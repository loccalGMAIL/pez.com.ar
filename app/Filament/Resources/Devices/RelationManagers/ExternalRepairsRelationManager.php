<?php

namespace App\Filament\Resources\Devices\RelationManagers;

use App\Filament\Resources\ExternalRepairs\Tables\ExternalRepairsTable;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;

/**
 * History only: repairs are created from the work order.
 */
class ExternalRepairsRelationManager extends RelationManager
{
    protected static string $relationship = 'externalRepairs';

    protected static ?string $title = 'Service externo';

    public function table(Table $table): Table
    {
        return ExternalRepairsTable::configure($table)
            ->filters([]);
    }
}
