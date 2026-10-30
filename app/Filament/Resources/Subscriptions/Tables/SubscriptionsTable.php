<?php

namespace App\Filament\Resources\Subscriptions\Tables;

use App\Enums\SubscriptionType;
use App\Models\Client;
use Filament\Actions\EditAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class SubscriptionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('billing_day')
            ->columns([
                TextColumn::make('client.business_name')
                    ->label('Cliente')
                    // Implied inside the client's own page.
                    ->hidden(fn ($livewire) => $livewire instanceof RelationManager && $livewire->getOwnerRecord() instanceof Client)
                    ->searchable()
                    ->sortable(),
                TextColumn::make('concept')
                    ->label('Concepto')
                    ->searchable(),
                TextColumn::make('type')
                    ->label('Tipo')
                    ->badge()
                    ->color(fn (SubscriptionType $state) => $state === SubscriptionType::Fixed ? 'gray' : 'info'),
                TextColumn::make('amount')
                    ->label('Monto')
                    ->money('ARS', locale: 'es_AR')
                    ->sortable(),
                TextColumn::make('billing_day')
                    ->label('Día')
                    ->sortable(),
                TextColumn::make('ends_on')
                    ->label('Hasta')
                    ->date('d/m/Y')
                    ->placeholder('—')
                    ->toggleable(),
                IconColumn::make('is_active')
                    ->label('Activo')
                    ->boolean(),
            ])
            ->filters([
                TernaryFilter::make('is_active')
                    ->label('Activo')
                    ->default(true),
                SelectFilter::make('type')
                    ->label('Tipo')
                    ->options(SubscriptionType::class),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
