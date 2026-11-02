<?php

namespace App\Support\Invoicing;

use App\Enums\ChargeStatus;
use App\Enums\InvoiceConcept;
use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Enums\RecipientDocumentType;
use App\Enums\TaxCondition;
use App\Models\Charge;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Payment;
use App\Rules\Cuit;
use App\Support\Arca\ArcaException;
use App\Support\Arca\Wsfe;
use App\Support\InvoicePdf;
use App\Support\QuotePdf;
use App\Support\Settings;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Issues the Factura C of a payment (after payment) or of a charge (before
 * payment), and the Nota de crédito C that annuls one: saves it with its own
 * copy of the recipient and items, takes the next number from ARCA and asks
 * for the CAE.
 *
 * The number is saved before asking. If ARCA does not answer, the invoice
 * stays pending with that number, and the next attempt looks it up in ARCA
 * before asking again, so a lost answer never produces a duplicate.
 */
class InvoiceIssuer
{
    public function __construct(private readonly Wsfe $wsfe) {}

    /**
     * Invoices what a payment settles that is not invoiced yet (after
     * payment), or retries the payment's invoice that failed.
     *
     * @param  array<string, mixed>  $data  what the preview changed: concept, service_from, service_to, items
     *
     * @throws InvoicingException
     */
    public function issueForPayment(Payment $payment, array $data = []): Invoice
    {
        $settings = $this->settings();
        $previous = $payment->invoice;

        if ($previous?->isInForce()) {
            throw new InvoicingException("El pago {$payment->code} ya tiene la {$previous->title}.");
        }

        if ($previous?->isAwaitingArca()) {
            return $this->authorize($previous);
        }

        $retrying = $previous?->isAuthorized() === false ? $previous : null;
        $draft = InvoiceDraft::forPayment($payment, $retrying);

        if ($draft['total'] <= 0) {
            throw new InvoicingException("Todo lo que cancela el recibo {$payment->code} ya está facturado.");
        }

        $invoice = $this->authorize($this->save($retrying, $payment->client, $draft, $data, $settings['point_of_sale'], [
            'payment_id' => $payment->id,
        ]));
        $payment->setRelation('invoice', $invoice);

        return $invoice;
    }

    /**
     * Invoices a charge before it is paid (the client needs the invoice to
     * pay), or retries the charge's invoice that failed.
     *
     * @param  array<string, mixed>  $data  what the preview changed: concept, service_from, service_to, items
     *
     * @throws InvoicingException
     */
    public function issueForCharge(Charge $charge, array $data = []): Invoice
    {
        $settings = $this->settings();

        if ($charge->status === ChargeStatus::Draft) {
            throw new InvoicingException('Confirmá el monto del cargo antes de facturarlo.');
        }

        if ($charge->status === ChargeStatus::Cancelled) {
            throw new InvoicingException('El cargo está anulado.');
        }

        $previous = $charge->invoice;

        if ($previous?->isAwaitingArca()) {
            return $this->authorize($previous);
        }

        $retrying = $previous?->isAuthorized() === false ? $previous : null;
        $draft = InvoiceDraft::forCharge($charge, $retrying);

        if ($draft['total'] <= 0) {
            throw new InvoicingException('El cargo ya está facturado.');
        }

        $invoice = $this->authorize($this->save($retrying, $charge->client, $draft, $data, $settings['point_of_sale'], [
            'charge_id' => $charge->id,
        ]));
        $charge->setRelation('invoice', $invoice);

        return $invoice;
    }

    /**
     * Annuls an authorized invoice with a Nota de crédito C for its total,
     * with the same recipient, items and period. Retries one that failed.
     *
     * @throws InvoicingException
     */
    public function creditNote(Invoice $invoice): Invoice
    {
        $this->settings(requireEnabled: false);

        if ($invoice->isCreditNote() || ! $invoice->isAuthorized()) {
            throw new InvoicingException('Solo se anula con nota de crédito una factura autorizada.');
        }

        if ($invoice->environment !== config('arca.environment')) {
            throw new InvoicingException("La {$invoice->title} es del otro ambiente de ARCA: se anula desde ese ambiente.");
        }

        $previous = $invoice->creditNote;

        if ($previous?->isAuthorized()) {
            throw new InvoicingException("La {$invoice->title} ya está anulada con la {$previous->title}.");
        }

        if ($previous?->isAwaitingArca()) {
            $note = $this->authorize($previous);
            $invoice->setRelation('creditNote', $note);

            return $note;
        }

        $note = DB::transaction(function () use ($invoice, $previous) {
            $note = $previous ?? new Invoice;
            $note->fill([
                ...$invoice->only([
                    'client_id', 'environment', 'point_of_sale', 'concept', 'service_from', 'service_to',
                    'recipient_document_type', 'recipient_document_number', 'recipient_tax_condition',
                    'recipient_name', 'recipient_address', 'total',
                ]),
                'type' => InvoiceType::NotaCreditoC,
                'credited_invoice_id' => $invoice->id,
                'payment_id' => null,
                'charge_id' => null,
                'payment_due_on' => null,
                'number' => null,
                'issued_on' => null,
                'status' => InvoiceStatus::Pending,
                'messages' => null,
            ]);
            $note->save();

            $note->items()->delete();
            $note->items()->createMany($invoice->items->map(fn (InvoiceItem $item) => $item->only(['charge_id', 'description', 'detail', 'amount', 'position']))->all());

            return $note;
        });

        $note = $this->authorize($note);
        $invoice->setRelation('creditNote', $note);

        return $note;
    }

