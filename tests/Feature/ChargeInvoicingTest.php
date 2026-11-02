<?php

namespace Tests\Feature;

use App\Enums\ChargeStatus;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Enums\SubscriptionType;
use App\Enums\TaxCondition;
use App\Filament\Resources\Charges\Pages\EditCharge;
use App\Filament\Resources\Charges\Pages\ListCharges;
use App\Filament\Resources\Charges\Pages\ViewCharge;
use App\Filament\Resources\Payments\Pages\ViewPayment;
use App\Filament\Widgets\BusinessOverview;
use App\Models\Charge;
use App\Models\Client;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\User;
use App\Support\Invoicing\InvoiceDraft;
use App\Support\Invoicing\InvoiceIssuer;
use App\Support\Invoicing\InvoicingException;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\FakesArca;
use Tests\TestCase;

class ChargeInvoicingTest extends TestCase
{
    use FakesArca, RefreshDatabase;

    private function client(): Client
    {
        return Client::factory()->create(['tax_condition' => TaxCondition::ConsumidorFinal]);
    }

    /**
     * The November charge of a fixed subscription whose client needs the invoice to pay.
     */
    private function subscriptionCharge(float $amount = 85000): Charge
    {
        $subscription = Subscription::factory()->for($this->client())->invoicedBeforePayment()->create([
            'type' => SubscriptionType::Fixed,
            'amount' => $amount,
            'billing_day' => 1,
            'starts_on' => '2026-01-01',
        ]);

        return $subscription->bill(today());
    }

    private function pay(Charge $charge, float $amount): Payment
    {
        return Payment::register(['client_id' => $charge->client_id, 'paid_on' => today(), 'amount' => $amount, 'method' => PaymentMethod::Transfer], [$charge->id]);
    }

    private function approveNext(int $last = 0): void
    {
        $this->fakeArca([
            'FECompUltimoAutorizado' => [self::lastAuthorized($last)],
            'FECAESolicitar' => [self::approved()],
        ]);
    }

    public function test_invoices_a_charge_before_payment_due_when_the_charge_is(): void
    {
        $this->travelTo('2026-11-02 10:00');
        $this->enableInvoicing();
        $this->approveNext();
        $charge = $this->subscriptionCharge();

        $invoice = app(InvoiceIssuer::class)->issueForCharge($charge);

        $this->assertSame(InvoiceStatus::Authorized, $invoice->status);
        $this->assertSame($charge->id, $invoice->charge_id);
        $this->assertSame([$charge->id], $invoice->items->pluck('charge_id')->all());
        $request = $this->arcaRequests('FECAESolicitar')[0];
        $this->assertStringContainsString('<CbteFch>20261102</CbteFch><ImpTotal>85000.00</ImpTotal>', $request);
        $this->assertStringContainsString('<FchServDesde>20261101</FchServDesde><FchServHasta>20261130</FchServHasta><FchVtoPago>20261112</FchVtoPago>', $request);
    }

    public function test_an_overdue_charge_is_due_on_the_invoice_date(): void
    {
        $this->travelTo('2026-11-20 10:00');
        $this->enableInvoicing();
        $this->approveNext();
        $charge = Charge::factory()->for($this->client())->create(['amount' => 1000, 'issued_on' => '2026-11-01', 'due_on' => '2026-11-11']);

        app(InvoiceIssuer::class)->issueForCharge($charge);

        $this->assertStringContainsString('<FchVtoPago>20261120</FchVtoPago>', $this->arcaRequests('FECAESolicitar')[0]);
    }

    public function test_the_payment_of_an_invoiced_charge_is_not_invoiced_again(): void
    {
        $this->actingAs(User::factory()->create());
        $this->enableInvoicing();
        $this->approveNext();
        $charge = $this->subscriptionCharge();
        $invoice = app(InvoiceIssuer::class)->issueForCharge($charge);

        $payment = $this->pay($charge, 85000);

        $this->assertSame(0.0, InvoiceDraft::forPayment($payment)['total']);
        Livewire::test(ViewPayment::class, ['record' => $payment->getRouteKey()])
            ->assertActionHidden('issueInvoice');
        $receipt = view('pdf.receipt', ['payment' => $payment, 'company' => Settings::company()])->render();
        $this->assertStringContainsString("Facturado con la {$invoice->title}.", $receipt);

        $this->expectException(InvoicingException::class);
        $this->expectExceptionMessage('ya está facturado');

        app(InvoiceIssuer::class)->issueForPayment($payment);
    }

