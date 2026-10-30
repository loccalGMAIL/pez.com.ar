<?php

namespace App\Filament\Resources\Clients\RelationManagers;

use App\Filament\Resources\Payments\PaymentResource;
use App\Filament\Resources\Payments\Tables\PaymentsTable;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;

class PaymentsRelationManager extends RelationManager
{
    protected static string $relationship = 'payments';

    protected static ?string $title = 'Pagos';

    protected static ?string $relatedResource = PaymentResource::class;

    public function table(Table $table): Table
    {
        return PaymentsTable::configure($table)
            ->filters([]);
    }
}
