<?php

namespace App\Filament\Resources\Charges\Pages;

use App\Filament\Resources\Charges\ChargeResource;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditCharge extends EditRecord
{
    protected static string $resource = ChargeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return ChargeResource::getUrl('view', ['record' => $this->getRecord()]);
    }
}
