<?php

namespace Tests\Feature;

use App\Enums\InvoiceConcept;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Enums\TaxCondition;
use App\Filament\Resources\Invoices\InvoiceResource;
use App\Filament\Resources\Invoices\Pages\ListInvoices;
use App\Filament\Resources\Invoices\Pages\ViewInvoice;
use App\Filament\Resources\Payments\Pages\ViewPayment;
use App\Mail\InvoiceMail;
use App\Models\Charge;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Payment;
use App\Models\User;
use App\Support\Arca\InvoiceQr;
use App\Support\Settings;
use Filament\Forms\Components\Repeater;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\Concerns\FakesArca;
use Tests\TestCase;

class InvoicePanelTest extends TestCase
{
    use FakesArca, RefreshDatabase;

    private function pay(Client $client, float $amount): Payment
    {
        return Payment::register(['client_id' => $client->id, 'paid_on' => today(), 'amount' => $amount, 'method' => PaymentMethod::Transfer]);
    }

    private function invoiced(Payment $payment, array $attributes = []): Invoice
    {
        $invoice = Invoice::factory()->authorized()->for($payment)->create([
            'client_id' => $payment->client_id,
            'number' => 125,
            'total' => $payment->amount,
            'recipient_name' => 'Estudio Contable Ríos SRL',
            ...$attributes,
        ]);
        InvoiceItem::factory()->for($invoice)->create(['description' => 'Abono soporte IT', 'amount' => $payment->amount]);

        return $invoice;
    }

    public function test_issues_the_invoice_from_the_payment_page_after_the_preview(): void
    {
        $this->actingAs(User::factory()->create());
        $this->enableInvoicing();
        $this->fakeArca([
            'FECompUltimoAutorizado' => [self::lastAuthorized(124)],
            'FECAESolicitar' => [self::approved('76443213456789')],
        ]);
        $client = Client::factory()->create(['tax_condition' => TaxCondition::ConsumidorFinal]);
        Charge::factory()->for($client)->create(['concept' => 'Visita técnica', 'amount' => 45000]);
        $payment = $this->pay($client, 45000);
        $undoRepeaterFake = Repeater::fake();

        Livewire::test(ViewPayment::class, ['record' => $payment->getRouteKey()])
            ->mountAction('issueInvoice')
            ->assertActionDataSet([
                'concept' => InvoiceConcept::Services,
                'items' => [['description' => 'Visita técnica', 'detail' => null, 'amount' => '45.000,00']],
            ])
            ->setActionData(['items' => [['description' => 'Visita técnica a domicilio', 'detail' => 'Sábado']]])
            ->callMountedAction()
            ->assertHasNoFormErrors()
            ->assertNotified('Factura C 00003-00000125 autorizada')
            ->assertActionHidden('issueInvoice')
            ->assertActionHidden('delete');

        $undoRepeaterFake();
        $invoice = $payment->invoice()->firstOrFail();
        $this->assertSame(InvoiceStatus::Authorized, $invoice->status);
        $this->assertSame([['Visita técnica a domicilio', 'Sábado', 45000.0]], $invoice->items->map(fn ($item) => [$item->description, $item->detail, $item->amount])->all());
    }

    public function test_reports_why_arca_rejected_the_invoice(): void
    {
        $this->actingAs(User::factory()->create());
        $this->enableInvoicing();
        $this->fakeArca([
            'FECompUltimoAutorizado' => [self::lastAuthorized(124)],
            'FECAESolicitar' => [self::rejected('10013: El documento del receptor es inválido')],
        ]);
        $payment = $this->pay(Client::factory()->create(['tax_condition' => TaxCondition::ConsumidorFinal]), 1000);

        Livewire::test(ViewPayment::class, ['record' => $payment->getRouteKey()])
            ->callAction('issueInvoice')
            ->assertNotified('ARCA rechazó la factura')
            ->assertActionVisible('issueInvoice')
            ->assertActionHasLabel('issueInvoice', 'Reintentar factura');
    }

    public function test_explains_what_to_fix_before_invoicing(): void
    {
        $this->actingAs(User::factory()->create());
        $this->enableInvoicing();
        $payment = $this->pay(Client::factory()->create(['business_name' => 'Ferretería Los Pinos', 'tax_condition' => TaxCondition::ResponsableInscripto]), 1000);

        Livewire::test(ViewPayment::class, ['record' => $payment->getRouteKey()])
            ->callAction('issueInvoice')
            ->assertNotified('No se puede facturar');

        $this->assertNull($payment->invoice()->first());
    }

