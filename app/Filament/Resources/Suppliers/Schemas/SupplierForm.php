<?php

namespace App\Filament\Resources\Suppliers\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;

class SupplierForm
{
    /**
     * Shared with the quick-create option of the external repair form.
     *
     * @return array<\Filament\Schemas\Components\Component>
     */
    public static function components(): array
    {
        return [
            TextInput::make('name')
                ->label('Nombre')
                ->required()
                ->maxLength(255),
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
