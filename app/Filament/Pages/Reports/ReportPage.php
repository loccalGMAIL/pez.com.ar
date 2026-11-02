<?php

namespace App\Filament\Pages\Reports;

use App\Models\Client;
use App\Support\ReportPdf;
use App\Support\Reports\Period;
use App\Support\Reports\Report;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Pages\Page;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Symfony\Component\HttpFoundation\StreamedResponse;
use UnitEnum;

/**
 * Screen of one report: period and client filters on top, the figures
 * below and a button to download the same thing as a PDF.
 *
 * @property-read Schema $form
 */
abstract class ReportPage extends Page
{
    protected string $view = 'filament.pages.report';

    protected static string|UnitEnum|null $navigationGroup = 'Informes';

    /**
     * @var array<string, mixed>|null
     */
    public ?array $filters = [];

    /**
     * @return class-string<Report>
     */
    abstract public static function reportClass(): string;

    public static function getNavigationLabel(): string
    {
        return static::reportClass()::title();
    }

    public function getTitle(): string
    {
        return static::reportClass()::title();
    }

    public function mount(): void
    {
        $this->form->fill([
            'preset' => 'this_month',
            'from' => today()->startOfMonth()->toDateString(),
            'to' => today()->toDateString(),
            'client_id' => null,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        $usesPeriod = static::reportClass()::usesPeriod();

        return $schema
            ->statePath('filters')
            ->columns(['default' => 1, 'sm' => 2, 'lg' => 4])
            ->components([
                Select::make('preset')
                    ->label('Período')
                    ->options(Period::PRESETS)
                    ->selectablePlaceholder(false)
                    ->live()
                    ->visible($usesPeriod),
                DatePicker::make('from')
                    ->label('Desde')
                    ->live()
                    ->visible(fn (Get $get) => $usesPeriod && $get('preset') === 'custom'),
                DatePicker::make('to')
                    ->label('Hasta')
                    ->live()
                    ->visible(fn (Get $get) => $usesPeriod && $get('preset') === 'custom'),
                Select::make('client_id')
                    ->label('Cliente')
                    ->placeholder('Todos')
                    ->options(fn () => Client::withTrashed()->orderBy('business_name')->pluck('business_name', 'id'))
                    ->searchable()
                    ->live(),
            ]);
    }

    public function report(): Report
    {
        $filters = $this->filters ?? [];
        $class = static::reportClass();

        return new $class(
            Period::preset((string) ($filters['preset'] ?? 'this_month'), $filters['from'] ?? null, $filters['to'] ?? null),
            filled($filters['client_id'] ?? null) ? Client::withTrashed()->find($filters['client_id']) : null,
        );
    }

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('downloadPdf')
                ->label('Descargar PDF')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->action(function (): StreamedResponse {
                    $report = $this->report();

                    return response()->streamDownload(
                        fn () => print (ReportPdf::render($report)),
                        ReportPdf::filename($report),
                        ['Content-Type' => 'application/pdf'],
                    );
                }),
        ];
    }
}
