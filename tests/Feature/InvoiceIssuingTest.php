<?php

namespace Tests\Feature;

use App\Enums\InvoiceConcept;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Enums\TaxCondition;
use App\Models\Charge;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Subscription;
use App\Support\Arca\InvoiceQr;
use App\Support\Invoicing\InvoiceDraft;
use App\Support\Invoicing\InvoiceIssuer;
use App\Support\Invoicing\InvoicingException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\FakesArca;
use Tests\TestCase;

class InvoiceIssuingTest extends TestCase
{
    use FakesArca, RefreshDatabase;

    private function pay(Client $client, float $amount, array $data = []): Payment
    {
        return Payment::register(['client_id' => $client->id, 'paid_on' => today(), 'amount' => $amount, 'method' => PaymentMethod::Transfer, ...$data]);
    }

    private function issue(Payment $payment, array $data = []): Invoice
    {
        return app(InvoiceIssuer::class)->issue($payment, $data);
    }

    private function registeredTaxpayer(): Client
    {
        return Client::factory()->create([
            'business_name' => 'Estudio Contable Ríos SRL',
            'cuit' => '30-71234567-1',
            'tax_condition' => TaxCondition::ResponsableInscripto,
            'address' => 'Córdoba 1450',
            'city' => 'Rosario',
            'province' => 'Santa Fe',
        ]);
    }

    public function test_issues_a_factura_c_with_the_cae_and_archives_its_pdf(): void
    {
        $this->travelTo('2026-11-02 10:00');
        Storage::fake('local');
        $this->enableInvoicing();
        $this->fakeArca([
            'FECompUltimoAutorizado' => [self::lastAuthorized(124)],
            'FECAESolicitar' => [self::approved('76443213456789', '20261112')],
        ]);
        $client = $this->registeredTaxpayer();
        Charge::factory()->for($client)->create(['concept' => 'Instalación de red', 'amount' => 85000, 'issued_on' => '2026-10-15']);

        $invoice = $this->issue($this->pay($client, 85000));

        $this->assertSame(InvoiceStatus::Authorized, $invoice->status);
        $this->assertSame('00003-00000125', $invoice->code);
        $this->assertSame('76443213456789', $invoice->cae);
        $this->assertSame('2026-11-12', $invoice->cae_due_on->toDateString());
        $this->assertSame('2026-11-02', $invoice->issued_on->toDateString());
        $this->assertSame('Estudio Contable Ríos SRL', $invoice->recipient_name);
        $this->assertSame('Córdoba 1450, Rosario, Santa Fe', $invoice->recipient_address);
        $this->assertSame(['Instalación de red'], $invoice->items->pluck('description')->all());
        Storage::disk('local')->assertExists($invoice->pdf_path);

        $request = $this->arcaRequests('FECAESolicitar')[0];
        foreach ([
            '<PtoVta>3</PtoVta><CbteTipo>11</CbteTipo>',
            '<Concepto>2</Concepto><DocTipo>80</DocTipo><DocNro>30712345671</DocNro>',
            '<CbteDesde>125</CbteDesde><CbteHasta>125</CbteHasta><CbteFch>20261102</CbteFch>',
            '<ImpTotal>85000.00</ImpTotal><ImpTotConc>0.00</ImpTotConc><ImpNeto>85000.00</ImpNeto><ImpOpEx>0.00</ImpOpEx><ImpTrib>0.00</ImpTrib><ImpIVA>0.00</ImpIVA>',
            '<FchServDesde>20261015</FchServDesde><FchServHasta>20261015</FchServHasta><FchVtoPago>20261102</FchVtoPago>',
            '<MonId>PES</MonId><MonCotiz>1</MonCotiz><CondicionIVAReceptorId>1</CondicionIVAReceptorId>',
        ] as $fragment) {
            $this->assertStringContainsString($fragment, $request);
        }
        $this->assertStringNotContainsString('<Iva>', $request);
    }

    public function test_a_rejection_keeps_arca_messages_and_releases_the_number(): void
    {
        $this->enableInvoicing();
        $this->fakeArca([
            'FECompUltimoAutorizado' => [self::lastAuthorized(124)],
            'FECAESolicitar' => [self::rejected('10016: El campo CbteFch es inválido')],
        ]);

        $invoice = $this->issue($this->pay(Client::factory()->create(['tax_condition' => TaxCondition::ConsumidorFinal]), 1000));

        $this->assertSame(InvoiceStatus::Rejected, $invoice->status);
        $this->assertNull($invoice->number);
        $this->assertSame(['10016: El campo CbteFch es inválido'], $invoice->messages);
    }

