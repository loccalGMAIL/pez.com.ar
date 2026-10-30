<?php

namespace App\Filament\Resources\Charges\Pages;

use App\Filament\Resources\Charges\ChargeResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCharge extends CreateRecord
{
    protected static string $resource = ChargeResource::class;

    protected function getRedirectUrl(): string
    {
        return ChargeResource::getUrl('view', ['record' => $this->getRecord()]);
    }
}
