<?php

namespace App\Filament\Resources\Charges\Tables;

use App\Enums\ChargeStatus;
use App\Filament\Resources\Charges\Actions\ChargeActions;
use App\Models\Charge;
use App\Models\Client;
use Filament\Actions\ActionGroup;
use Filament\Actions\ViewAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ChargesTable
{
    /**
     * Compact concept for the list: the full text stays on the charge itself.
     */
    private static function shortConcept(Charge $charge): string
    {
        return match (true) {
            $charge->subscription_id !== null && $charge->period !== null => 'Abono — '.ucfirst($charge->period->translatedFormat('F Y')),
            $charge->workOrder !== null => 'OT '.str_pad((string) $charge->workOrder->number, 5, '0', STR_PAD_LEFT),
            default => $charge->concept,
        };
    }

    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('due_on')
            ->modifyQueryUsing(fn (Builder $query) => $query->with('workOrder'))
            ->columns([
                TextColumn::make('client.business_name')
                    ->label('Cliente')
                    // Implied inside the client's own page.
                    ->hidden(fn ($livewire) => $livewire instanceof RelationManager && $livewire->getOwnerRecord() instanceof Client)
                    ->limit(22)
                    ->tooltip(fn (TextColumn $column) => strlen((string) $column->getState()) > 22 ? $column->getState() : null)
                    ->searchable()
                    ->sortable(),
                TextColumn::make('concept')
                    ->label('Concepto')
                    ->state(fn (Charge $record) => self::shortConcept($record))
                    ->searchable()
                    ->wrap(),
                TextColumn::make('issued_on')
                    ->label('Fecha')
                    ->date('d/m/Y')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('due_on')
                    ->label('Vence')
                    ->date('d/m/Y')
                    ->sortable()
                    ->color(fn (Charge $record) => $record->isOverdue() ? 'danger' : null)
                    ->description(fn (Charge $record) => $record->isOverdue() ? 'Vencido' : null),
                TextColumn::make('amount')
                    ->label('Monto')
                    ->money('ARS', locale: 'es_AR')
                    ->sortable(),
                TextColumn::make('outstanding')
                    ->label('Saldo')
                    ->state(fn (Charge $record) => $record->status->isOpen() ? $record->outstanding() : null)
                    ->money('ARS', locale: 'es_AR')
                    ->placeholder('—'),
                TextColumn::make('status')
                    ->label('Estado')
                    ->badge(),
            ])
            ->filters([
                Filter::make('pending')
                    ->label('Por cobrar o a confirmar')
                    ->query(fn (Builder $query) => $query->whereIn('status', [ChargeStatus::Draft, ...ChargeStatus::open()]))
                    ->default(),
                Filter::make('overdue')
                    ->label('Vencidos')
                    ->query(fn (Builder $query) => $query->scopes('overdue')),
                SelectFilter::make('status')
                    ->label('Estado')
                    ->options(ChargeStatus::class),
                SelectFilter::make('client')
                    ->label('Cliente')
                    ->relationship('client', 'business_name')
                    ->searchable(),
            ])
            ->recordActions([
                ChargeActions::confirm(),
                ChargeActions::registerPayment(),
                ActionGroup::make([
                    ViewAction::make(),
                    ChargeActions::cancel(),
                ]),
            ]);
    }
}
