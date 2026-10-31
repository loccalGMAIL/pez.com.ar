<?php

namespace App\Filament\Resources\ServiceItems\Tables;

use App\Enums\ProductCondition;
use App\Enums\ServiceUnit;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class ServiceItemsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')
                    ->label('Nombre')
                    ->description(fn ($record) => str($record->description)->limit(80))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('condition')
                    ->label('Condición')
                    ->badge()
                    ->placeholder('—')
                    ->visible(fn ($livewire) => $livewire->activeTab === 'producto'),
                TextColumn::make('unit')
                    ->label('Unidad'),
                TextColumn::make('price')
                    ->label('Precio base')
                    ->money('ARS', locale: 'es_AR')
                    ->sortable(),
                TextColumn::make('cost')
                    ->label('Costo')
                    ->money('ARS', locale: 'es_AR')
                    ->placeholder('—')
                    ->description(fn ($record) => $record->supplier?->name)
                    ->visible(fn ($livewire) => $livewire->activeTab === 'producto'),
                IconColumn::make('is_active')
                    ->label('Activo')
                    ->boolean(),
            ])
            ->filters([
                TernaryFilter::make('is_active')
                    ->label('Activo'),
                SelectFilter::make('condition')
                    ->label('Condición')
                    ->options(ProductCondition::class),
                SelectFilter::make('unit')
                    ->label('Unidad')
                    ->options(ServiceUnit::class),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
