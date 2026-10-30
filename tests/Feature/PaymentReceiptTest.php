<?php

namespace Tests\Feature;

use App\Enums\PaymentMethod;
use App\Filament\Resources\Charges\Pages\ListCharges;
use App\Filament\Resources\Payments\Pages\ViewPayment;
use App\Models\Charge;
use App\Models\Client;
use App\Models\Payment;
use App\Models\User;
use App\Support\AmountInWords;
use App\Support\ReceiptPdf;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PaymentReceiptTest extends TestCase
{
    use RefreshDatabase;

    private function pay(Client $client, float $amount, array $data = []): Payment
    {
        return Payment::register(['client_id' => $client->id, 'paid_on' => today(), 'amount' => $amount, 'method' => PaymentMethod::Transfer, ...$data]);
    }

    public function test_receipts_are_numbered_from_r_00001(): void
    {
        $client = Client::factory()->create();

        $this->assertSame('R-00001', $this->pay($client, 100)->code);
        $this->assertSame('R-00002', $this->pay($client, 100)->code);
    }

    public function test_writes_amounts_in_words(): void
    {
        $this->assertSame('Pesos sesenta mil con 00/100', AmountInWords::pesos(60000));
        $this->assertSame('Pesos mil doscientos cincuenta con 50/100', AmountInWords::pesos(1250.5));
        $this->assertSame('Pesos un millón ciento once mil setecientos setenta y nueve con 00/100', AmountInWords::pesos(1111779));
    }

    public function test_receipt_lists_what_the_payment_settled_and_the_credit_left(): void
    {
        $client = Client::factory()->create(['business_name' => 'Ferretería Los Pinos SRL']);
        Charge::factory()->for($client)->create(['concept' => 'Visita técnica', 'amount' => 45000, 'issued_on' => today()->subMonth()]);
        Charge::factory()->for($client)->create(['concept' => 'Abono noviembre', 'amount' => 85000]);
        $payment = $this->pay($client, 150000, ['reference' => 'OP 99881']);

        $html = view('pdf.receipt', ['payment' => $payment, 'company' => Settings::company()])->render();

        $this->assertStringContainsString('R-00001', $html);
        $this->assertStringContainsString('Ferretería Los Pinos SRL', $html);
        $this->assertStringContainsString('Visita técnica', $html);
        $this->assertStringContainsString('Abono noviembre', $html);
        $this->assertStringContainsString('A cuenta', $html);
        $this->assertStringContainsString('$ 20.000,00', $html);
        $this->assertStringContainsString('$ 150.000,00', $html);
        $this->assertStringContainsString('Transferencia', $html);
        $this->assertStringContainsString('OP 99881', $html);
        $this->assertStringContainsString('Pesos ciento cincuenta mil con 00/100', $html);
        $this->assertStringStartsWith('%PDF', ReceiptPdf::render($payment));
    }

    public function test_partial_payments_say_so(): void
    {
        $client = Client::factory()->create();
        Charge::factory()->for($client)->create(['amount' => 85000]);

        $html = view('pdf.receipt', ['payment' => $this->pay($client, 15000), 'company' => Settings::company()])->render();

        $this->assertStringContainsString('Pago parcial de $ 85.000,00', $html);
        $this->assertStringNotContainsString('A cuenta', $html);
    }

    public function test_the_client_opens_the_receipt_with_the_signed_link(): void
    {
        $payment = $this->pay(Client::factory()->create(), 1000);

        $this->get($payment->publicReceiptUrl())
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');

        $this->get('http://'.config('app.domain').'/recibo/'.$payment->id)->assertForbidden();
    }

    public function test_shares_and_downloads_the_receipt_from_the_payment(): void
    {
        $this->actingAs(User::factory()->create());
        $payment = $this->pay(Client::factory()->create(['phone' => '011 2345-6789']), 60000);

        $component = Livewire::test(ViewPayment::class, ['record' => $payment->getRouteKey()])
            ->assertActionShouldOpenUrlInNewTab('shareReceiptOnWhatsApp');

        $url = $component->instance()->getAction('shareReceiptOnWhatsApp')->getUrl();
        $this->assertStringStartsWith('https://wa.me/5491123456789?text=', $url);
        $text = rawurldecode(substr($url, strpos($url, '=') + 1));
        $this->assertStringContainsString('recibo R-00001', $text);
        $this->assertStringContainsString('$ 60.000,00', $text);
        $this->assertStringContainsString($payment->publicReceiptUrl(), $text);

        $component->callAction('downloadReceipt')
            ->assertFileDownloaded('Recibo-R-00001.pdf');
    }

    public function test_registering_a_payment_offers_the_receipt(): void
    {
        $this->actingAs(User::factory()->create());
        $charge = Charge::factory()->create(['amount' => 1000]);

        Livewire::test(ListCharges::class)
            ->callTableAction('registerPayment', $charge, data: ['method' => PaymentMethod::Cash->value])
            ->assertNotified('Pago registrado — recibo R-00001');
    }
}
