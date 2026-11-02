<?php

namespace Tests\Feature;

use App\Enums\ChargeStatus;
use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Enums\PaymentMethod;
use App\Enums\TaxCondition;
use App\Filament\Resources\Charges\Pages\ViewCharge;
use App\Filament\Resources\Invoices\Pages\ViewInvoice;
use App\Models\Charge;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use App\Support\Arca\InvoiceQr;
use App\Support\InvoicePdf;
use App\Support\Invoicing\InvoiceIssuer;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use LogicException;
use Tests\Concerns\FakesArca;
use Tests\TestCase;

class CreditNoteTest extends TestCase
{
    use FakesArca, RefreshDatabase;

    /**
     * A charge invoiced before payment through the real flow.
     */
    private function invoicedCharge(): Charge
    {
        $this->fakeArca([
            'FECompUltimoAutorizado' => [self::lastAuthorized(124)],
            'FECAESolicitar' => [self::approved('71111111111111')],
        ]);
        $charge = Charge::factory()->for(Client::factory()->create(['tax_condition' => TaxCondition::ConsumidorFinal]))->create(['amount' => 85000]);
        app(InvoiceIssuer::class)->issueForCharge($charge);

        return $charge;
    }

    /**
     * ARCA's answers for the credit note that follows.
     */
    private function arcaAnswersTheNote(string $answer): void
    {
        $this->fakeArca([
            'FECompUltimoAutorizado' => [self::lastAuthorized(0)],
            'FECAESolicitar' => [$answer],
        ]);
    }

    public function test_annuls_an_invoice_with_a_credit_note_that_names_it(): void
    {
        $this->travelTo('2026-11-02 10:00');
        $this->enableInvoicing();
        $invoice = $this->invoicedCharge()->invoice;
        $this->arcaAnswersTheNote(self::approved('72222222222222'));

        $note = app(InvoiceIssuer::class)->creditNote($invoice);

        $this->assertSame(InvoiceStatus::Authorized, $note->status);
        $this->assertSame(InvoiceType::NotaCreditoC, $note->type);
        $this->assertSame('Nota de crédito C 00003-00000001', $note->title);
        $this->assertSame(85000.0, $note->total);
        $this->assertTrue($invoice->refresh()->isCredited());
        // The invoice's own requests come first; the note's are the last ones.
        $this->assertStringContainsString('<CbteTipo>13</CbteTipo>', $this->arcaRequests('FECompUltimoAutorizado')[1]);
        $request = $this->arcaRequests('FECAESolicitar')[1];
        $this->assertStringContainsString('<PtoVta>3</PtoVta><CbteTipo>13</CbteTipo>', $request);
        $this->assertStringContainsString('<CbtesAsoc><CbteAsoc><Tipo>11</Tipo><PtoVta>3</PtoVta><Nro>125</Nro><Cuit>20304050609</Cuit><CbteFch>20261102</CbteFch></CbteAsoc></CbtesAsoc>', $request);
    }

    public function test_an_annulled_invoice_leaves_its_charge_to_invoice_again(): void
    {
        $this->enableInvoicing();
        $charge = $this->invoicedCharge();
        $this->arcaAnswersTheNote(self::approved());

        app(InvoiceIssuer::class)->creditNote($charge->invoice);

        $this->assertSame(85000.0, $charge->pendingToInvoice());
        $this->assertCount(0, $charge->invoicesInForce());
    }

    public function test_cancelling_an_invoiced_charge_issues_the_credit_note_first(): void
    {
        $this->actingAs(User::factory()->create());
        $this->enableInvoicing();
        $charge = $this->invoicedCharge();
        $this->arcaAnswersTheNote(self::approved());

        Livewire::test(ViewCharge::class, ['record' => $charge->getRouteKey()])
            ->callAction('cancel')
            ->assertNotified('Cargo anulado');

        $this->assertSame(ChargeStatus::Cancelled, $charge->refresh()->status);
        $this->assertTrue($charge->invoice->isCredited());
    }