    public function test_without_an_answer_the_retry_finds_the_invoice_in_arca_instead_of_asking_again(): void
    {
        $this->enableInvoicing();
        $this->fakeArca([
            'FECompUltimoAutorizado' => [self::lastAuthorized(124)],
            'FECAESolicitar' => [Http::failedConnection()],
            'FECompConsultar' => [self::found(125, 1000, '0', '71111111111111')],
        ]);
        $payment = $this->pay(Client::factory()->create(['tax_condition' => TaxCondition::ConsumidorFinal]), 1000);

        $pending = $this->issue($payment);

        $this->assertSame(InvoiceStatus::Pending, $pending->status);
        $this->assertSame(125, $pending->number);
        $this->assertStringContainsString('ARCA no respondió', $pending->messages[0]);

        $invoice = $this->issue($payment->refresh());

        $this->assertSame(InvoiceStatus::Authorized, $invoice->status);
        $this->assertSame(125, $invoice->number);
        $this->assertSame('71111111111111', $invoice->cae);
        $this->assertCount(1, $this->arcaRequests('FECAESolicitar'));
        $this->assertStringContainsString('<CbteNro>125</CbteNro>', $this->arcaRequests('FECompConsultar')[0]);
    }

    public function test_the_retry_asks_for_a_new_cae_when_arca_never_got_the_invoice(): void
    {
        $this->enableInvoicing();
        $this->fakeArca([
            'FECompUltimoAutorizado' => [self::lastAuthorized(124), self::lastAuthorized(124)],
            'FECAESolicitar' => [Http::failedConnection(), self::approved()],
            'FECompConsultar' => [self::notFound()],
        ]);
        $payment = $this->pay(Client::factory()->create(['tax_condition' => TaxCondition::ConsumidorFinal]), 1000);
        $this->issue($payment);

        $invoice = $this->issue($payment->refresh());

        $this->assertSame(InvoiceStatus::Authorized, $invoice->status);
        $this->assertSame(125, $invoice->number);
        $this->assertCount(2, $this->arcaRequests('FECAESolicitar'));
    }

    /**
     * @return array<string, array{int, bool, int}>
     */
    public static function paymentDates(): array
    {
        return [
            'service paid 3 days ago keeps its date' => [3, false, 3],
            'service paid 12 days ago is invoiced today' => [12, false, 0],
            'product paid 4 days ago keeps its date' => [4, true, 4],
            'product paid 7 days ago is invoiced today' => [7, true, 0],
        ];
    }

    #[DataProvider('paymentDates')]
    public function test_dates_the_invoice_on_the_payment_while_arca_accepts_it(int $paidDaysAgo, bool $resale, int $expectedDaysAgo): void
    {
        $this->travelTo('2026-11-20 10:00');
        $this->enableInvoicing();
        $this->fakeArca([
            'FECompUltimoAutorizado' => [self::lastAuthorized(0)],
            'FECAESolicitar' => [self::approved()],
        ]);
        $client = Client::factory()->create(['tax_condition' => TaxCondition::ConsumidorFinal]);
        $resale
            ? Charge::factory()->for($client)->resale(500)->create(['amount' => 1000])
            : Charge::factory()->for($client)->create(['amount' => 1000]);

        $invoice = $this->issue($this->pay($client, 1000, ['paid_on' => today()->subDays($paidDaysAgo)]));

        $this->assertSame(today()->subDays($expectedDaysAgo)->toDateString(), $invoice->issued_on->toDateString());
        $this->assertStringContainsString('<CbteFch>'.today()->subDays($expectedDaysAgo)->format('Ymd').'</CbteFch>', $this->arcaRequests('FECAESolicitar')[0]);
    }

    public function test_never_dates_an_invoice_before_the_last_one_of_the_point_of_sale(): void
    {
        $this->travelTo('2026-11-20 10:00');
        $this->enableInvoicing();
        $this->fakeArca([
            'FECompUltimoAutorizado' => [self::lastAuthorized(1)],
            'FECAESolicitar' => [self::approved()],
        ]);
        Invoice::factory()->authorized()->create(['number' => 1, 'issued_on' => '2026-11-18']);

        $invoice = $this->issue($this->pay(Client::factory()->create(['tax_condition' => TaxCondition::ConsumidorFinal]), 1000, ['paid_on' => '2026-11-15']));

        $this->assertSame('2026-11-18', $invoice->issued_on->toDateString());
    }

