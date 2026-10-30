<?php

namespace App\Filament\Resources\Payments\Schemas;

use App\Enums\PaymentMethod;
use App\Filament\Resources\Payments\Actions\PaymentActions;
use App\Filament\Resources\Payments\PaymentResource;
use App\Models\Charge;
use App\Models\Client;
use App\Models\Payment;
use App\Support\QuotePdf;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class PaymentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Select::make('client_id')
                    ->label('Cliente')
                    ->relationship('client', 'business_name')
                    ->searchable(['business_name', 'trade_name'])
                    ->preload()
                    ->default(fn () => request()->query('client'))
                    ->required()
                    ->live()
                    ->disabledOn('edit')
                    ->hidden(fn ($livewire) => $livewire instanceof RelationManager)
                    ->columnSpanFull(),
                ...self::fields(),
                CheckboxList::make('charge_ids')
                    ->label('Imputar a')
                    ->helperText('Si no elegís ninguno, se imputa a los cargos más antiguos. Lo que sobre queda como saldo a favor.')
                    ->options(fn (Get $get, $livewire) => self::openChargeOptions(
                        $livewire instanceof RelationManager ? $livewire->getOwnerRecord()->getKey() : $get('client_id'),
                    ))
                    ->visibleOn('create')
                    ->dehydrated(false)
                    ->columnSpanFull(),
            ]);
    }

    /**
     * Fields of a payment, also used by the "register payment" actions.
     *
     * @return array<Component>
     */
    public static function fields(): array
    {
        return [
            DatePicker::make('paid_on')
                ->label('Fecha')
                ->native(false)
                ->displayFormat('d/m/Y')
                ->default(today())
                ->required(),
            TextInput::make('amount')
                ->label('Monto')
                ->numeric()
                ->minValue(0.01)
                ->prefix('$')
                ->required()
                ->disabledOn('edit'),
            Select::make('method')
                ->label('Medio de pago')
                ->options(PaymentMethod::class)
                ->default(PaymentMethod::Transfer)
                ->required(),
            TextInput::make('reference')
                ->label('Referencia')
                ->placeholder('N° de operación, recibo…')
                ->maxLength(255),
            FileUpload::make('receipt_path')
                ->label('Comprobante')
                ->disk('local')
                ->directory('payments')
                ->visibility('private')
                ->acceptedFileTypes(['image/*', 'application/pdf'])
                ->maxSize(10240)
                ->openable()
                ->downloadable(),
            Textarea::make('notes')
                ->label('Notas')
                ->rows(2),
        ];
    }

    /**
     * Confirmation with shortcuts to the receipt, shown wherever a payment is registered.
     */
    public static function notifyRegistered(Payment $payment): void
    {
        Notification::make()
            ->title("Pago registrado — recibo {$payment->code}")
            ->success()
            ->actions([
                Action::make('shareReceipt')
                    ->label('Enviar por WhatsApp')
                    ->url(PaymentActions::shareReceiptOnWhatsApp()->record($payment)->getUrl(), shouldOpenInNewTab: true),
                Action::make('viewPayment')
                    ->label('Ver pago')
                    ->url(PaymentResource::getUrl('view', ['record' => $payment])),
            ])
            ->send();
    }

    /**
     * @return array<int, string>
     */
    public static function openChargeOptions(mixed $clientId): array
    {
        if (blank($clientId)) {
            return [];
        }

        return Client::withTrashed()->find($clientId)?->charges()->open()->orderBy('due_on')->orderBy('id')->get()
            ->mapWithKeys(fn (Charge $charge) => [
                $charge->id => "{$charge->concept} — debe ".QuotePdf::money($charge->outstanding())." (vence {$charge->due_on->format('d/m/Y')})",
            ])
            ->all() ?? [];
    }
}
