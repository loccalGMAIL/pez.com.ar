<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Clients\Actions\FollowUpActions;
use App\Models\FollowUp;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/**
 * After-sales messages to send today. Polls so a follow-up sent from another
 * tab (WhatsApp opens in a new one) leaves the list by itself.
 */
class DueFollowUps extends TableWidget
{
    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Seguimientos para hoy';

    public static function canView(): bool
    {
        return FollowUp::isEnabled();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => FollowUp::query()->due()->with(['client.primaryContact', 'workOrder', 'quote', 'charge']))
            ->defaultSort('due_on')
            ->poll('10s')
            ->emptyStateHeading('No hay seguimientos para enviar')
            ->emptyStateDescription('Aparecen acá a los días hábiles configurados de entregar un trabajo o hacer una venta.')
            ->columns([
                TextColumn::make('client.business_name')
                    ->label('Cliente')
                    ->description(fn (FollowUp $record) => $record->client->whatsappPhone() ?? 'Sin teléfono'),
                TextColumn::make('subject')
                    ->label('Motivo')
                    ->state(fn (FollowUp $record) => $record->subject())
                    ->wrap(),
                TextColumn::make('occurred_on')
                    ->label('Entrega / venta')
                    ->date('d/m/Y')
                    ->description(fn (FollowUp $record) => $record->occurred_on->diffForHumans()),
            ])
            ->recordActions([
                FollowUpActions::send(),
                FollowUpActions::postpone(),
                FollowUpActions::skip(),
            ]);
    }
}
