<?php

namespace App\Filament\Resources\ExternalRepairs\Tables;

use App\Enums\ExternalRepairStatus;
use App\Filament\Resources\WorkOrders\WorkOrderResource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ExternalRepairsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('sent_on', 'desc')
            ->columns([
                TextColumn::make('workOrder.number')
                    ->label('Trabajo')
                    ->formatStateUsing(fn ($record) => $record->workOrder->code)
                    ->description(fn ($record) => $record->workOrder->client->business_name)
                    ->url(fn ($record) => WorkOrderResource::getUrl('view', ['record' => $record->work_order_id])),
                TextColumn::make('device.name')
                    ->label('Equipo')
                    ->state(fn ($record) => $record->device?->label)
                    ->placeholder('—')
                    ->wrap(),
                TextColumn::make('supplier.name')
                    ->label('Proveedor')
                    ->description(fn ($record) => $record->supplier_reference ? "Orden {$record->supplier_reference}" : null)
                    ->searchable()
                    ->sortable(),
                TextColumn::make('problem')
                    ->label('Falla')
                    ->limit(40)
                    ->toggleable(),
                TextColumn::make('sent_on')
                    ->label('Enviado')
                    ->date('d/m/Y')
                    ->description(fn ($record) => $record->status->isOut() ? $record->sent_on->diffForHumans() : null)
                    ->sortable(),
                TextColumn::make('returned_on')
                    ->label('Retirado')
                    ->date('d/m/Y')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('cost')
                    ->label('Costo')
                    ->money('ARS', locale: 'es_AR')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('status')
                    ->label('Estado')
                    ->badge(),
            ])
            ->filters([
                Filter::make('out')
                    ->label('Solo los que están afuera')
                    ->query(fn (Builder $query) => $query->out())
                    ->default(),
                SelectFilter::make('status')
                    ->label('Estado')
                    ->options(ExternalRepairStatus::class),
                SelectFilter::make('supplier')
                    ->label('Proveedor')
                    ->relationship('supplier', 'name')
                    ->searchable(),
            ]);
    }
}
