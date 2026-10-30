<?php

namespace App\Filament\Resources\Clients\RelationManagers;

use App\Filament\Resources\Quotes\QuoteResource;
use App\Filament\Resources\Quotes\Tables\QuotesTable;
use Filament\Actions\CreateAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;

class QuotesRelationManager extends RelationManager
{
    protected static string $relationship = 'quotes';

    protected static ?string $title = 'Presupuestos';

    protected static ?string $relatedResource = QuoteResource::class;

    public function table(Table $table): Table
    {
        return QuotesTable::configure($table)
            ->headerActions([
                CreateAction::make()
                    ->url(fn () => QuoteResource::getUrl('create', ['client' => $this->getOwnerRecord()->getKey()])),
            ]);
    }
}