    public function test_the_charge_stays_when_arca_rejects_the_credit_note(): void
    {
        $this->actingAs(User::factory()->create());
        $this->enableInvoicing();
        $charge = $this->invoicedCharge();
        $this->arcaAnswersTheNote(self::rejected('10197: El comprobante asociado no existe'));

        Livewire::test(ViewCharge::class, ['record' => $charge->getRouteKey()])
            ->callAction('cancel')
            ->assertNotified('ARCA rechazó la nota de crédito');

        $this->assertSame(ChargeStatus::Pending, $charge->refresh()->status);
        $this->assertFalse($charge->invoice->isCredited());
    }

    public function test_a_charge_invoiced_with_its_payment_is_annulled_from_that_invoice(): void
    {
        $this->actingAs(User::factory()->create());
        $this->enableInvoicing();
        $this->fakeArca([
            'FECompUltimoAutorizado' => [self::lastAuthorized(0)],
            'FECAESolicitar' => [self::approved()],
        ]);
        $charge = Charge::factory()->for(Client::factory()->create(['tax_condition' => TaxCondition::ConsumidorFinal]))->create(['amount' => 1000]);
        $payment = Payment::register(['client_id' => $charge->client_id, 'paid_on' => today(), 'amount' => 1000, 'method' => PaymentMethod::Cash], [$charge->id]);
        app(InvoiceIssuer::class)->issueForPayment($payment);

        Livewire::test(ViewCharge::class, ['record' => $charge->getRouteKey()])
            ->callAction('cancel')
            ->assertNotified('No se puede anular');

        $this->assertSame(ChargeStatus::Paid, $charge->refresh()->status);
        $this->expectException(LogicException::class);
        $charge->cancel();
    }

    public function test_a_payment_can_be_deleted_once_its_invoice_is_annulled(): void
    {
        $payment = Payment::register(['client_id' => Client::factory()->create()->id, 'paid_on' => today(), 'amount' => 1000, 'method' => PaymentMethod::Cash]);
        $invoice = Invoice::factory()->authorized()->for($payment)->create(['client_id' => $payment->client_id]);
        Invoice::factory()->authorized()->create(['type' => InvoiceType::NotaCreditoC, 'credited_invoice_id' => $invoice->id]);

        $payment->delete();

        $this->assertModelMissing($payment);
    }

    public function test_annuls_from_the_invoice_page(): void
    {
        $this->actingAs(User::factory()->create());
        $this->enableInvoicing();
        $invoice = $this->invoicedCharge()->invoice;
        $this->arcaAnswersTheNote(self::approved());

        Livewire::test(ViewInvoice::class, ['record' => $invoice->getRouteKey()])
            ->callAction('creditNote')
            ->assertNotified('Nota de crédito C 00003-00000001 autorizada')
            ->assertActionHidden('creditNote');
    }

    public function test_the_credit_note_pdf_names_the_invoice_it_annuls(): void
    {
        $this->enableInvoicing();
        $invoice = $this->invoicedCharge()->invoice;
        $this->arcaAnswersTheNote(self::approved());
        $note = app(InvoiceIssuer::class)->creditNote($invoice);

        $html = view('pdf.invoice', [
            'invoice' => $note,
            'company' => Settings::company(),
            'issuer' => Settings::invoicing(),
            'qr' => InvoiceQr::dataUri($note),
        ])->render();

        $this->assertStringContainsString('NOTA DE CRÉDITO ELECTRÓNICA', $html);
        $this->assertStringContainsString('COD. 13', $html);
        $this->assertStringContainsString("Anula la {$invoice->title}", $html);
        $this->assertSame('Nota-de-credito-C-00003-00000001.pdf', InvoicePdf::filename($note));
        $this->assertSame(13, InvoiceQr::payload($note)['tipoCmp']);
    }
}
