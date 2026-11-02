<?php

namespace App\Filament\Resources\Payments\Pages;

use App\Filament\Resources\Payments\Actions\PaymentActions;
use App\Filament\Resources\Payments\PaymentResource;
use App\Models\Payment;
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
            PaymentActions::issueInvoice(),
            PaymentActions::viewInvoice(),
            PaymentActions::shareReceiptOnWhatsApp(),
            PaymentActions::downloadReceipt(),
            EditAction::make(),
            DeleteAction::make()
                // An invoiced payment is deleted once its invoice is annulled with a credit note.
                ->hidden(fn (Payment $record) => (bool) $record->invoice?->isInForce())
                ->modalDescription('Los cargos que cancelaba vuelven a quedar pendientes.'),
        ];
    }
}
