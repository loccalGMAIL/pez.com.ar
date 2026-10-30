<?php

namespace App\Filament\Resources\WorkOrders\RelationManagers;

use App\Filament\Resources\ExternalRepairs\Schemas\ExternalRepairForm;
use App\Filament\Resources\ExternalRepairs\Tables\ExternalRepairsTable;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class ExternalRepairsRelationManager extends RelationManager
{
    protected static string $relationship = 'externalRepairs';

    protected static ?string $title = 'Service externo';

    protected static ?string $modelLabel = 'envío a service';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        return ExternalRepairForm::configure($schema);
    }

    public function table(Table $table): Table
    {
        return ExternalRepairsTable::configure($table)
            ->filters([])
            ->headerActions([
                CreateAction::make()
                    ->label('Enviar a service'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