    public function test_a_charge_invoiced_partly_from_a_receipt_is_invoiced_for_the_rest(): void
    {
        $this->enableInvoicing();
        $this->fakeArca([
            'FECompUltimoAutorizado' => [self::lastAuthorized(0), self::lastAuthorized(1)],
            'FECAESolicitar' => [self::approved(), self::approved()],
        ]);
        $charge = Charge::factory()->for($this->client())->create(['amount' => 100000]);
        app(InvoiceIssuer::class)->issueForPayment($this->pay($charge, 40000));

        $draft = InvoiceDraft::forCharge($charge);
        $rest = app(InvoiceIssuer::class)->issueForCharge($charge);

        $this->assertSame('Saldo de $ 100.000,00', $draft['items'][0]['detail']);
        $this->assertSame(60000.0, $rest->total);
        $this->assertSame(0.0, InvoiceDraft::forPayment($this->pay($charge, 60000))['total']);
    }

    public function test_credit_left_on_account_and_invoiced_counts_for_the_charge_it_pays_later(): void
    {
        $this->enableInvoicing();
        $this->approveNext();
        $client = $this->client();
        $advance = Payment::register(['client_id' => $client->id, 'paid_on' => today(), 'amount' => 50000, 'method' => PaymentMethod::Cash]);
        app(InvoiceIssuer::class)->issueForPayment($advance);

        $charge = Charge::factory()->for($client)->create(['amount' => 50000]);

        $this->assertSame(ChargeStatus::Paid, $charge->refresh()->status);
        $this->assertSame(0.0, $charge->pendingToInvoice());
    }

    public function test_lists_the_charges_waiting_for_their_invoice(): void
    {
        $this->actingAs(User::factory()->create());
        $this->enableInvoicing();
        $waiting = $this->subscriptionCharge();
        $regular = Charge::factory()->for($this->client())->create();
        $variable = Subscription::factory()->for($this->client())->invoicedBeforePayment()->create([
            'type' => SubscriptionType::Variable, 'billing_day' => 1, 'starts_on' => '2026-01-01',
        ])->bill(today());

        $this->assertSame([$waiting->id], Charge::awaitingInvoice()->modelKeys());
        $this->assertSame(ChargeStatus::Draft, $variable->status);
        Livewire::test(ListCharges::class)
            ->filterTable('to_invoice')
            ->assertCanSeeTableRecords([$waiting])
            ->assertCanNotSeeTableRecords([$regular, $variable]);
        Livewire::test(BusinessOverview::class)->assertSee('Para facturar');

        $this->approveNext();
        app(InvoiceIssuer::class)->issueForCharge($waiting);

        $this->assertSame([], Charge::awaitingInvoice()->modelKeys());
    }

    public function test_a_draft_charge_is_confirmed_before_invoicing(): void
    {
        $this->enableInvoicing();
        $draft = Charge::factory()->for($this->client())->create(['status' => ChargeStatus::Draft]);

        $this->expectException(InvoicingException::class);
        $this->expectExceptionMessage('Confirmá el monto');

        app(InvoiceIssuer::class)->issueForCharge($draft);
    }

    public function test_invoices_the_charge_from_its_page_and_locks_its_amount(): void
    {
        $this->actingAs(User::factory()->create());
        $this->enableInvoicing();
        $this->approveNext();
        $charge = $this->subscriptionCharge();

        Livewire::test(ViewCharge::class, ['record' => $charge->getRouteKey()])
            ->callAction('issueInvoice')
            ->assertNotified('Factura C 00003-00000001 autorizada')
            ->assertActionHidden('issueInvoice')
            ->assertActionVisible('viewInvoice');

        Livewire::test(EditCharge::class, ['record' => $charge->getRouteKey()])
            ->assertFormFieldDisabled('amount')
            ->assertFormFieldDisabled('concept');
    }
}
