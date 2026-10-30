<?php

namespace App\Filament\Resources\Payments\Tables;

use App\Enums\PaymentMethod;
use App\Models\Client;
use App\Models\Payment;
use Filament\Actions\ViewAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PaymentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('paid_on', 'desc')
            ->columns([
                TextColumn::make('paid_on')
                    ->label('Fecha')
                    ->date('d/m/Y')
                    ->sortable(),
                TextColumn::make('client.business_name')
                    ->label('Cliente')
                    // Implied inside the client's own page.
                    ->hidden(fn ($livewire) => $livewire instanceof RelationManager && $livewire->getOwnerRecord() instanceof Client)
                    ->searchable()
                    ->sortable(),
                TextColumn::make('method')
                    ->label('Medio'),
                TextColumn::make('reference')
                    ->label('Referencia')
                    ->placeholder('—')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('amount')
                    ->label('Monto')
                    ->money('ARS', locale: 'es_AR')
                    ->sortable(),
                TextColumn::make('unallocated')
                    ->label('A favor')
                    ->state(fn (Payment $record) => $record->unallocated() ?: null)
                    ->money('ARS', locale: 'es_AR')
                    ->placeholder('—')
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('method')
                    ->label('Medio')
                    ->options(PaymentMethod::class),
                SelectFilter::make('client')
                    ->label('Cliente')
                    ->relationship('client', 'business_name')
                    ->searchable(),
                Filter::make('this_month')
                    ->label('Este mes')
                    ->query(fn (Builder $query) => $query->whereBetween('paid_on', [today()->startOfMonth(), today()->endOfMonth()])),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }
}
