<?php

namespace App\Filament\Resources\WorkOrders\Schemas;

use App\Enums\QuoteStatus;
use App\Enums\WorkOrderStatus;
use App\Models\WorkOrder;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class WorkOrderForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make()
                    ->columns(2)
                    ->schema([
                        Select::make('client_id')
                            ->label('Cliente')
                            // Inactive clients are hidden, except the one an existing order already has.
                            ->relationship('client', 'business_name', fn (Builder $query, ?WorkOrder $record) => $query->where(
                                fn ($query) => $query->where('is_active', true)->when($record, fn ($query) => $query->orWhere('id', $record->client_id)),
                            ))
                            ->searchable(['business_name', 'trade_name'])
                            ->preload()
                            ->default(fn () => request()->query('client'))
                            ->required()
                            ->live()
                            ->afterStateUpdated(function (Set $set) {
                                $set('devices', []);
                                $set('quote_id', null);
                            })
                            ->hidden(fn ($livewire) => $livewire instanceof RelationManager),
                        Select::make('status')
                            ->label('Estado')
                            ->options(WorkOrderStatus::class)
                            ->default(WorkOrderStatus::Pending)
                            ->required(),
                        TextInput::make('title')
                            ->label('Título')
                            ->placeholder('No enciende, configurar WiFi…')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),
                        Select::make('devices')
                            ->label('Equipos')
                            ->multiple()
                            ->relationship('devices', 'name', fn (Builder $query, Get $get, $livewire) => $query->where(
                                'client_id',
                                $livewire instanceof RelationManager ? $livewire->getOwnerRecord()->getKey() : $get('client_id'),
                            ))
                            ->getOptionLabelFromRecordUsing(fn ($record) => $record->label)
                            ->preload()
                            ->helperText('Equipos del cliente involucrados. Se cargan desde la ficha del cliente.'),
                        Select::make('quote_id')
                            ->label('Presupuesto')
                            ->relationship('quote', 'number', fn (Builder $query, Get $get, $livewire) => $query
                                ->where('client_id', $livewire instanceof RelationManager ? $livewire->getOwnerRecord()->getKey() : $get('client_id'))
                                ->where('status', QuoteStatus::Accepted))
                            ->getOptionLabelFromRecordUsing(fn ($record) => "{$record->code} — {$record->issue_date->format('d/m/Y')}")
                            ->placeholder('Sin presupuesto'),
                        Textarea::make('description')
                            ->label('Pedido / problema')
                            ->rows(4)
                            ->columnSpanFull(),
                    ]),
                Section::make('Resolución')
                    ->columns(3)
                    ->schema([
                        DatePicker::make('opened_on')
                            ->label('Fecha de ingreso')
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->default(today())
                            ->required(),
                        DatePicker::make('due_on')
                            ->label('Comprometido para')
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->afterOrEqual('opened_on'),
                        TextInput::make('hours')
                            ->label('Horas trabajadas')
                            ->numeric()
                            ->minValue(0)
                            ->step(0.25),
                        Textarea::make('resolution')
                            ->label('Diagnóstico y trabajo realizado')
                            ->rows(4)
                            ->columnSpanFull(),
                        FileUpload::make('attachments')
                            ->label('Adjuntos')
                            ->helperText('Fotos, informes, remitos.')
                            ->multiple()
                            ->disk('local')
                            ->directory('work-orders')
                            ->visibility('private')
                            ->downloadable()
                            ->openable()
                            ->maxSize(10240)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
