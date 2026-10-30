<?php

namespace App\Filament\Resources\Clients\RelationManagers;

use App\Filament\Resources\WorkOrders\Tables\WorkOrdersTable;
use App\Filament\Resources\WorkOrders\WorkOrderResource;
use Filament\Actions\CreateAction;
use Filament\Actions\ViewAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;

class WorkOrdersRelationManager extends RelationManager
{
    protected static string $relationship = 'workOrders';

    protected static ?string $title = 'Trabajos';

    public function table(Table $table): Table
    {
        return WorkOrdersTable::configure($table)
            ->filters([])
            ->headerActions([
                CreateAction::make()
                    ->url(fn () => WorkOrderResource::getUrl('create', ['client' => $this->getOwnerRecord()->getKey()])),
            ])
            ->recordActions([
                ViewAction::make()
                    ->url(fn ($record) => WorkOrderResource::getUrl('view', ['record' => $record])),
            ]);
    }
}
