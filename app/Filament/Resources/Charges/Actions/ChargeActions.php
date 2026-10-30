<?php

namespace App\Filament\Resources\Charges\Actions;

use App\Enums\ChargeStatus;
use App\Filament\Resources\Payments\Schemas\PaymentForm;
use App\Models\Charge;
use App\Models\Payment;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Support\Icons\Heroicon;

class ChargeActions
{
    public static function confirm(): Action
    {
        return Action::make('confirm')
            ->label('Confirmar')
            ->icon(Heroicon::OutlinedCheck)
            ->color('primary')
            ->visible(fn (Charge $record) => $record->status === ChargeStatus::Draft)
            ->modalHeading('Confirmar cargo')
            ->modalDescription(fn (Charge $record) => $record->notes)
            ->fillForm(fn (Charge $record) => ['amount' => $record->amount])
            ->schema([
                TextInput::make('amount')
                    ->label('Monto a cobrar')
                    ->numeric()
                    ->minValue(0)
                    ->prefix('$')
                    ->required(),
            ])
            ->action(fn (Charge $record, array $data) => $record->confirm((float) $data['amount']))
            ->successNotificationTitle('Cargo confirmado');
    }

    public static function registerPayment(): Action
    {
        return Action::make('registerPayment')
            ->label('Registrar pago')
            ->icon(Heroicon::OutlinedBanknotes)
            ->color('success')
            ->visible(fn (Charge $record) => $record->status->isOpen())
            ->fillForm(fn (Charge $record) => ['amount' => $record->outstanding(), 'paid_on' => today()])
            ->schema(PaymentForm::fields())
            ->action(fn (Charge $record, array $data) => Payment::register([...$data, 'client_id' => $record->client_id], [$record->id]))
            ->successNotificationTitle('Pago registrado');
    }

    public static function cancel(): Action
    {
        return Action::make('cancel')
            ->label('Anular')
            ->icon(Heroicon::OutlinedXCircle)
            ->color('danger')
            ->visible(fn (Charge $record) => $record->status !== ChargeStatus::Cancelled)
            ->requiresConfirmation()
            ->modalDescription('El cargo deja de deberse. Si tenía pagos imputados, pasan a saldo a favor del cliente.')
            ->action(fn (Charge $record) => $record->cancel())
            ->successNotificationTitle('Cargo anulado');
    }
}