    public function test_offers_no_invoice_while_invoicing_is_off(): void
    {
        $this->actingAs(User::factory()->create());
        $payment = $this->pay(Client::factory()->create(), 1000);

        Livewire::test(ViewPayment::class, ['record' => $payment->getRouteKey()])
            ->assertActionHidden('issueInvoice')
            ->assertActionVisible('delete');
    }

    public function test_sends_the_invoice_by_mail_with_its_pdf(): void
    {
        $this->actingAs(User::factory()->create());
        $this->enableInvoicing();
        Mail::fake();
        $payment = $this->pay(Client::factory()->create(['email' => 'cliente@example.com']), 85000);
        $invoice = $this->invoiced($payment);

        Livewire::test(ViewPayment::class, ['record' => $payment->getRouteKey()])
            ->assertActionHasUrl('viewInvoice', InvoiceResource::getUrl('view', ['record' => $invoice]));

        Livewire::test(ViewInvoice::class, ['record' => $invoice->getRouteKey()])
            ->callAction('sendByMail')
            ->assertNotified('Enviada por mail');

        Mail::assertSent(InvoiceMail::class, fn (InvoiceMail $mail) => $mail->hasTo('cliente@example.com')
            && $mail->invoice->is($invoice)
            && collect($mail->attachments())->contains(fn (Attachment $attachment) => $attachment->as === 'Factura-C-00003-00000125.pdf' && $attachment->mime === 'application/pdf'));
    }

    public function test_the_client_opens_an_authorized_invoice_with_the_signed_link(): void
    {
        $payment = $this->pay(Client::factory()->create(), 1000);
        $invoice = $this->invoiced($payment);
        $pending = Invoice::factory()->create();

        $this->get($invoice->publicPdfUrl())
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
        $this->get('http://'.config('app.domain').'/factura/'.$invoice->id)->assertForbidden();
        $this->get($pending->publicPdfUrl())->assertNotFound();
    }

    public function test_the_pdf_carries_the_fiscal_data_arca_requires(): void
    {
        $this->enableInvoicing([
            'invoicing.legal_name' => 'Juan Pérez',
            'invoicing.gross_income' => 'Exento',
            'invoicing.activity_start' => '2019-03-01',
        ]);
        $invoice = $this->invoiced($this->pay(Client::factory()->create(), 85000), [
            'issued_on' => '2026-11-02',
            'cae' => '76443213456789',
            'cae_due_on' => '2026-11-12',
        ]);

        $html = view('pdf.invoice', [
            'invoice' => $invoice,
            'company' => Settings::company(),
            'issuer' => Settings::invoicing(),
            'qr' => InvoiceQr::dataUri($invoice),
        ])->render();

        foreach (['COD. 11', '00003-00000125', '02/11/2026', '20-30405060-9', '01/03/2019', 'Juan Pérez', 'Responsable Monotributo',
            'Estudio Contable Ríos SRL', 'Abono soporte IT', '$ 85.000,00', '76443213456789', '12/11/2026', 'data:image/png;base64,',
            'HOMOLOGACIÓN · SIN VALIDEZ FISCAL'] as $text) {
            $this->assertStringContainsString($text, $html);
        }
    }

    public function test_production_invoices_have_no_test_watermark(): void
    {
        $invoice = $this->invoiced($this->pay(Client::factory()->create(), 1000), ['environment' => 'produccion']);

        $html = view('pdf.invoice', [
            'invoice' => $invoice,
            'company' => Settings::company(),
            'issuer' => Settings::invoicing(),
            'qr' => InvoiceQr::dataUri($invoice),
        ])->render();

        $this->assertStringNotContainsString('HOMOLOGACIÓN', $html);
    }

    public function test_the_receipt_says_it_is_not_an_invoice_and_names_the_invoice(): void
    {
        $payment = $this->pay(Client::factory()->create(), 1000);

        $receipt = fn () => view('pdf.receipt', ['payment' => $payment->refresh(), 'company' => Settings::company()])->render();

        $this->assertStringContainsString('Documento no válido como factura.', $receipt());
        $this->assertStringNotContainsString('Facturado con', $receipt());

        $this->invoiced($payment);

        $this->assertStringContainsString('Facturado con la Factura C 00003-00000125.', $receipt());
    }

    public function test_lists_the_invoices_of_the_current_environment(): void
    {
        $this->actingAs(User::factory()->create());
        $test = $this->invoiced($this->pay(Client::factory()->create(), 1000));
        $real = Invoice::factory()->authorized()->create(['environment' => 'produccion']);

        Livewire::test(ListInvoices::class)
            ->assertCanSeeTableRecords([$test])
            ->assertCanNotSeeTableRecords([$real]);
    }
}
