<?php

namespace App\Filament\Resources\ServiceItems\Schemas;

use App\Enums\CatalogItemType;
use App\Enums\ProductCondition;
use App\Enums\ServiceUnit;
use App\Filament\Resources\Suppliers\Schemas\SupplierForm;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class ServiceItemForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Select::make('type')
                    ->label('Tipo')
                    ->options(CatalogItemType::class)
                    ->default(fn () => CatalogItemType::tryFrom((string) request()->query('type')) ?? CatalogItemType::Service)
                    ->live()
                    ->afterStateUpdated(function ($state, Set $set) {
                        $isProduct = self::isProduct($state);

                        $set('condition', $isProduct ? ProductCondition::New->value : null);
                        $set('unit', ($isProduct ? ServiceUnit::Unit : ServiceUnit::Hour)->value);
                    })
                    ->required()
                    ->native(false),
                Select::make('condition')
                    ->label('Condición')
                    ->options(ProductCondition::class)
                    ->default(fn () => CatalogItemType::tryFrom((string) request()->query('type')) === CatalogItemType::Product ? ProductCondition::New : null)
                    ->visible(fn (Get $get) => self::isProduct($get('type')))
                    ->required(fn (Get $get) => self::isProduct($get('type')))
                    ->native(false),
                TextInput::make('name')
                    ->label('Nombre')
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),
                Textarea::make('description')
                    ->label('Descripción')
                    ->helperText('Se copia como detalle al agregarlo a un presupuesto.')
                    ->rows(3)
                    ->columnSpanFull(),
                Select::make('unit')
                    ->label('Unidad')
                    ->options(ServiceUnit::class)
                    ->default(fn () => CatalogItemType::tryFrom((string) request()->query('type')) === CatalogItemType::Product ? ServiceUnit::Unit : ServiceUnit::Hour)
                    ->required(),
                TextInput::make('price')
                    ->label('Precio base')
                    ->numeric()
                    ->minValue(0)
                    ->step(0.01)
                    ->prefix('$')
                    ->live(onBlur: true)
                    ->required(),
                Select::make('supplier_id')
                    ->label('Proveedor habitual')
                    ->relationship('supplier', 'name')
                    ->searchable()
                    ->preload()
                    ->createOptionForm(SupplierForm::components())
                    ->visible(fn (Get $get) => self::isProduct($get('type'))),
                TextInput::make('cost')
                    ->label('Costo')
                    ->numeric()
                    ->minValue(0)
                    ->step(0.01)
                    ->prefix('$')
                    ->live(onBlur: true)
                    ->helperText(fn (Get $get) => filled($get('cost')) && filled($get('price'))
                        ? 'Margen: $ '.number_format((float) $get('price') - (float) $get('cost'), 2, ',', '.').((float) $get('cost') > 0 ? ' ('.number_format(((float) $get('price') / (float) $get('cost') - 1) * 100, 1, ',', '.').' % sobre el costo)' : '')
                        : 'Interno: no se imprime. Se copia al armar presupuestos.')
                    ->visible(fn (Get $get) => self::isProduct($get('type'))),
                Textarea::make('notes')
                    ->label('Notas internas')
                    ->helperText('Links, referencias, dónde se consigue. No se imprime en los presupuestos.')
                    ->rows(3)
                    ->columnSpanFull(),
                Toggle::make('is_active')
                    ->label('Activo')
                    ->helperText('Los inactivos no se ofrecen al armar presupuestos.')
                    ->default(true),
            ]);
    }

    /**
     * The type state is an enum or its value depending on where it comes from.
     */
    private static function isProduct(mixed $type): bool
    {
        return in_array($type, [CatalogItemType::Product, CatalogItemType::Product->value], true);
    }
}
