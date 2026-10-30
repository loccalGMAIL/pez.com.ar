<?php

namespace App\Filament\Resources\Payments\Pages;

use App\Filament\Resources\Payments\Actions\PaymentActions;
use App\Filament\Resources\Payments\PaymentResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewPayment extends ViewRecord
{
    protected static string $resource = PaymentResource::class;

    public function getTitle(): string
    {
        // The resource's record title is its number (P-00100, T-00001, R-00001).
        return "Pago {$this->getRecordTitle()}";
    }

    protected function getHeaderActions(): array
    {
        return [
            PaymentActions::shareReceiptOnWhatsApp(),
            PaymentActions::downloadReceipt(),
            EditAction::make(),
            DeleteAction::make()
                ->modalDescription('Los cargos que cancelaba vuelven a quedar pendientes.'),
        ];
    }
}
