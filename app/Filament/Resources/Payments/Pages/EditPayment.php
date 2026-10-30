<?php

namespace App\Filament\Resources\Payments\Pages;

use App\Filament\Resources\Payments\PaymentResource;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

/**
 * Date, method, reference and receipt only: the amount and client are fixed
 * because the payment is already allocated. To change them, delete it and
 * register it again.
 */
class EditPayment extends EditRecord
{
    protected static string $resource = PaymentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return PaymentResource::getUrl('view', ['record' => $this->getRecord()]);
    }
}
