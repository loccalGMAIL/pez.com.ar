<?php

namespace App\Filament\Resources\Clients\RelationManagers;

use App\Filament\Resources\Clients\Actions\FollowUpActions;
use App\Models\FollowUp;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class FollowUpsRelationManager extends RelationManager
{
    protected static string $relationship = 'followUps';

    protected static ?string $title = 'Seguimientos';

    protected static ?string $modelLabel = 'seguimiento';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['workOrder', 'quote', 'charge']))
            ->defaultSort('due_on', 'desc')
            ->emptyStateHeading('Sin seguimientos')
            ->columns([
                TextColumn::make('due_on')
                    ->label('Fecha')
                    ->date('d/m/Y')
                    ->sortable(),
                TextColumn::make('subject')
                    ->label('Motivo')
                    ->state(fn (FollowUp $record) => $record->subject())
                    ->wrap(),
                TextColumn::make('status')
                    ->label('Estado')
                    ->badge(),
                TextColumn::make('sent_at')
                    ->label('Enviado')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('—'),
            ])
            ->recordActions([
                FollowUpActions::send(),
                FollowUpActions::postpone(),
                FollowUpActions::skip(),
            ]);
    }
}
