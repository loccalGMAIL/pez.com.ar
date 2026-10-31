<?php

namespace App\Filament\Resources\Quotes\Schemas;

use App\Filament\Resources\Quotes\Pages\CreateQuote;
use App\Filament\Resources\Quotes\Pages\EditQuote;
use App\Filament\Resources\Suppliers\Schemas\SupplierForm;
use App\Models\Quote;
use App\Models\ServiceItem;
use App\Support\QuotePdf;
use App\Support\Settings;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\VerticalAlignment;

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
                            ->default(today()->addDays((int) Settings::get('quotes.valid_days')))
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
                            ->columns(['default' => 2, 'md' => 12])
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
                                            $set('unit_cost', $item->cost);
                                            $set('supplier_id', $item->supplier_id);
                                            $set('markup', self::markupOf($item->cost, $item->price));
                                        }
                                    })
                                    ->columnSpanFull(),
                                TextInput::make('title')
                                    ->label('Componente / concepto')
                                    ->placeholder('Procesador, Servicio técnico, De regalo…')
                                    ->maxLength(255)
                                    ->columnSpanFull(),
                                Textarea::make('description')
                                    ->label('Descripción')
                                    ->rows(2)
                                    ->required()
                                    ->columnSpanFull(),
                                TextInput::make('quantity')
                                    ->label('Cantidad')
                                    ->numeric()
                                    ->minValue(0.01)
                                    ->default(1)
                                    ->required()
                                    ->live(onBlur: true)
                                    ->columnSpan(['default' => 1, 'md' => 2]),
                                TextInput::make('unit')
                                    ->label('Unidad')
                                    ->maxLength(50)
                                    ->columnSpan(['default' => 1, 'md' => 2]),
                                TextInput::make('unit_price')
                                    ->label('Precio unitario')
                                    ->helperText('En 0 se imprime "SIN CARGO".')
                                    ->numeric()
                                    ->minValue(0)
                                    ->prefix('$')
                                    ->required()
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(fn (Get $get, Set $set) => self::syncMarkup($get, $set))
                                    ->columnSpan(['default' => 1, 'md' => 4]),
                                TextEntry::make('line_total')
                                    ->label('Importe')
                                    ->state(fn (Get $get) => QuotePdf::money(self::lineTotal($get('quantity'), $get('unit_price'))))
                                    ->weight('bold')
                                    ->columnSpan(['default' => 1, 'md' => 4]),
                                Section::make('Compra (uso interno, no se imprime)')
                                    ->collapsible()
                                    ->collapsed()
                                    ->compact()
                                    ->columns(3)
                                    ->columnSpanFull()
                                    ->schema([
                                        Select::make('supplier_id')
                                            ->label('Comprado a')
                                            ->relationship('supplier', 'name')
                                            ->searchable()
                                            ->preload()
                                            ->createOptionForm(SupplierForm::components()),
                                        TextInput::make('unit_cost')
                                            ->label('Costo unitario')
                                            ->numeric()
                                            ->minValue(0)
                                            ->prefix('$')
                                            ->live(onBlur: true)
                                            ->afterStateUpdated(function (Get $get, Set $set) {
                                                $set('markup', $get('markup') ?: Settings::get('quotes.default_markup'));
                                                self::applyMarkup($get, $set);
                                            }),
                                        TextInput::make('markup')
                                            ->label('Recargo')
                                            ->numeric()
                                            ->minValue(0)
                                            ->suffix('%')
                                            ->live(onBlur: true)
                                            // Not stored: derived from cost and price.
                                            ->dehydrated(false)
                                            ->afterStateHydrated(fn (TextInput $component, Get $get) => $component->state(self::markupOf($get('unit_cost'), $get('unit_price'))))
                                            ->afterStateUpdated(fn (Get $get, Set $set) => self::applyMarkup($get, $set))
                                            ->helperText('Sobre el costo; el precio se sugiere con este recargo.'),
                                    ]),
                            ]),
                    ]),
                Grid::make(2)
                    ->schema([
                        Section::make('Condiciones')
                            ->collapsible()
                            ->collapsed()
                            ->schema([
                                TextInput::make('warranty')
                                    ->label('Garantía')
                                    ->default(Settings::get('quotes.default_warranty'))
                                    ->maxLength(255),
                                TextInput::make('payment_terms')
                                    ->label('Forma de pago')
                                    ->default(Settings::get('quotes.default_payment_terms'))
                                    ->maxLength(255),
                                TextInput::make('delivery_time')
                                    ->label('Plazo de entrega')
                                    ->default(Settings::get('quotes.default_delivery_time'))
                                    ->maxLength(255),
                                Textarea::make('notes')
                                    ->label('Observaciones')
                                    ->rows(2),
                                Textarea::make('terms')
                                    ->label('Nota al pie')
                                    ->default(Settings::get('quotes.default_terms'))
                                    ->rows(2),
                            ]),
                        Section::make('Cuotas')
                            ->collapsible()
                            ->collapsed()
                            ->schema([
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
                self::floatingFooter($schema),
            ]);
    }

    /**
     * Totals and form buttons in one bar that stays at the bottom of the screen while the items scroll.
     */
    private static function floatingFooter(Schema $schema): Section
    {
        $livewire = $schema->getLivewire();

        return Section::make()
            ->compact()
            ->columns(['default' => 2, 'lg' => 5])
            ->extraAttributes(['style' => 'position: sticky; bottom: 1rem; z-index: 10; box-shadow: 0 10px 25px -5px rgb(0 0 0 / 0.2);'])
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
                TextEntry::make('margin_preview')
                    ->label('Costo y margen (interno)')
                    ->visible(fn (Get $get) => self::cost($get('items')) > 0)
                    ->state(fn (Get $get) => 'Costo '.QuotePdf::money(self::cost($get('items')))
                        .' · Margen '.QuotePdf::money(max(0, self::subtotal($get('items')) - (float) $get('discount')) - self::cost($get('items')))),
                Actions::make($livewire instanceof CreateQuote || $livewire instanceof EditQuote ? $livewire->getFormActions() : [])
                    ->alignment(Alignment::End)
                    ->verticalAlignment(VerticalAlignment::End),
            ]);
    }

    /**
     * @param  array<array-key, array<string, mixed>>|null  $items
     */
    private static function installmentPreview(mixed $count, mixed $surcharge, ?array $items, mixed $discount): string
    {
        if ((int) $count < 2) {
            return '—';
        }

        $total = max(0, self::subtotal($items) - (float) $discount) * (1 + (float) $surcharge / 100);

        return (int) $count.' x '.QuotePdf::money(round($total / (int) $count, 2));
    }

    /**
     * Sets the price from the cost and markup, like the catalog does for its own price.
     */
    private static function applyMarkup(Get $get, Set $set): void
    {
        if (blank($get('unit_cost')) || blank($get('markup'))) {
            return;
        }

        $set('unit_price', round((float) $get('unit_cost') * (1 + (float) $get('markup') / 100), 2));
    }

    /**
     * Keeps the markup in line with a price edited by hand.
     */
    private static function syncMarkup(Get $get, Set $set): void
    {
        $set('markup', self::markupOf($get('unit_cost'), $get('unit_price')));
    }

    private static function markupOf(mixed $cost, mixed $price): ?float
    {
        return (float) $cost > 0 ? round(((float) $price / (float) $cost - 1) * 100, 2) : null;
    }

    /**
     * @param  array<array-key, array<string, mixed>>|null  $items
     */
    private static function cost(?array $items): float
    {
        return collect($items ?? [])->sum(fn (array $item) => self::lineTotal($item['quantity'] ?? 0, $item['unit_cost'] ?? 0));
    }

    private static function lineTotal(mixed $quantity, mixed $unitPrice): float
    {
        return round((float) $quantity * (float) $unitPrice, 2);
    }

    /**
     * Live preview only; the stored totals are computed by the models on save.
     *
     * @param  array<array-key, array<string, mixed>>|null  $items
     */
    private static function subtotal(?array $items): float
    {
        return collect($items ?? [])->sum(fn (array $item) => self::lineTotal($item['quantity'] ?? 0, $item['unit_price'] ?? 0));
    }
}