    public function test_products_go_without_service_period(): void
    {
        $this->enableInvoicing();
        $this->fakeArca([
            'FECompUltimoAutorizado' => [self::lastAuthorized(0)],
            'FECAESolicitar' => [self::approved()],
        ]);
        $client = Client::factory()->create(['tax_condition' => TaxCondition::ConsumidorFinal]);
        Charge::factory()->for($client)->resale(500)->create(['amount' => 1000]);

        $invoice = $this->issue($this->pay($client, 1000));

        $this->assertSame(InvoiceConcept::Products, $invoice->concept);
        $request = $this->arcaRequests('FECAESolicitar')[0];
        $this->assertStringContainsString('<Concepto>1</Concepto>', $request);
        $this->assertStringNotContainsString('FchServDesde', $request);
        $this->assertStringNotContainsString('FchVtoPago', $request);
    }

    /**
     * @return array<string, array{list<bool>, InvoiceConcept}>
     */
    public static function resaleMixes(): array
    {
        return [
            'only services' => [[false, false], InvoiceConcept::Services],
            'only resale' => [[true, true], InvoiceConcept::Products],
            'both' => [[true, false], InvoiceConcept::Both],
        ];
    }

    #[DataProvider('resaleMixes')]
    public function test_the_concept_follows_what_the_charges_sold(array $resale, InvoiceConcept $expected): void
    {
        $client = Client::factory()->create();
        foreach ($resale as $isResale) {
            Charge::factory()->for($client)->create(['amount' => 1000, 'is_resale' => $isResale, 'cost' => $isResale ? 500 : null]);
        }

        $this->assertSame($expected, InvoiceDraft::for($this->pay($client, 2000))['concept']);
    }

    public function test_items_list_what_the_payment_settled_and_what_was_left_on_account(): void
    {
        $client = Client::factory()->create();
        Charge::factory()->for($client)->create(['concept' => 'Visita técnica', 'amount' => 45000, 'issued_on' => today()->subMonth()]);
        Charge::factory()->for($client)->create(['concept' => 'Mantenimiento', 'amount' => 85000]);

        $items = InvoiceDraft::for($this->pay($client, 150000))['items'];

        $this->assertSame([
            ['description' => 'Visita técnica', 'detail' => null, 'amount' => 45000.0],
            ['description' => 'Mantenimiento', 'detail' => null, 'amount' => 85000.0],
            ['description' => 'Pago a cuenta', 'detail' => null, 'amount' => 20000.0],
        ], $items);
    }

    public function test_partial_payments_say_so_and_subscriptions_bill_their_month(): void
    {
        $client = Client::factory()->create();
        $subscription = Subscription::factory()->for($client)->create();
        Charge::factory()->for($client)->for($subscription)->create(['concept' => 'Abono octubre', 'amount' => 85000, 'period' => '2026-10-01', 'issued_on' => '2026-10-05']);

        $draft = InvoiceDraft::for($this->pay($client, 15000));

        $this->assertSame('Pago parcial de $ 85.000,00', $draft['items'][0]['detail']);
        $this->assertSame('2026-10-01', $draft['service_from']->toDateString());
        $this->assertSame('2026-10-31', $draft['service_to']->toDateString());
    }

    /**
     * @return array<string, array{array<string, mixed>, string, string, string}>
     */
    public static function recipients(): array
    {
        return [
            'with CUIT' => [['cuit' => '30-71234567-1', 'tax_condition' => TaxCondition::Monotributista], '80', '30712345671', '6'],
            'with DNI' => [['dni' => '28123456', 'tax_condition' => TaxCondition::ConsumidorFinal], '96', '28123456', '5'],
            'unidentified' => [['tax_condition' => null], '99', '0', '5'],
        ];
    }

    #[DataProvider('recipients')]
    public function test_identifies_the_recipient_as_arca_expects(array $client, string $documentType, string $documentNumber, string $taxCondition): void
    {
        $this->enableInvoicing();
        $this->fakeArca([
            'FECompUltimoAutorizado' => [self::lastAuthorized(0)],
            'FECAESolicitar' => [self::approved()],
        ]);

        $this->issue($this->pay(Client::factory()->create($client), 1000));

        $request = $this->arcaRequests('FECAESolicitar')[0];
        $this->assertStringContainsString("<DocTipo>{$documentType}</DocTipo><DocNro>{$documentNumber}</DocNro>", $request);
        $this->assertStringContainsString("<CondicionIVAReceptorId>{$taxCondition}</CondicionIVAReceptorId>", $request);
    }

    public function test_a_registered_taxpayer_needs_a_cuit(): void
    {
        $this->enableInvoicing();
        Http::preventStrayRequests();
        $payment = $this->pay(Client::factory()->create(['business_name' => 'Ferretería Los Pinos', 'tax_condition' => TaxCondition::ResponsableInscripto]), 1000);

        try {
            $this->issue($payment);
            $this->fail('Invoiced a registered taxpayer without CUIT.');
        } catch (InvoicingException $exception) {
            $this->assertSame('Ferretería Los Pinos figura como Responsable inscripto: cargale el CUIT para facturarle.', $exception->getMessage());
        }

        $this->assertSame(0, Invoice::count());
    }