    /**
     * Asks ARCA for the CAE. Never throws for ARCA's sake: errors end up in the invoice's messages.
     */
    public function authorize(Invoice $invoice): Invoice
    {
        if ($invoice->isAuthorized()) {
            return $invoice;
        }

        try {
            Cache::lock("arca.vouchers.{$invoice->environment}.{$invoice->point_of_sale}.{$invoice->type->value}", 120)
                ->block(10, fn () => $this->authorizeLocked($invoice));
        } catch (LockTimeoutException) {
            $this->leavePending($invoice, 'Otra factura está pidiendo CAE en este momento. Reintentá en unos segundos.');
        }

        return $invoice->refresh();
    }

    private function authorizeLocked(Invoice $invoice): void
    {
        try {
            $this->reconcile($invoice->point_of_sale, $invoice->type);

            if ($invoice->refresh()->isAuthorized()) {
                return;
            }

            $invoice->number = $this->wsfe->lastAuthorized($invoice->point_of_sale, $invoice->type) + 1;
            $invoice->issued_on = $this->issueDate($invoice);
            $invoice->status = InvoiceStatus::Pending;
            $invoice->messages = null;
            $invoice->save();

            $result = $this->wsfe->requestCae($invoice->point_of_sale, $invoice->type, $this->detail($invoice));
        } catch (ConnectionException) {
            $this->leavePending($invoice, 'ARCA no respondió. Al reintentar, primero se consulta si llegó a autorizarla.');

            return;
        } catch (ArcaException $exception) {
            $this->leavePending($invoice, $exception->getMessage());

            return;
        }

        if ($result->approved) {
            $this->markAuthorized($invoice, (string) $result->cae, $result->caeDueOn, $result->messages, $result->response);

            return;
        }

        $invoice->update([
            'status' => InvoiceStatus::Rejected,
            'number' => null,
            'messages' => $result->messages ?: ['ARCA rechazó la factura sin indicar el motivo.'],
            'arca_response' => $result->response,
        ]);
    }

    /**
     * Settles invoices left pending with a number: authorized if ARCA has them
     * with the same total and recipient; otherwise the number is released.
     */
    private function reconcile(int $pointOfSale, InvoiceType $type): void
    {
        Invoice::query()
            ->currentEnvironment()
            ->where('point_of_sale', $pointOfSale)
            ->where('type', $type)
            ->where('status', InvoiceStatus::Pending)
            ->whereNotNull('number')
            ->get()
            ->each(function (Invoice $pending) use ($pointOfSale, $type) {
                $voucher = $this->wsfe->find($pointOfSale, $type, (int) $pending->number);

                $isThisInvoice = $voucher !== null
                    && trim((string) ($voucher['CodAutorizacion'] ?? '')) !== ''
                    && abs((float) ($voucher['ImpTotal'] ?? -1) - $pending->total) < 0.01
                    && (string) ($voucher['DocNro'] ?? '') === $pending->recipient_document_number;

                if (! $isThisInvoice) {
                    $pending->update(['number' => null, 'issued_on' => null]);

                    return;
                }

                $pending->issued_on = Wsfe::date($voucher['CbteFch'] ?? null) ?? $pending->issued_on;

                $this->markAuthorized(
                    $pending,
                    trim((string) $voucher['CodAutorizacion']),
                    Wsfe::date($voucher['FchVto'] ?? null),
                    [],
                    $voucher,
                );
            });
    }

    /**
     * @param  list<string>  $messages
     * @param  array<string, mixed>  $response
     */
    private function markAuthorized(Invoice $invoice, string $cae, ?Carbon $caeDueOn, array $messages, array $response): void
    {
        $invoice->fill([
            'status' => InvoiceStatus::Authorized,
            'cae' => $cae,
            'cae_due_on' => $caeDueOn,
            'messages' => $messages ?: null,
            'arca_response' => $response,
        ])->save();

        $invoice->update(['pdf_path' => InvoicePdf::store($invoice)]);
    }

