<?php

namespace App\Filament\Resources\Clients\Pages;

use App\Filament\Resources\Clients\ClientResource;
use App\Filament\Resources\Payments\Schemas\PaymentForm;
use App\Models\Client;
use App\Models\Payment;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

class ViewClient extends ViewRecord
{
    protected static string $resource = ClientResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('registerPayment')
                ->label('Registrar pago')
                ->icon(Heroicon::OutlinedBanknotes)
                ->color('success')
                ->fillForm(fn (Client $record) => ['amount' => max(0, $record->balance()) ?: null, 'paid_on' => today()])
                ->schema(fn (Client $record) => [
                    ...PaymentForm::fields(),
                    CheckboxList::make('charge_ids')
                        ->label('Imputar a')
                        ->helperText('Si no elegís ninguno, se imputa a los cargos más antiguos. Lo que sobre queda como saldo a favor.')
                        ->options(PaymentForm::openChargeOptions($record->getKey())),
                ])
                ->action(function (Client $record, array $data) {
                    $chargeIds = $data['charge_ids'] ?? [];
                    unset($data['charge_ids']);

                    Payment::register([...$data, 'client_id' => $record->getKey()], $chargeIds);
                })
                ->successNotificationTitle('Pago registrado'),
            EditAction::make(),
        ];
    }
}
