<?php

namespace App\Filament\Resources\Suppliers\Schemas;

use App\Enums\PurchaseChannel;
use App\Enums\SupplierKind;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Utilities\Get;

class SupplierForm
{
    /**
     * Shared with the quick-create option of the external repair form.
     *
     * @return array<Component>
     */
    public static function components(): array
    {
        return [
            TextInput::make('name')
                ->label('Nombre')
                ->required()
                ->maxLength(255),
            Select::make('kind')
                ->label('Tipo')
                ->options(SupplierKind::class)
                ->default(SupplierKind::Business)
                ->live()
                ->required()
                ->native(false),
            Select::make('channel')
                ->label('Canal de compra')
                ->options(PurchaseChannel::class)
                ->visible(fn (Get $get) => in_array($get('kind'), [SupplierKind::Individual, SupplierKind::Individual->value], true))
                ->native(false),
            TextInput::make('specialty')
                ->label('Rubro')
                ->placeholder('Impresoras, fuentes, notebooks…')
                ->maxLength(255),
            TextInput::make('contact_name')
                ->label('Contacto')
                ->maxLength(255),
            TextInput::make('phone')
                ->label('Teléfono')
                ->tel()
                ->maxLength(255),
            TextInput::make('email')
                ->label('Email')
                ->email()
                ->maxLength(255),
            TextInput::make('address')
                ->label('Dirección')
                ->maxLength(255),
            Textarea::make('notes')
                ->label('Notas')
                ->rows(3)
                ->columnSpanFull(),
        ];
    }
}
