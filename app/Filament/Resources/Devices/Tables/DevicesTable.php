<?php

namespace App\Filament\Resources\Devices\Tables;

use App\Enums\DeviceType;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class DevicesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('client.business_name')
            ->columns([
                TextColumn::make('client.business_name')
                    ->label('Cliente')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('type')
                    ->label('Tipo'),
                TextColumn::make('name')
                    ->label('Identificación')
                    ->placeholder('—')
                    ->searchable(),
                TextColumn::make('brand')
                    ->label('Marca / modelo')
                    ->formatStateUsing(fn ($record) => trim("{$record->brand} {$record->model}"))
                    ->searchable(['brand', 'model']),
                TextColumn::make('serial_number')
                    ->label('N° de serie')
                    ->placeholder('—')
                    ->searchable()
                    ->copyable(),
                TextColumn::make('work_orders_count')
                    ->label('Trabajos')
                    ->counts('workOrders')
                    ->sortable(),
                IconColumn::make('is_active')
                    ->label('En uso')
                    ->boolean(),
            ])
            ->filters([
                SelectFilter::make('client')
                    ->label('Cliente')
                    ->relationship('client', 'business_name')
                    ->searchable(),
                SelectFilter::make('type')
                    ->label('Tipo')
                    ->options(DeviceType::class),
                TernaryFilter::make('is_active')
                    ->label('En uso'),
                TrashedFilter::make(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
