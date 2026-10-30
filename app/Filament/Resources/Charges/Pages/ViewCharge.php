<?php

namespace App\Filament\Resources\Charges\Pages;

use App\Filament\Resources\Charges\Actions\ChargeActions;
use App\Filament\Resources\Charges\ChargeResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewCharge extends ViewRecord
{
    protected static string $resource = ChargeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ChargeActions::confirm(),
            ChargeActions::registerPayment(),
            EditAction::make(),
            ChargeActions::cancel(),
        ];
    }
}
