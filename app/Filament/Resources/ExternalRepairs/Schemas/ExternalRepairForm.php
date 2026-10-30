<?php

namespace App\Filament\Resources\ExternalRepairs\Schemas;

use App\Enums\ExternalRepairStatus;
use App\Enums\WorkOrderStatus;
use App\Filament\Resources\Suppliers\Schemas\SupplierForm;
use App\Models\ExternalRepair;
use App\Models\WorkOrder;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class ExternalRepairForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Select::make('work_order_id')
                    ->label('Orden de trabajo')
                    ->relationship('workOrder', 'title', fn (Builder $query, ?ExternalRepair $record) => $query
                        ->where(fn ($query) => $query->whereIn('status', WorkOrderStatus::open())->when($record, fn ($query) => $query->orWhere('id', $record->work_order_id))))
                    ->getOptionLabelFromRecordUsing(fn (WorkOrder $record) => "{$record->code} — {$record->client->business_name}: {$record->title}")
                    ->searchable(['title'])
                    ->preload()
                    ->required()
                    ->live()
                    ->afterStateUpdated(fn (Set $set) => $set('device_id', null))
                    ->hidden(fn ($livewire) => $livewire instanceof RelationManager)
                    ->columnSpanFull(),
                Select::make('supplier_id')
                    ->label('Proveedor')
                    ->relationship('supplier', 'name')
                    ->searchable()
                    ->preload()
                    ->createOptionForm(SupplierForm::components())
                    ->required(),
                Select::make('device_id')
                    ->label('Equipo')
                    ->relationship('device', 'name', fn (Builder $query, Get $get, $livewire) => $query->whereHas(
                        'workOrders',
                        fn ($query) => $query->whereKey($livewire instanceof RelationManager ? $livewire->getOwnerRecord()->getKey() : $get('work_order_id')),
                    ))
                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->label)
                    ->preload()
                    ->helperText('Equipos asociados a la orden de trabajo.'),
                Textarea::make('problem')
                    ->label('Falla / qué se pide')
                    ->required()
                    ->rows(2)
                    ->columnSpanFull(),
                TextInput::make('supplier_reference')
                    ->label('N° de orden del proveedor')
                    ->maxLength(255),
                Select::make('status')
                    ->label('Estado')
                    ->options(ExternalRepairStatus::class)
                    ->default(ExternalRepairStatus::Sent)
                    ->required(),
                DatePicker::make('sent_on')
                    ->label('Enviado el')
                    ->native(false)
                    ->displayFormat('d/m/Y')
                    ->default(today())
                    ->required(),
                DatePicker::make('returned_on')
                    ->label('Retirado el')
                    ->native(false)
                    ->displayFormat('d/m/Y')
                    ->afterOrEqual('sent_on')
                    ->helperText('Se completa sola al marcarlo como retirado o sin reparación.'),
                TextInput::make('cost')
                    ->label('Costo del proveedor')
                    ->numeric()
                    ->minValue(0)
                    ->prefix('$'),
                TextInput::make('price')
                    ->label('Precio al cliente')
                    ->numeric()
                    ->minValue(0)
                    ->prefix('$'),
                Textarea::make('notes')
                    ->label('Notas')
                    ->rows(2)
                    ->columnSpanFull(),
            ]);
    }
}
