<?php

namespace App\Filament\Resources\Clients\RelationManagers;

use App\Filament\Resources\Devices\DeviceResource;
use App\Filament\Resources\Devices\Schemas\DeviceForm;
use App\Filament\Resources\Devices\Tables\DevicesTable;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class DevicesRelationManager extends RelationManager
{
    protected static string $relationship = 'devices';

    protected static ?string $title = 'Equipos';

    protected static ?string $modelLabel = 'equipo';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        return DeviceForm::configure($schema);
    }

    public function table(Table $table): Table
    {
        return DevicesTable::configure($table)
            // The client column is hidden here, so it can't be the sort.
            ->defaultSort('type')
            ->filters([])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                ViewAction::make()
                    ->url(fn ($record) => DeviceResource::getUrl('view', ['record' => $record])),
                EditAction::make(),
            ])
            ->toolbarActions([]);
    }
}
