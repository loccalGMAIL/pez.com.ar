<?php

namespace App\Filament\Resources\Clients\Schemas;

use App\Enums\TaxCondition;
use App\Models\Client;
use App\Rules\Cuit;
use Closure;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ClientForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Datos fiscales')
                    ->columns(2)
                    ->schema([
                        TextInput::make('business_name')
                            ->label('Razón social')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('trade_name')
                            ->label('Nombre de fantasía')
                            ->maxLength(255),
                        TextInput::make('cuit')
                            ->label('CUIT')
                            ->placeholder('30-12345678-9')
                            ->mask('99-99999999-9')
                            ->rule(new Cuit)
                            ->rule(fn (?Client $record) => self::uniqueCuit($record)),
                        Select::make('tax_condition')
                            ->label('Condición frente al IVA')
                            ->options(TaxCondition::class),
                    ]),
                Section::make('Contacto')
                    ->columns(2)
                    ->schema([
                        TextInput::make('email')
                            ->label('Email')
                            ->email()
                            ->maxLength(255),
                        TextInput::make('phone')
                            ->label('Teléfono')
                            ->tel()
                            ->maxLength(255),
                        TextInput::make('address')
                            ->label('Dirección')
                            ->maxLength(255),
                        TextInput::make('city')
                            ->label('Localidad')
                            ->maxLength(255),
                        TextInput::make('province')
                            ->label('Provincia')
                            ->maxLength(255),
                    ]),
                Section::make('Otros')
                    ->schema([
                        Textarea::make('notes')
                            ->label('Notas')
                            ->rows(4),
                        Toggle::make('is_active')
                            ->label('Activo')
                            ->default(true),
                    ]),
            ]);
    }

    /**
     * The column stores digits only while the input is masked, so the built-in
     * unique rule would never match. Trashed clients count too: the database
     * index still holds their CUIT.
     */
    private static function uniqueCuit(?Client $record): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($record) {
            $exists = Client::withTrashed()
                ->where('cuit', Cuit::normalize((string) $value))
                ->when($record, fn ($query) => $query->whereKeyNot($record->getKey()))
                ->exists();

            if ($exists) {
                $fail('Ya existe un cliente con ese CUIT.');
            }
        };
    }
}
