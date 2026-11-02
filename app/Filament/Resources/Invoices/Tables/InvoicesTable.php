<?php

namespace App\Filament\Resources\Invoices\Tables;

use App\Enums\InvoiceStatus;
use App\Filament\Resources\Invoices\Actions\InvoiceActions;
use App\Filament\Resources\Payments\PaymentResource;
use App\Models\Invoice;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class InvoicesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['payment', 'creditNote']))
            ->columns([
                TextColumn::make('title')
                    ->label('Comprobante')
                    ->searchable(query: fn (Builder $query, string $search) => $query->where('number', (int) preg_replace('/\D/', '', $search))),
                TextColumn::make('issued_on')
                    ->label('Fecha')
                    ->date('d/m/Y')
                    ->placeholder('—')
                    ->sortable(),
                TextColumn::make('recipient_name')
                    ->label('Cliente')
                    ->searchable(),
                TextColumn::make('total')
                    ->label('Total')
                    ->money('ARS', locale: 'es_AR')
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn (Invoice $record) => $record->isCredited() ? 'Anulada' : $record->status->getLabel())
                    ->color(fn (Invoice $record) => $record->isCredited() ? 'gray' : $record->status->getColor()),
                TextColumn::make('cae')
                    ->label('CAE')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('payment.code')
                    ->label('Recibo')
                    ->url(fn (Invoice $record) => $record->payment_id ? PaymentResource::getUrl('view', ['record' => $record->payment_id]) : null)
                    ->placeholder('—'),
                TextColumn::make('environment')
                    ->label('Ambiente')
                    ->formatStateUsing(fn (string $state) => $state === 'produccion' ? 'Producción' : 'Homologación')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Estado')
                    ->options(InvoiceStatus::class),
                SelectFilter::make('environment')
                    ->label('Ambiente')
                    ->options(['produccion' => 'Producción', 'homologacion' => 'Homologación'])
                    ->default(config('arca.environment')),
            ])
            ->recordActions([
                ViewAction::make(),
                InvoiceActions::download(),
            ]);
    }
}