    private function leavePending(Invoice $invoice, string $message): void
    {
        $invoice->update(['status' => InvoiceStatus::Pending, 'messages' => [$message]]);
    }

    /**
     * A payment's invoice takes the payment's date when ARCA still accepts it;
     * everything else is dated today. Never before the last voucher of the
     * point of sale, which ARCA rejects.
     */
    private function issueDate(Invoice $invoice): Carbon
    {
        $date = $invoice->payment?->paid_on->copy() ?? today();

        if ($date->lt(today()->subDays($invoice->concept->maxDaysBack())) || $date->gt(today())) {
            $date = today();
        }

        $last = Invoice::query()
            ->currentEnvironment()
            ->where('point_of_sale', $invoice->point_of_sale)
            ->where('type', $invoice->type)
            ->where('status', InvoiceStatus::Authorized)
            ->max('issued_on');

        return Carbon::instance($last !== null && $date->lt(Carbon::parse($last)) ? Carbon::parse($last) : $date);
    }

    /**
     * FECAEDetRequest for a Factura C or Nota de crédito C: no VAT, the whole
     * total is net. A credit note names the invoice it annuls (CbtesAsoc).
     *
     * @return array<string, mixed>
     */
    private function detail(Invoice $invoice): array
    {
        $total = number_format($invoice->total, 2, '.', '');
        $servicePeriod = $invoice->concept->hasServicePeriod();
        // ARCA rejects a payment due date before the voucher's date.
        $paymentDueOn = $invoice->payment_due_on !== null && $invoice->issued_on !== null && $invoice->payment_due_on->gt($invoice->issued_on)
            ? $invoice->payment_due_on
            : $invoice->issued_on;
        $credited = $invoice->creditedInvoice;

        return [
            'Concepto' => $invoice->concept->value,
            'DocTipo' => $invoice->recipient_document_type->value,
            'DocNro' => $invoice->recipient_document_number,
            'CbteDesde' => $invoice->number,
            'CbteHasta' => $invoice->number,
            'CbteFch' => $invoice->issued_on?->format('Ymd'),
            'ImpTotal' => $total,
            'ImpTotConc' => '0.00',
            'ImpNeto' => $total,
            'ImpOpEx' => '0.00',
            'ImpTrib' => '0.00',
            'ImpIVA' => '0.00',
            'FchServDesde' => $servicePeriod ? $invoice->service_from?->format('Ymd') : null,
            'FchServHasta' => $servicePeriod ? $invoice->service_to?->format('Ymd') : null,
            'FchVtoPago' => $servicePeriod ? $paymentDueOn?->format('Ymd') : null,
            'MonId' => 'PES',
            'MonCotiz' => 1,
            'CondicionIVAReceptorId' => $invoice->recipient_tax_condition->arcaId(),
            'CbtesAsoc' => $credited === null ? null : [
                'CbteAsoc' => [
                    'Tipo' => $credited->type->value,
                    'PtoVta' => $credited->point_of_sale,
                    'Nro' => $credited->number,
                    'Cuit' => Cuit::normalize((string) Settings::get('company.cuit')),
                    'CbteFch' => $credited->issued_on?->format('Ymd'),
                ],
            ],
        ];
    }

    /**
     * Saves the invoice with its recipient, content and items, ready to authorize.
     *
     * @param  array{concept: InvoiceConcept, service_from: CarbonInterface, service_to: CarbonInterface, payment_due_on: ?CarbonInterface, items: list<array{charge_id: ?int, description: string, detail: ?string, amount: float}>, total: float}  $draft
     * @param  array<string, mixed>  $data
     * @param  array<string, int>  $source
     */
    private function save(?Invoice $invoice, Client $client, array $draft, array $data, int $pointOfSale, array $source): Invoice
    {
        return DB::transaction(function () use ($invoice, $client, $draft, $data, $pointOfSale, $source) {
            $invoice ??= new Invoice;
            $invoice->fill([
                ...$this->recipient($client, $draft['total']),
                ...$this->content($draft, $data),
                ...$source,
                'client_id' => $client->id,
                'environment' => config('arca.environment'),
                'type' => InvoiceType::FacturaC,
                'point_of_sale' => $pointOfSale,
                'number' => null,
                'issued_on' => null,
                'status' => InvoiceStatus::Pending,
                'messages' => null,
            ]);
            $invoice->save();

            $invoice->items()->delete();
            $invoice->items()->createMany($this->items($draft, $data));

            return $invoice;
        });
    }

