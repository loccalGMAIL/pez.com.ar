<?php

namespace Tests\Feature;

use App\Enums\ChargeStatus;
use App\Enums\ExternalRepairStatus;
use App\Enums\QuoteStatus;
use App\Enums\WorkOrderStatus;
use App\Filament\Pages\Reports\ClientsReportPage;
use App\Filament\Pages\Reports\DebtReportPage;
use App\Filament\Pages\Reports\IncomeReportPage;
use App\Filament\Pages\Reports\MarginReportPage;
use App\Filament\Pages\Reports\QuotesReportPage;
use App\Filament\Pages\Reports\ReportPage;
use App\Filament\Pages\Reports\SubscriptionsReportPage;
use App\Filament\Pages\Reports\WorkshopReportPage;
use App\Models\Charge;
use App\Models\Client;
use App\Models\ExternalRepair;
use App\Models\Payment;
use App\Models\Quote;
use App\Models\Subscription;
use App\Models\Supplier;
use App\Models\User;
use App\Models\WorkOrder;
use App\Support\Reports\ClientsReport;
use App\Support\Reports\DebtReport;
use App\Support\Reports\IncomeReport;
use App\Support\Reports\MarginReport;
use App\Support\Reports\Period;
use App\Support\Reports\QuotesReport;
use App\Support\Reports\SubscriptionsReport;
use App\Support\Reports\WorkshopReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ReportsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-10-20 12:00');
    }

    private function october(): Period
    {
        return new Period('2026-10-01', '2026-10-31');
    }

    /**
     * @param  array<int>  $chargeIds
     */
    private function pay(Client $client, float $amount, string $on = '2026-10-10', array $chargeIds = [], string $method = 'efectivo'): Payment
    {
        return Payment::register(['client_id' => $client->id, 'paid_on' => $on, 'amount' => $amount, 'method' => $method], $chargeIds);
    }

    public function test_periods_cover_whole_days_and_read_as_months(): void
    {
        $this->assertSame('Octubre 2026', $this->october()->label());
        $this->assertSame('Septiembre 2026', Period::preset('last_month')->label());
        $this->assertSame('Año 2026', Period::preset('this_year')->label());
        $this->assertSame('05/10/2026 al 12/10/2026', Period::preset('custom', '2026-10-12', '2026-10-05')->label());
        $this->assertSame('2026-10-31 23:59:59', $this->october()->bounds()[1]->toDateTimeString());
    }

    public function test_income_counts_payments_by_their_date(): void
    {
        $client = Client::factory()->create();
        $this->pay($client, 1000, '2026-10-01', method: 'efectivo');
        $this->pay($client, 3000, '2026-10-31', method: 'transferencia');
        $this->pay($client, 500, '2026-09-30');
        $this->pay(Client::factory()->create(), 2000, '2026-10-15', method: 'transferencia');

        $report = new IncomeReport($this->october());

        $this->assertSame(6000.0, $report->metric('Cobrado'));
        $this->assertSame(3, $report->metric('Pagos'));
        $this->assertSame(['Transferencia', 'Efectivo'], $report->table('Por medio de pago')->pluck('method'));
        $this->assertSame([5000.0, 1000.0], $report->table('Por medio de pago')->pluck('amount'));
        $this->assertSame(4000.0, (new IncomeReport($this->october(), $client))->metric('Cobrado'));
    }

    public function test_margin_takes_out_the_cost_of_the_part_of_each_resale_that_was_paid(): void
    {
        $client = Client::factory()->create();
        $resale = Charge::factory()->for($client)->resale(600)->create(['amount' => 1000, 'issued_on' => '2026-10-01']);
        $service = Charge::factory()->for($client)->create(['amount' => 2000, 'issued_on' => '2026-10-02']);
        $pending = Charge::factory()->for($client)->resale()->create(['amount' => 400, 'issued_on' => '2026-10-03']);

        $this->pay($client, 500, '2026-10-05', [$resale->id]);
        $this->pay($client, 2000, '2026-10-06', [$service->id]);
        $this->pay($client, 400, '2026-10-07', [$pending->id]);
        $this->pay(Client::factory()->create(), 300, '2026-10-08');

        $report = new MarginReport($this->october());

        $this->assertSame(3200.0, $report->metric('Cobrado'));
        $this->assertSame(300.0, $report->metric('Costo de lo revendido'));
        $this->assertSame(2900.0, $report->metric('Percibido'));
        $this->assertSame(300.0, $report->metric('A cuenta'));
        $this->assertSame(1, $report->metric('Cobros sin costo cargado'));
        $this->assertSame([500.0, 400.0], $report->table('Ventas con costo')->pluck('collected'));
        $this->assertSame([200.0, null], $report->table('Ventas con costo')->pluck('kept'));
    }

    public function test_debt_is_grouped_by_days_overdue(): void
    {
        $client = Client::factory()->create(['business_name' => 'Deudor']);
        Charge::factory()->for($client)->create(['amount' => 100, 'issued_on' => '2026-10-01', 'due_on' => '2026-10-25']);
        Charge::factory()->for($client)->create(['amount' => 200, 'issued_on' => '2026-09-01', 'due_on' => '2026-10-10']);
        Charge::factory()->for($client)->create(['amount' => 300, 'issued_on' => '2026-06-01', 'due_on' => '2026-07-01']);
        Charge::factory()->for($client)->create(['amount' => 999, 'status' => ChargeStatus::Draft]);
        $partial = Charge::factory()->for($client)->create(['amount' => 1000, 'issued_on' => '2026-08-01', 'due_on' => '2026-08-15']);
        $this->pay($client, 400, chargeIds: [$partial->id]);

        $report = new DebtReport($this->october());
        $row = $report->table('Por cliente')->rows[0];

        $this->assertFalse(DebtReport::usesPeriod());
        $this->assertSame(1200.0, $report->metric('Por cobrar'));
        $this->assertSame(1100.0, $report->metric('Vencido'));
        $this->assertSame(['client' => 'Deudor', 'current' => 100.0, 'd30' => 200.0, 'd60' => 0.0, 'd90' => 600.0, 'older' => 300.0, 'total' => 1200.0], $row);
        $this->assertSame([111, 66, 10], $report->table('Cargos vencidos')->pluck('days'));
    }

    public function test_clients_are_ranked_by_what_they_paid(): void
    {
        $small = Client::factory()->create(['business_name' => 'Chico']);
        $big = Client::factory()->create(['business_name' => 'Grande']);
        Charge::factory()->for($big)->create(['amount' => 5000, 'issued_on' => '2026-10-02']);
        Charge::factory()->for($big)->create(['amount' => 700, 'issued_on' => '2026-10-03', 'status' => ChargeStatus::Draft]);
        $this->pay($big, 3000);
        $this->pay($small, 1000);

        $report = new ClientsReport($this->october());
        $ranking = $report->table('Ranking');

        $this->assertSame(['Grande', 'Chico'], $ranking->pluck('client'));
        $this->assertSame([5000.0, 0.0], $ranking->pluck('billed'));
        $this->assertSame([75.0, 25.0], $ranking->pluck('share'));
        $this->assertSame([2000.0, 0.0], $ranking->pluck('owed'));
        $this->assertSame('Grande', $report->metric('Principal cliente'));
    }

    public function test_subscriptions_show_what_they_collected_and_their_movements(): void
    {
        $client = Client::factory()->create();
        $current = Subscription::factory()->for($client)->create(['amount' => 80000]);
        Subscription::factory()->for($client)->create(['concept' => 'Nuevo', 'amount' => 20000, 'starts_on' => '2026-10-05']);
        Subscription::factory()->for($client)->variable()->create(['concept' => 'Terminado', 'ends_on' => '2026-10-15']);
        Subscription::factory()->for($client)->create(['concept' => 'Viejo', 'starts_on' => '2025-01-01', 'ends_on' => '2026-08-31']);
        $charge = Charge::factory()->for($client)->create(['subscription_id' => $current->id, 'amount' => 80000, 'issued_on' => '2026-10-01']);
        $this->pay($client, 50000, chargeIds: [$charge->id]);

        $report = new SubscriptionsReport($this->october());

        $this->assertSame(3, $report->metric('Abonos vigentes'));
        $this->assertSame(100000.0, $report->metric('Mensual de abonos fijos'));
        $this->assertSame(50000.0, $report->metric('Cobrado de abonos'));
        $this->assertSame(30000.0, $report->metric('Adeudado de abonos'));
        $this->assertSame(['Alta', 'Baja'], $report->table('Altas y bajas')->pluck('movement'));
        $this->assertSame(['Nuevo', 'Terminado'], $report->table('Altas y bajas')->pluck('concept'));
    }

    public function test_workshop_measures_orders_and_external_services(): void
    {
        $finished = WorkOrder::factory()->status(WorkOrderStatus::Finished)->create(['opened_on' => '2026-10-01', 'hours' => 2.5]);
        $finished->forceFill(['finished_at' => '2026-10-11 18:00'])->saveQuietly();
        $open = WorkOrder::factory()->create(['opened_on' => '2026-10-15']);
        $supplier = Supplier::factory()->create(['name' => 'Service SA']);
        ExternalRepair::factory()->for($finished)->for($supplier)->create([
            'status' => ExternalRepairStatus::PickedUp, 'sent_on' => '2026-10-02', 'returned_on' => '2026-10-08', 'cost' => 1000, 'price' => 1500,
        ]);
        ExternalRepair::factory()->for($open)->for($supplier)->create(['sent_on' => '2026-10-16', 'cost' => 2000, 'price' => 2600]);

        $report = new WorkshopReport($this->october());

        $this->assertSame(2, $report->metric('Órdenes ingresadas'));
        $this->assertSame(1, $report->metric('Órdenes terminadas'));
        $this->assertSame(2.5, $report->metric('Horas cargadas'));
        $this->assertSame(10.0, $report->metric('Resolución promedio'));
        $this->assertSame(1, $report->metric('En service externo hoy'));
        $this->assertSame([['supplier' => 'Service SA', 'count' => 2, 'days' => 6.0, 'cost' => 3000.0, 'price' => 4100.0, 'difference' => 1100.0]], $report->table('Service externo por proveedor')->rows);
        $this->assertSame([4], $report->table('Equipos en service externo hoy')->pluck('days'));
    }

    public function test_quotes_acceptance_leaves_out_drafts_and_unanswered_ones(): void
    {
        foreach ([QuoteStatus::Accepted, QuoteStatus::Rejected, QuoteStatus::Sent, QuoteStatus::Draft] as $status) {
            Quote::factory()->status($status)->create(['issue_date' => '2026-10-05'])->forceFill(['total' => 1000])->saveQuietly();
        }

        $report = new QuotesReport($this->october());

        $this->assertSame(3, $report->metric('Presupuestos emitidos'));
        $this->assertSame(50.0, $report->metric('Tasa de aceptación'));
        $this->assertSame(3000.0, $report->metric('Monto presupuestado'));
        $this->assertSame(1000.0, $report->metric('Monto aceptado'));
    }

    /**
     * @return array<string, array{class-string}>
     */
    public static function pages(): array
    {
        return [
            'ingresos' => [IncomeReportPage::class],
            'margen' => [MarginReportPage::class],
            'deuda' => [DebtReportPage::class],
            'clientes' => [ClientsReportPage::class],
            'abonos' => [SubscriptionsReportPage::class],
            'taller' => [WorkshopReportPage::class],
            'presupuestos' => [QuotesReportPage::class],
        ];
    }

    /**
     * @param  class-string<ReportPage>  $page
     */
    #[DataProvider('pages')]
    public function test_each_report_renders_and_downloads_as_pdf(string $page): void
    {
        $this->actingAs(User::factory()->create());
        $client = Client::factory()->create(['business_name' => 'Cliente Informe']);
        Charge::factory()->for($client)->create(['amount' => 1000, 'issued_on' => '2026-08-01']);
        $this->pay($client, 600);

        Livewire::test($page)
            ->assertOk()
            ->assertSee($page::reportClass()::title())
            ->callAction('downloadPdf')
            ->assertFileDownloaded();
    }

    public function test_filters_change_the_figures_on_screen(): void
    {
        $this->actingAs(User::factory()->create());
        $client = Client::factory()->create();
        $this->pay($client, 1234, '2026-10-05');
        $this->pay(Client::factory()->create(), 4321, '2026-09-05');

        Livewire::test(IncomeReportPage::class)
            ->assertSee('$ 1.234,00')
            ->assertDontSee('$ 4.321,00')
            ->set('filters.preset', 'last_month')
            ->assertSee('$ 4.321,00')
            ->assertDontSee('$ 1.234,00')
            ->set('filters.preset', 'this_year')
            ->set('filters.client_id', $client->id)
            ->assertSee('$ 1.234,00')
            ->assertDontSee('$ 4.321,00');
    }
}
