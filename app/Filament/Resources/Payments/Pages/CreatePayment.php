<?php

namespace App\Filament\Resources\Payments\Pages;

use App\Filament\Resources\Payments\PaymentResource;
use App\Models\Payment;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreatePayment extends CreateRecord
{
    protected static string $resource = PaymentResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return Payment::register($data, $this->data['charge_ids'] ?? []);
    }

    protected function getRedirectUrl(): string
    {
        return PaymentResource::getUrl('view', ['record' => $this->getRecord()]);
    }
}
