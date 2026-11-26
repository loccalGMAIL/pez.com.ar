<?php

namespace App\Filament\Resources\FollowUps\Tables;

use App\Enums\FollowUpKind;
use App\Enums\FollowUpStatus;
use App\Filament\Resources\FollowUps\Actions\FollowUpActions;
use App\Models\FollowUp;
use Filament\Actions\ActionGroup;
use Filament\Actions\ViewAction;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\Layout\Panel;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;
use Livewire\Component;

class FollowUpsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['client.primaryContact', 'workOrder', 'quote', 'charge']))
            // Pending ones from the oldest due date; the rest from the latest.
            ->defaultSort('due_on', fn (Component $livewire) => ($livewire->activeTab ?? null) === 'pendientes' ? 'asc' : 'desc')
            ->emptyStateHeading('No hay seguimientos')
            // Stacked on the phone so the status and the WhatsApp button stay in view;
            // each row unfolds the message it was sent with (or will be sent with).
            ->columns([
                Split::make([
                    TextColumn::make('due_on')
                        ->label('Fecha')
                        ->date('d/m/Y')
                        ->description(fn (FollowUp $record) => $record->status === FollowUpStatus::Pending ? self::relativeDay($record) : null)
                        ->color(fn (FollowUp $record) => $record->status === FollowUpStatus::Pending && $record->due_on->isPast() && ! $record->due_on->isToday() ? 'danger' : null)
                        ->sortable()
                        ->grow(false),
                    TextColumn::make('client.business_name')
                        ->label('Cliente')
                        ->weight(FontWeight::SemiBold)
                        ->description(fn (FollowUp $record) => $record->client->whatsappPhone() ?? 'Sin teléfono')
                        ->searchable(),
                    TextColumn::make('subject')
                        ->label('Motivo')
                        ->state(fn (FollowUp $record) => $record->subject())
                        ->description(fn (FollowUp $record) => 'Del '.$record->occurred_on->format('d/m/Y'))
                        ->wrap(),
                    TextColumn::make('status')
                        ->label('Estado')
                        ->badge()
                        ->description(fn (FollowUp $record) => $record->sent_at ? 'El '.$record->sent_at->format('d/m/Y H:i') : null)
                        ->grow(false),
                ])->from('md'),
                Panel::make([
                    TextColumn::make('message')
                        ->label('Mensaje')
                        ->state(fn (FollowUp $record) => new HtmlString(nl2br(e($record->message ?? $record->composeMessage()))))
                        ->color('gray'),
                ])->collapsible(),
            ])
            ->filters([
                SelectFilter::make('kind')
                    ->label('Tipo')
                    ->options(FollowUpKind::class),
                SelectFilter::make('client')
                    ->label('Cliente')
                    ->relationship('client', 'business_name')
                    ->searchable()
                    ->preload(),
            ])
            ->recordActions([
                FollowUpActions::send()->button(),
                ActionGroup::make([
                    ViewAction::make(),
                    FollowUpActions::postpone(),
                    FollowUpActions::skip(),
                ]),
            ]);
    }

    /**
     * "Hoy", "Mañana", "En 3 días", "Hace 2 días".
     */
    private static function relativeDay(FollowUp $followUp): string
    {
        $days = (int) today()->diffInDays($followUp->due_on, false);

        return match (true) {
            $days === 0 => 'Hoy',
            $days === 1 => 'Mañana',
            $days === -1 => 'Ayer',
            $days > 1 => "En {$days} días",
            default => 'Hace '.abs($days).' días',
        };
    }
}
