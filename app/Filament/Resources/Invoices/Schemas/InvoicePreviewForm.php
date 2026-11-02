<?php

namespace App\Filament\Resources\Invoices\Schemas;

use App\Enums\InvoiceConcept;
use App\Models\Client;
use App\Models\Invoice;
use App\Support\QuotePdf;
use Carbon\CarbonInterface;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Utilities\Get;

/**
 * Preview shown before asking ARCA for the CAE, for a payment's invoice and
 * for a charge's. Amounts come from the draft and cannot be edited.
 */
class InvoicePreviewForm
{
    /**
     * @return array<int, mixed>
     */
    public static function fields(): array
    {
        return [
            ToggleButtons::make('concept')
                ->label('Concepto')
                ->options(InvoiceConcept::class)
                ->inline()
                ->required()
                ->live(),
            DatePicker::make('service_from')
                ->label('Período desde')
                ->visible(fn (Get $get) => self::conceptOf($get('concept'))->hasServicePeriod())
                ->required(),
            DatePicker::make('service_to')
                ->label('Período hasta')
                ->visible(fn (Get $get) => self::conceptOf($get('concept'))->hasServicePeriod())
                ->afterOrEqual('service_from')
                ->required(),
            Repeater::make('items')
                ->label('Detalle')
                ->addable(false)
                ->deletable(false)
                ->reorderable(false)
                ->columns(3)
                ->schema([
                    TextInput::make('description')
                        ->label('Concepto')
                        ->required()
                        ->maxLength(255),
                    TextInput::make('detail')
                        ->label('Aclaración')
                        ->maxLength(255),
                    TextInput::make('amount')
                        ->label('Importe')
                        ->prefix('$')
                        ->disabled()
                        ->dehydrated(false),
                ])
                ->columnSpanFull(),
        ];
    }

    /**
     * The draft, with what the previous attempt changed (concept, period, texts).
     *
     * @param  array{concept: InvoiceConcept, service_from: CarbonInterface, service_to: CarbonInterface, items: list<array{description: string, detail: ?string, amount: float}>}  $draft
     * @return array<string, mixed>
     */
    public static function fill(array $draft, ?Invoice $previous): array
    {
        $previous?->loadMissing('items');
        $items = $previous?->items->values();

        return [
            'concept' => $previous->concept ?? $draft['concept'],
            'service_from' => ($previous->service_from ?? $draft['service_from'])->toDateString(),
            'service_to' => ($previous->service_to ?? $draft['service_to'])->toDateString(),
            'items' => collect($draft['items'])
                ->map(fn (array $item, int $index) => [
                    'description' => $items?->get($index)->description ?? $item['description'],
                    'detail' => $items !== null && $items->has($index) ? $items->get($index)->detail : $item['detail'],
                    'amount' => number_format($item['amount'], 2, ',', '.'),
                ])
                ->all(),
        ];
    }

    public static function description(Client $client, float $total, ?Invoice $previous, ?string $extra = null): string
    {
        $document = match (true) {
            filled($client->cuit) => "CUIT {$client->cuit}",
            filled($client->dni) => "DNI {$client->dni}",
            default => 'consumidor final sin identificar',
        };

        return collect([
            "Para {$client->business_name} ({$document}) por ".QuotePdf::money($total).'.',
            $extra,
            $previous?->isAwaitingArca() ? 'ARCA no respondió el pedido anterior: primero se consulta si llegó a autorizarla.' : null,
            config('arca.environment') === 'produccion' ? null : 'Ambiente de prueba (homologación): la factura no tiene validez fiscal.',
        ])->filter()->join(' ');
    }

    private static function conceptOf(mixed $value): InvoiceConcept
    {
        return $value instanceof InvoiceConcept ? $value : (InvoiceConcept::tryFrom((int) $value) ?? InvoiceConcept::Services);
    }
}
