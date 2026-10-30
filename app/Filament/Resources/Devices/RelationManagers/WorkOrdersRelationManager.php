<?php

namespace App\Filament\Resources\Devices\RelationManagers;

use App\Filament\Resources\WorkOrders\Tables\WorkOrdersTable;
use App\Filament\Resources\WorkOrders\WorkOrderResource;
use Filament\Actions\ViewAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;

/**
 * History of the device: every job it was part of.
 */
class WorkOrdersRelationManager extends RelationManager
{
    protected static string $relationship = 'workOrders';

    protected static ?string $title = 'Historial de trabajos';

    public function table(Table $table): Table
    {
        return WorkOrdersTable::configure($table)
            ->filters([])
            ->recordActions([
                ViewAction::make()
                    ->url(fn ($record) => WorkOrderResource::getUrl('view', ['record' => $record])),
            ]);
    }
}