    /**
     * @return array{enabled: bool, point_of_sale: int}
     *
     * @throws InvoicingException
     */
    private function settings(bool $requireEnabled = true): array
    {
        $settings = Settings::invoicing();

        if ($requireEnabled && ! $settings['enabled']) {
            throw new InvoicingException('La facturación electrónica está desactivada en Configuración.');
        }

        if ($settings['point_of_sale'] === null) {
            throw new InvoicingException('Cargá el punto de venta de ARCA en Configuración.');
        }

        if (strlen(Cuit::normalize((string) Settings::get('company.cuit'))) !== 11) {
            throw new InvoicingException('Cargá el CUIT de la empresa en Configuración.');
        }

        return ['enabled' => $settings['enabled'], 'point_of_sale' => $settings['point_of_sale']];
    }

    /**
     * The client's data as of today, copied to the invoice.
     *
     * @return array<string, mixed>
     */
    private function recipient(Client $client, float $total): array
    {
        $taxCondition = $client->tax_condition ?? TaxCondition::ConsumidorFinal;
        [$documentType, $documentNumber] = self::document($client);

        if ($taxCondition !== TaxCondition::ConsumidorFinal && $documentType !== RecipientDocumentType::Cuit) {
            throw new InvoicingException("{$client->business_name} figura como {$taxCondition->getLabel()}: cargale el CUIT para facturarle.");
        }

        $limit = (float) config('arca.unidentified_limit');

        if ($documentType === RecipientDocumentType::Unidentified && $total >= $limit) {
            throw new InvoicingException('Desde '.QuotePdf::money($limit).' ARCA exige identificar al consumidor final: cargale el CUIT o el DNI al cliente.');
        }

        return [
            'recipient_document_type' => $documentType,
            'recipient_document_number' => $documentNumber,
            'recipient_tax_condition' => $taxCondition,
            'recipient_name' => $client->business_name,
            'recipient_address' => $client->fullAddress(),
        ];
    }

    /**
     * @return array{RecipientDocumentType, string}
     */
    private static function document(Client $client): array
    {
        return match (true) {
            filled($client->cuit) => [RecipientDocumentType::Cuit, Cuit::normalize((string) $client->cuit)],
            filled($client->dni) => [RecipientDocumentType::Dni, (string) $client->dni],
            default => [RecipientDocumentType::Unidentified, '0'],
        };
    }

    /**
     * Concept, service period and payment due date: the draft's, unless the preview changed them.
     *
     * @param  array{concept: InvoiceConcept, service_from: CarbonInterface, service_to: CarbonInterface, payment_due_on: ?CarbonInterface, total: float}  $draft
     * @param  array<string, mixed>  $data
     * @return array{concept: InvoiceConcept, service_from: ?CarbonInterface, service_to: ?CarbonInterface, payment_due_on: ?CarbonInterface, total: float}
     */
    private function content(array $draft, array $data): array
    {
        $concept = $data['concept'] ?? null;
        $concept = $concept instanceof InvoiceConcept ? $concept : (filled($concept) ? InvoiceConcept::from((int) $concept) : $draft['concept']);
        $content = ['concept' => $concept, 'payment_due_on' => $draft['payment_due_on'], 'total' => $draft['total']];

        if (! $concept->hasServicePeriod()) {
            return [...$content, 'service_from' => null, 'service_to' => null];
        }

        $from = filled($data['service_from'] ?? null) ? Carbon::parse($data['service_from']) : $draft['service_from'];
        $to = filled($data['service_to'] ?? null) ? Carbon::parse($data['service_to']) : $draft['service_to'];

        if ($from->gt($to)) {
            throw new InvoicingException('El período facturado termina antes de empezar.');
        }

        return [...$content, 'service_from' => $from, 'service_to' => $to];
    }

    /**
     * The draft's items with the descriptions from the preview; amounts and charges always come from the draft.
     *
     * @param  array{items: list<array{charge_id: ?int, description: string, detail: ?string, amount: float}>}  $draft
     * @param  array<string, mixed>  $data
     * @return list<array{charge_id: ?int, description: string, detail: ?string, amount: float, position: int}>
     */
    private function items(array $draft, array $data): array
    {
        $edited = array_values(is_array($data['items'] ?? null) ? $data['items'] : []);

        return collect($draft['items'])
            ->map(fn (array $item, int $index) => [
                'charge_id' => $item['charge_id'],
                'description' => filled($edited[$index]['description'] ?? null) ? (string) $edited[$index]['description'] : $item['description'],
                'detail' => array_key_exists('detail', $edited[$index] ?? []) ? ($edited[$index]['detail'] ?: null) : $item['detail'],
                'amount' => $item['amount'],
                'position' => $index,
            ])
            ->values()
            ->all();
    }
}
