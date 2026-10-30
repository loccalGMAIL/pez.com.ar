<?php

namespace App\Filament\Resources\Clients\RelationManagers;

use App\Filament\Resources\Subscriptions\Schemas\SubscriptionForm;
use App\Filament\Resources\Subscriptions\Tables\SubscriptionsTable;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class SubscriptionsRelationManager extends RelationManager
{
    protected static string $relationship = 'subscriptions';

    protected static ?string $title = 'Abonos';

    protected static ?string $modelLabel = 'abono';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        return SubscriptionForm::configure($schema);
    }

    public function table(Table $table): Table
    {
        return SubscriptionsTable::configure($table)
            ->filters([])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
