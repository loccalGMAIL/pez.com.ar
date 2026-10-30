<?php

namespace App\Filament\Resources\WorkOrders\Schemas;

use App\Enums\QuoteStatus;
use App\Enums\WorkOrderStatus;
use App\Filament\Forms\PhotosUpload;
use App\Filament\Resources\Clients\Schemas\ClientForm;
use App\Filament\Resources\Devices\Schemas\DeviceForm;
use App\Models\Device;
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
                            ->createOptionForm(fn (Schema $schema) => ClientForm::configure($schema))
                            ->createOptionModalHeading('Nuevo cliente')
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
                            ->required()
                            ->hiddenOn('create'),
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
                            ->helperText('Equipos del cliente involucrados. Si el equipo es nuevo, cargalo con el "+".')
                            ->createOptionForm(fn (Schema $schema) => DeviceForm::configure($schema, withClient: false))
                            ->createOptionUsing(function (array $data, Get $get, $livewire): int {
                                $data['client_id'] = $livewire instanceof RelationManager ? $livewire->getOwnerRecord()->getKey() : $get('client_id');

                                return Device::create($data)->getKey();
                            })
                            ->disabled(fn (Get $get, $livewire) => ! $livewire instanceof RelationManager && blank($get('client_id'))),
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
                        PhotosUpload::make('reception_photos', 'work-orders/reception')
                            ->label('Fotos de recepción')
                            ->helperText('Opcional: estado en que se recibe el equipo (golpes, faltantes, accesorios).')
                            ->columnSpanFull(),
                    ]),
                Section::make('Resolución')
                    ->hiddenOn('create')
                    ->columns(3)
                    ->schema([
                        DatePicker::make('opened_on')
                            ->label('Fecha de ingreso')
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->required(),
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
