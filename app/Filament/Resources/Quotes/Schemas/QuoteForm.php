<?php

namespace App\Filament\Resources\Quotes\Schemas;

use App\Models\Quote;
use App\Models\ServiceItem;
use App\Support\QuotePdf;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class QuoteForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make()
                    ->columns(3)
                    ->schema([
                        TextInput::make('title')
                            ->label('Título')
                            ->placeholder('Equipo de escritorio, instalación de cámaras…')
                            ->maxLength(255)
                            ->columnSpanFull(),
                        Select::make('client_id')
                            ->label('Cliente')
                            // Inactive clients are hidden, except the one an existing quote already has.
                            ->relationship('client', 'business_name', fn ($query, ?Quote $record) => $query->where(
                                fn ($query) => $query->where('is_active', true)->when($record, fn ($query) => $query->orWhere('id', $record->client_id)),
                            ))
                            ->default(fn () => request()->query('client'))
                            ->searchable(['business_name', 'trade_name'])
                            ->preload()
                            ->required(),
                        DatePicker::make('issue_date')
                            ->label('Fecha')
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->default(today())
                            ->required(),
                        DatePicker::make('valid_until')
                            ->label('Válido hasta')
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->default(today()->addDays(config('pez.quotes.valid_days')))
                            ->afterOrEqual('issue_date')
                            ->required(),
                    ]),
                Section::make('Ítems')
                    ->schema([
                        Repeater::make('items')
                            ->hiddenLabel()
                            ->relationship()
                            ->orderColumn('sort')
                            ->addActionLabel('Agregar ítem')
                            ->minItems(1)
                            ->defaultItems(1)
                            ->columns(12)
                            ->live()
                            ->schema([
                                Select::make('service_item_id')
                                    ->label('Del catálogo')
                                    ->options(fn () => ServiceItem::where('is_active', true)->orderBy('name')->pluck('name', 'id'))
                                    ->searchable()
                                    ->live()
                                    ->afterStateUpdated(function (?string $state, Set $set) {
                                        $item = $state ? ServiceItem::find($state) : null;

                                        if ($item) {
                                            $set('title', $item->description ? $item->name : null);
                                            $set('description', $item->description ?: $item->name);
                                            $set('unit', $item->unit->value);
                                            $set('unit_price', $item->price);
                                        }
                                    })
                                    ->columnSpan(12),
                                TextInput::make('title')
                                    ->label('Componente / concepto')
                                    ->placeholder('Procesador, Servicio técnico, De regalo…')
                                    ->maxLength(255)
                                    ->columnSpan(12),
                                Textarea::make('description')
                                    ->label('Descripción')
                                    ->rows(2)
                                    ->required()
                                    ->columnSpan(12),
                                TextInput::make('quantity')
                                    ->label('Cantidad')
                                    ->numeric()
                                    ->minValue(0.01)
                                    ->default(1)
                                    ->required()
                                    ->live(onBlur: true)
                                    ->columnSpan(3),
                                TextInput::make('unit')
                                    ->label('Unidad')
                                    ->maxLength(50)
                                    ->columnSpan(3),
                                TextInput::make('unit_price')
                                    ->label('Precio unitario')
                                    ->helperText('En 0 se imprime "SIN CARGO".')
                                    ->numeric()
                                    ->minValue(0)
                                    ->prefix('$')
                                    ->required()
                                    ->live(onBlur: true)
                                    ->columnSpan(3),
                                TextEntry::make('line_total')
                                    ->label('Importe')
                                    ->state(fn (Get $get) => QuotePdf::money(self::lineTotal($get('quantity'), $get('unit_price'))))
                                    ->columnSpan(3),
                            ]),
                    ]),
                Grid::make(2)
                    ->schema([
                        Section::make('Condiciones')
                            ->schema([
                                TextInput::make('warranty')
                                    ->label('Garantía')
                                    ->default(config('pez.quotes.default_warranty'))
                                    ->maxLength(255),
                                TextInput::make('payment_terms')
                                    ->label('Forma de pago')
                                    ->default(config('pez.quotes.default_payment_terms'))
                                    ->maxLength(255),
                                TextInput::make('delivery_time')
                                    ->label('Plazo de entrega')
                                    ->default(config('pez.quotes.default_delivery_time'))
                                    ->maxLength(255),
                                Textarea::make('notes')
                                    ->label('Observaciones')
                                    ->rows(2),
                                Textarea::make('terms')
                                    ->label('Nota al pie')
                                    ->default(config('pez.quotes.default_terms'))
                                    ->rows(2),
                            ]),
                        Section::make('Totales')
                            ->schema([
                                TextInput::make('discount')
                                    ->label('Descuento')
                                    ->numeric()
                                    ->minValue(0)
                                    ->default(0)
                                    ->prefix('$')
                                    ->live(onBlur: true),
                                TextEntry::make('subtotal_preview')
                                    ->label('Subtotal')
                                    ->state(fn (Get $get) => QuotePdf::money(self::subtotal($get('items')))),
                                TextEntry::make('total_preview')
                                    ->label('Total (precio final)')
                                    ->size('lg')
                                    ->weight('bold')
                                    ->state(fn (Get $get) => QuotePdf::money(max(0, self::subtotal($get('items')) - (float) $get('discount')))),
                                Toggle::make('offer_installments')
                                    ->label('Ofrecer cuotas')
                                    ->live()
                                    ->dehydrated(false)
                                    ->afterStateHydrated(fn (Toggle $component, ?Quote $record) => $component->state(filled($record?->installment_plans)))
                                    ->afterStateUpdated(fn (bool $state, Set $set) => $set('installment_plans', $state ? Quote::defaultInstallmentPlans() : [])),
                                Repeater::make('installment_plans')
                                    ->hiddenLabel()
                                    ->visible(fn (Get $get) => $get('offer_installments'))
                                    // Turning the toggle off empties the plans; they must still be saved.
                                    ->dehydratedWhenHidden()
                                    ->defaultItems(0)
                                    ->addActionLabel('Agregar plan')
                                    ->reorderable(false)
                                    ->columns(3)
                                    ->live()
                                    ->schema([
                                        TextInput::make('count')
                                            ->label('Cuotas')
                                            ->integer()
                                            ->minValue(2)
                                            ->maxValue(48)
                                            ->required()
                                            ->live(onBlur: true),
                                        TextInput::make('surcharge')
                                            ->label('Recargo')
                                            ->numeric()
                                            ->minValue(0)
                                            ->suffix('%')
                                            ->default(0)
                                            ->required()
                                            ->live(onBlur: true),
                                        TextEntry::make('installment_preview')
                                            ->label('Cuota')
                                            ->state(fn (Get $get) => self::installmentPreview($get('count'), $get('surcharge'), $get('../../items'), $get('../../discount'))),
                                    ]),
                            ]),
                    ]),
            ]);
    }

    private static function installmentPreview(mixed $count, mixed $surcharge, ?array $items, mixed $discount): string
    {
        if ((int) $count < 2) {
            return '—';
        }

        $total = max(0, self::subtotal($items) - (float) $discount) * (1 + (float) $surcharge / 100);

        return (int) $count.' x '.QuotePdf::money(round($total / (int) $count, 2));
    }

    private static function lineTotal(mixed $quantity, mixed $unitPrice): float
    {
        return round((float) $quantity * (float) $unitPrice, 2);
    }

    /**
     * Live preview only; the stored totals are computed by the models on save.
     */
    private static function subtotal(?array $items): float
    {
        return collect($items ?? [])->sum(fn (array $item) => self::lineTotal($item['quantity'] ?? 0, $item['unit_price'] ?? 0));
    }
}
