<?php

namespace App\Filament\Resources\Subscriptions\Actions;

use App\Enums\ChargeStatus;
use App\Filament\Resources\Charges\ChargeResource;
use App\Models\Subscription;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

class SubscriptionActions
{
    /**
     * Creates this month's charge before the billing day, for clients who pay
     * ahead. The daily billing then skips it, so it is never charged twice.
     */
    public static function billNow(): Action
    {
        return Action::make('billNow')
            ->label('Generar cargo del mes')
            ->icon(Heroicon::OutlinedDocumentPlus)
            ->color('primary')
            ->visible(fn (Subscription $record) => $record->canBillEarly(today()))
            ->requiresConfirmation()
            ->modalHeading('Generar cargo del mes')
            ->modalDescription(fn (Subscription $record) => 'Se crea hoy el cargo de '.ucfirst(today()->translatedFormat('F Y'))
                ." del abono {$record->concept}, sin esperar al día {$record->billing_day}. Ese día no se vuelve a generar.")
            ->modalSubmitActionLabel('Generar cargo')
            ->action(function (Subscription $record) {
                $charge = $record->bill(today());

                if ($charge === null) {
                    Notification::make()->title('El cargo de este mes ya estaba generado')->warning()->send();

                    return;
                }

                Notification::make()
                    ->title('Cargo generado')
                    ->body($charge->status === ChargeStatus::Draft ? 'Es un abono variable: confirmá el monto desde el cargo.' : null)
                    ->success()
                    ->actions([
                        Action::make('viewCharge')
                            ->label('Ver cargo')
                            ->url(ChargeResource::getUrl('view', ['record' => $charge])),
                    ])
                    ->send();
            });
    }
}
