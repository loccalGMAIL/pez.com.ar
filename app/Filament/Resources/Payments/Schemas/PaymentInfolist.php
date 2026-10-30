<?php

namespace App\Filament\Resources\Payments\Schemas;

use App\Filament\Resources\Charges\ChargeResource;
use App\Filament\Resources\Clients\ClientResource;
use App\Models\Payment;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Storage;

class PaymentInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make()
                    ->columns(4)
                    ->schema([
                        TextEntry::make('client.business_name')
                            ->label('Cliente')
                            ->url(fn (Payment $record) => ClientResource::getUrl('view', ['record' => $record->client_id])),
                        TextEntry::make('paid_on')
                            ->label('Fecha')
                            ->date('d/m/Y'),
                        TextEntry::make('method')
                            ->label('Medio'),
                        TextEntry::make('amount')
                            ->label('Monto')
                            ->money('ARS', locale: 'es_AR'),
                        TextEntry::make('reference')
                            ->label('Referencia')
                            ->placeholder('—'),
                        TextEntry::make('unallocated')
                            ->label('Saldo a favor')
                            ->state(fn (Payment $record) => $record->unallocated())
                            ->money('ARS', locale: 'es_AR'),
                        TextEntry::make('receipt_path')
                            ->label('Comprobante')
                            ->formatStateUsing(fn (string $state) => basename($state))
                            ->url(fn (Payment $record) => $record->receipt_path ? Storage::disk('local')->temporaryUrl($record->receipt_path, now()->addMinutes(30)) : null, shouldOpenInNewTab: true)
                            ->placeholder('—'),
                        TextEntry::make('notes')
                            ->label('Notas')
                            ->placeholder('—'),
                    ]),
                Section::make('Imputado a')
                    ->schema([
                        RepeatableEntry::make('allocations')
                            ->hiddenLabel()
                            ->placeholder('Todavía no se imputó a ningún cargo')
                            ->table([
                                TableColumn::make('Cargo'),
                                TableColumn::make('Imputado'),
                            ])
                            ->schema([
                                TextEntry::make('charge.concept')
                                    ->url(fn ($record) => ChargeResource::getUrl('view', ['record' => $record->charge_id])),
                                TextEntry::make('amount')
                                    ->money('ARS', locale: 'es_AR'),
                            ]),
                    ]),
            ]);
    }
}
