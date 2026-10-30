<?php

namespace App\Filament\Resources\WorkOrders\Tables;

use App\Enums\WorkOrderStatus;
use App\Models\Client;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class WorkOrdersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('number', 'desc')
            ->columns([
                TextColumn::make('code')
                    ->label('Número')
                    ->sortable(query: fn (Builder $query, string $direction) => $query->orderBy('number', $direction))
                    ->searchable(query: fn (Builder $query, string $search) => $query->where('number', (int) preg_replace('/\D/', '', $search))),
                TextColumn::make('title')
                    ->label('Título')
                    ->searchable()
                    ->limit(50),
                TextColumn::make('client.business_name')
                    ->label('Cliente')
                    // Implied inside the client's own page.
                    ->hidden(fn ($livewire) => $livewire instanceof RelationManager && $livewire->getOwnerRecord() instanceof Client)
                    ->searchable()
                    ->sortable(),
                TextColumn::make('opened_on')
                    ->label('Ingreso')
                    ->date('d/m/Y')
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Estado')
                    ->badge(),
            ])
            ->filters([
                Filter::make('open')
                    ->label('Solo abiertos')
                    ->query(fn (Builder $query) => $query->whereIn('status', WorkOrderStatus::open()))
                    ->default(),
                SelectFilter::make('status')
                    ->label('Estado')
                    ->options(WorkOrderStatus::class),
                SelectFilter::make('client')
                    ->label('Cliente')
                    ->relationship('client', 'business_name')
                    ->searchable(),
                TrashedFilter::make(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ]);
    }
}
