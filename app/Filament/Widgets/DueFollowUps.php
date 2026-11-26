<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\FollowUps\Actions\FollowUpActions;
use App\Filament\Resources\FollowUps\FollowUpResource;
use App\Models\FollowUp;
use Filament\Actions\ActionGroup;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\Layout\Stack;
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
            // Stacked on the phone, so the send button stays in view instead of off to the right.
            ->columns([
                Split::make([
                    Stack::make([
                        TextColumn::make('client.business_name')
                            ->label('Cliente')
                            ->weight(FontWeight::SemiBold),
                        TextColumn::make('phone')
                            ->state(fn (FollowUp $record) => $record->client->whatsappPhone())
                            ->placeholder('Sin teléfono')
                            ->color('gray'),
                    ]),
                    TextColumn::make('subject')
                        ->label('Motivo')
                        ->state(fn (FollowUp $record) => $record->subject())
                        ->wrap(),
                    TextColumn::make('occurred_on')
                        ->label('Entrega / venta')
                        ->formatStateUsing(fn (FollowUp $record) => $record->occurred_on->format('d/m/Y').' · '.$record->occurred_on->diffForHumans())
                        ->color('gray'),
                ])->from('md'),
            ])
            ->recordUrl(fn (FollowUp $record) => FollowUpResource::getUrl('view', ['record' => $record]))
            ->recordActions([
                FollowUpActions::send()->button(),
                ActionGroup::make([
                    FollowUpActions::postpone(),
                    FollowUpActions::skip(),
                ]),
            ]);
    }
}