    public function test_from_the_limit_a_consumer_must_be_identified(): void
    {
        $this->enableInvoicing();
        config(['arca.unidentified_limit' => 100000]);
        Http::preventStrayRequests();

        $this->expectException(InvoicingException::class);
        $this->expectExceptionMessage('ARCA exige identificar al consumidor final');

        $this->issue($this->pay(Client::factory()->create(['tax_condition' => TaxCondition::ConsumidorFinal]), 100000));
    }

    /**
     * @return array<string, array{array<string, mixed>, string}>
     */
    public static function incompleteSettings(): array
    {
        return [
            'invoicing off' => [['invoicing.enabled' => false], 'desactivada'],
            'no point of sale' => [['invoicing.point_of_sale' => null], 'punto de venta'],
            'no company CUIT' => [['company.cuit' => null], 'CUIT de la empresa'],
        ];
    }

    #[DataProvider('incompleteSettings')]
    public function test_refuses_to_invoice_with_incomplete_settings(array $settings, string $message): void
    {
        $this->enableInvoicing($settings);
        Http::preventStrayRequests();

        $this->expectException(InvoicingException::class);
        $this->expectExceptionMessage($message);

        $this->issue($this->pay(Client::factory()->create(), 1000));
    }

    public function test_an_invoiced_payment_cannot_be_deleted(): void
    {
        $payment = $this->pay(Client::factory()->create(), 1000);
        Invoice::factory()->authorized()->for($payment)->create(['client_id' => $payment->client_id]);

        $this->expectException(LogicException::class);

        $payment->delete();
    }

    public function test_deleting_a_payment_drops_its_unauthorized_invoice(): void
    {
        $payment = $this->pay(Client::factory()->create(), 1000);
        $invoice = Invoice::factory()->for($payment)->create(['client_id' => $payment->client_id, 'status' => InvoiceStatus::Rejected]);

        $payment->delete();

        $this->assertModelMissing($invoice);
    }

    public function test_the_qr_carries_the_invoice_data_for_arca(): void
    {
        $this->enableInvoicing();
        $invoice = Invoice::factory()->authorized()->create([
            'number' => 125,
            'issued_on' => '2026-11-02',
            'total' => 145000,
            'recipient_document_type' => 80,
            'recipient_document_number' => '30712345671',
            'cae' => '76443213456789',
        ]);

        $url = InvoiceQr::url($invoice);

        $this->assertStringStartsWith('https://www.afip.gob.ar/fe/qr/?p=', $url);
        $this->assertSame([
            'ver' => 1,
            'fecha' => '2026-11-02',
            'cuit' => 20304050609,
            'ptoVta' => 3,
            'tipoCmp' => 11,
            'nroCmp' => 125,
            'importe' => 145000,
            'moneda' => 'PES',
            'ctz' => 1,
            'tipoDocRec' => 80,
            'nroDocRec' => 30712345671,
            'tipoCodAut' => 'E',
            'codAut' => 76443213456789,
        ], json_decode(base64_decode(substr($url, strlen('https://www.afip.gob.ar/fe/qr/?p='))), true));
        $this->assertStringStartsWith('data:image/png;base64,', InvoiceQr::dataUri($invoice));
    }

    public function test_an_invoiced_payment_is_not_sent_to_arca_again(): void
    {
        $this->enableInvoicing();
        Http::preventStrayRequests();
        $payment = $this->pay(Client::factory()->create(), 1000);
        Invoice::factory()->authorized()->for($payment)->create(['client_id' => $payment->client_id]);

        $this->expectException(InvoicingException::class);
        $this->expectExceptionMessage('ya tiene la Factura C');

        $this->issue($payment->refresh());
    }

    public function test_test_invoices_never_take_numbers_from_production(): void
    {
        $this->enableInvoicing();
        $this->fakeArca([
            'FECompUltimoAutorizado' => [self::lastAuthorized(0)],
            'FECAESolicitar' => [self::approved()],
        ]);
        Invoice::factory()->authorized()->create(['environment' => 'produccion', 'number' => 1, 'issued_on' => today()->addDays(5)]);

        $invoice = $this->issue($this->pay(Client::factory()->create(['tax_condition' => TaxCondition::ConsumidorFinal]), 1000));

        $this->assertSame(1, $invoice->number);
        $this->assertSame(today()->toDateString(), $invoice->issued_on->toDateString());
        Http::assertSent(fn (Request $request) => str_contains($request->url(), 'wswhomo'));
    }
}
