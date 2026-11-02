<?php

namespace App\Filament\Resources\Payments\Pages;

use App\Filament\Resources\Payments\Actions\PaymentActions;
use App\Filament\Resources\Payments\PaymentResource;
use App\Models\Payment;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

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
            ActionGroup::make([
                PaymentActions::downloadInvoice(),
                PaymentActions::sendInvoice(),
                PaymentActions::shareInvoiceOnWhatsApp(),
            ])
                ->label('Factura')
                ->icon(Heroicon::OutlinedDocumentText)
                ->color('primary')
                ->button(),
            PaymentActions::shareReceiptOnWhatsApp(),
            PaymentActions::downloadReceipt(),
            EditAction::make(),
            DeleteAction::make()
                // An invoiced payment is undone with a credit note, not deleted.
                ->hidden(fn (Payment $record) => (bool) $record->invoice?->isAuthorized())
                ->modalDescription('Los cargos que cancelaba vuelven a quedar pendientes.'),
        ];
    }
}
