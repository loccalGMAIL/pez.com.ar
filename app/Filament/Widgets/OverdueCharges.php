<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Charges\Actions\ChargeActions;
use App\Filament\Resources\Charges\ChargeResource;
use App\Models\Charge;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class OverdueCharges extends TableWidget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Cargos vencidos';

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => Charge::query()->overdue()->with('client'))
            ->defaultSort('due_on')
            ->emptyStateHeading('No hay cargos vencidos')
            ->columns([
                TextColumn::make('client.business_name')
                    ->label('Cliente'),
                TextColumn::make('concept')
                    ->label('Concepto')
                    ->wrap(),
                TextColumn::make('due_on')
                    ->label('Venció')
                    ->date('d/m/Y')
                    ->description(fn (Charge $record) => $record->due_on->diffForHumans())
                    ->color('danger'),
                TextColumn::make('outstanding')
                    ->label('Saldo')
                    ->state(fn (Charge $record) => $record->outstanding())
                    ->money('ARS', locale: 'es_AR'),
            ])
            ->recordActions([
                ChargeActions::registerPayment(),
                ViewAction::make()
                    ->url(fn (Charge $record) => ChargeResource::getUrl('view', ['record' => $record])),
            ]);
    }
}
