<?php

namespace App\Filament\Resources\Clients\RelationManagers;

use App\Filament\Resources\Charges\ChargeResource;
use App\Filament\Resources\Charges\Schemas\ChargeForm;
use App\Filament\Resources\Charges\Tables\ChargesTable;
use Filament\Actions\CreateAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

/**
 * The client's account: every charge, with what is still owed.
 */
class ChargesRelationManager extends RelationManager
{
    protected static string $relationship = 'charges';

    protected static ?string $title = 'Cuenta corriente';

    protected static ?string $modelLabel = 'cargo';

    protected static ?string $relatedResource = ChargeResource::class;

    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        return ChargeForm::configure($schema);
    }

    public function table(Table $table): Table
    {
        return ChargesTable::configure($table)
            ->defaultSort('issued_on', 'desc')
            ->headerActions([
                CreateAction::make()
                    ->label('Nuevo cargo'),
            ]);
    }
}
