<?php

namespace App\Support\Invoicing;

use App\Enums\InvoiceConcept;
use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Enums\RecipientDocumentType;
use App\Enums\TaxCondition;
use App\Models\Client;
use App\Models\Invoice;
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
 * Issues the Factura C of a payment: saves it with its own copy of the
 * recipient and items, takes the next number from ARCA and asks for the CAE.
 *
 * The number is saved before asking. If ARCA does not answer, the invoice
 * stays pending with that number, and the next attempt looks it up in ARCA
 * before asking again, so a lost answer never produces a duplicate.
 */
class InvoiceIssuer
{
    public function __construct(private readonly Wsfe $wsfe) {}

    /**
     * Issues the invoice of a payment, or retries the one that failed.
     *
     * @param  array{concept?: InvoiceConcept|int|string|null, service_from?: Carbon|string|null, service_to?: Carbon|string|null, items?: array<int, array{description?: ?string, detail?: ?string}>}  $data  what the preview changed
     *
     * @throws InvoicingException
     */
    public function issue(Payment $payment, array $data = []): Invoice
    {
        $settings = Settings::invoicing();

        if (! $settings['enabled']) {
            throw new InvoicingException('La facturación electrónica está desactivada en Configuración.');
        }

        if ($settings['point_of_sale'] === null) {
            throw new InvoicingException('Cargá el punto de venta de ARCA en Configuración.');
        }

        if (strlen(Cuit::normalize((string) Settings::get('company.cuit'))) !== 11) {
            throw new InvoicingException('Cargá el CUIT de la empresa en Configuración.');
        }

        $invoice = $payment->invoice;

        if ($invoice?->isAuthorized()) {
            throw new InvoicingException("El pago {$payment->code} ya tiene la {$invoice->title}.");
        }

        // Sent without an answer: ARCA may have it, so it is looked up as it was sent.
        if ($invoice?->number !== null && $invoice->environment === config('arca.environment')) {
            return $this->authorize($invoice);
        }

        $invoice = DB::transaction(function () use ($payment, $data, $invoice, $settings) {
            $invoice ??= new Invoice;
            $invoice->fill([
                ...$this->recipient($payment),
                ...$this->content($payment, $data),
                'client_id' => $payment->client_id,
                'payment_id' => $payment->id,
                'environment' => config('arca.environment'),
                'type' => InvoiceType::FacturaC,
                'point_of_sale' => $settings['point_of_sale'],
                'number' => null,
                'issued_on' => null,
                'status' => InvoiceStatus::Pending,
                'messages' => null,
            ]);
            $invoice->save();

            $invoice->items()->delete();
            $invoice->items()->createMany($this->items($payment, $data));

            return $invoice;
        });

        return $this->authorize($invoice);
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
     * The payment's date when ARCA still accepts it, otherwise today; never
     * before the last invoice of the point of sale, which ARCA rejects.
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
     * FECAEDetRequest for a Factura C: no VAT, the whole total is net.
     *
     * @return array<string, mixed>
     */
    private function detail(Invoice $invoice): array
    {
        $total = number_format($invoice->total, 2, '.', '');
        $servicePeriod = $invoice->concept->hasServicePeriod();

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
            'FchVtoPago' => $servicePeriod ? $invoice->issued_on?->format('Ymd') : null,
            'MonId' => 'PES',
            'MonCotiz' => 1,
            'CondicionIVAReceptorId' => $invoice->recipient_tax_condition->arcaId(),
        ];
    }

    /**
     * The client's data as of today, copied to the invoice.
     *
     * @return array<string, mixed>
     */
    private function recipient(Payment $payment): array
    {
        $client = $payment->client;
        $taxCondition = $client->tax_condition ?? TaxCondition::ConsumidorFinal;
        [$documentType, $documentNumber] = self::document($client);

        if ($taxCondition !== TaxCondition::ConsumidorFinal && $documentType !== RecipientDocumentType::Cuit) {
            throw new InvoicingException("{$client->business_name} figura como {$taxCondition->getLabel()}: cargale el CUIT para facturarle.");
        }

        $limit = (float) config('arca.unidentified_limit');

        if ($documentType === RecipientDocumentType::Unidentified && $payment->amount >= $limit) {
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
     * Concept and service period: the draft's, unless the preview changed them.
     *
     * @param  array<string, mixed>  $data
     * @return array{concept: InvoiceConcept, service_from: ?CarbonInterface, service_to: ?CarbonInterface, total: float}
     */
    private function content(Payment $payment, array $data): array
    {
        $draft = InvoiceDraft::for($payment);

        if ($payment->amount <= 0) {
            throw new InvoicingException('El pago no tiene monto para facturar.');
        }

        $concept = $data['concept'] ?? null;
        $concept = $concept instanceof InvoiceConcept ? $concept : (filled($concept) ? InvoiceConcept::from((int) $concept) : $draft['concept']);

        if (! $concept->hasServicePeriod()) {
            return ['concept' => $concept, 'service_from' => null, 'service_to' => null, 'total' => $payment->amount];
        }

        $from = filled($data['service_from'] ?? null) ? Carbon::parse($data['service_from']) : $draft['service_from'];
        $to = filled($data['service_to'] ?? null) ? Carbon::parse($data['service_to']) : $draft['service_to'];

        if ($from->gt($to)) {
            throw new InvoicingException('El período facturado termina antes de empezar.');
        }

        return ['concept' => $concept, 'service_from' => $from, 'service_to' => $to, 'total' => $payment->amount];
    }

    /**
     * The draft's items with the descriptions from the preview; amounts always come from the payment.
     *
     * @param  array<string, mixed>  $data
     * @return list<array{description: string, detail: ?string, amount: float, position: int}>
     */
    private function items(Payment $payment, array $data): array
    {
        $edited = array_values(is_array($data['items'] ?? null) ? $data['items'] : []);

        return collect(InvoiceDraft::for($payment)['items'])
            ->map(fn (array $item, int $index) => [
                'description' => filled($edited[$index]['description'] ?? null) ? (string) $edited[$index]['description'] : $item['description'],
                'detail' => array_key_exists('detail', $edited[$index] ?? []) ? ($edited[$index]['detail'] ?: null) : $item['detail'],
                'amount' => $item['amount'],
                'position' => $index,
            ])
            ->values()
            ->all();
    }
}
