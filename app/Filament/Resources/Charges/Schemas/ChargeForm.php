<?php

namespace App\Filament\Resources\Charges\Schemas;

use App\Filament\Resources\Suppliers\Schemas\SupplierForm;
use App\Models\Charge;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class ChargeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Select::make('client_id')
                    ->label('Cliente')
                    ->relationship('client', 'business_name')
                    ->searchable(['business_name', 'trade_name'])
                    ->preload()
                    ->default(fn () => request()->query('client'))
                    ->required()
                    ->disabledOn('edit')
                    ->hidden(fn ($livewire) => $livewire instanceof RelationManager)
                    ->columnSpanFull(),
                TextInput::make('concept')
                    ->label('Concepto')
                    ->disabled(fn (?Charge $record) => self::isInvoiced($record))
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),
                TextInput::make('amount')
                    ->label('Monto')
                    ->disabled(fn (?Charge $record) => self::isInvoiced($record))
                    ->helperText(fn (?Charge $record) => self::isInvoiced($record) ? 'Está facturado: para cambiarlo, anulá la factura con nota de crédito y volvé a facturar.' : null)
                    ->numeric()
                    ->minValue(0)
                    ->prefix('$')
                    ->required(),
                DatePicker::make('issued_on')
                    ->label('Fecha')
                    ->native(false)
                    ->displayFormat('d/m/Y')
                    ->default(today())
                    ->required(),
                DatePicker::make('due_on')
                    ->label('Vencimiento')
                    ->native(false)
                    ->displayFormat('d/m/Y')
                    ->afterOrEqual('issued_on')
                    ->helperText('Vacío: según los días de la configuración.'),
                Textarea::make('notes')
                    ->label('Detalle')
                    ->rows(3)
                    ->columnSpanFull(),
                Toggle::make('is_resale')
                    ->label('Reventa (equipo o insumo comprado para esta venta)')
                    ->live()
                    ->columnSpanFull(),
                Fieldset::make('Compra (uso interno)')
                    ->columns(2)
                    ->columnSpanFull()
                    ->visible(fn (Get $get) => (bool) $get('is_resale'))
                    ->schema([
                        Select::make('supplier_id')
                            ->label('Comprado a')
                            ->relationship('supplier', 'name')
                            ->searchable()
                            ->preload()
                            ->createOptionForm(SupplierForm::components()),
                        TextInput::make('cost')
                            ->label('Costo')
                            ->numeric()
                            ->minValue(0)
                            ->prefix('$')
                            ->live(onBlur: true)
                            ->helperText(fn (Get $get) => filled($get('cost')) && filled($get('amount'))
                                ? 'Margen: $ '.number_format((float) $get('amount') - (float) $get('cost'), 2, ',', '.')
                                : 'Si todavía no lo sabés, dejalo vacío: queda como costo pendiente.'),
                    ]),
            ]);
    }

    /**
     * An invoiced charge keeps the concept and amount it was invoiced with.
     */
    private static function isInvoiced(?Charge $record): bool
    {
        return $record !== null && once(fn () => $record->invoicesInForce()->isNotEmpty());
    }
}
