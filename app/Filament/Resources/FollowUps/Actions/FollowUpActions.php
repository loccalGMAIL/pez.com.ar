<?php

namespace App\Filament\Resources\FollowUps\Actions;

use App\Enums\FollowUpStatus;
use App\Models\FollowUp;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;

/**
 * Actions on after-sales follow-ups, shared by the dashboard, their list and the client page.
 */
class FollowUpActions
{
    /**
     * A plain link, so the phone opens WhatsApp from the tap itself (a popup
     * opened after a server round trip gets blocked). An already sent one opens
     * again with the text it was sent with, in case WhatsApp was closed before sending.
     */
    public static function send(): Action
    {
        return Action::make('sendFollowUp')
            ->label(fn (FollowUp $record) => $record->status === FollowUpStatus::Sent ? 'Abrir de nuevo en WhatsApp' : 'Enviar por WhatsApp')
            ->icon(Heroicon::OutlinedChatBubbleLeftRight)
            ->color(fn (FollowUp $record) => $record->status === FollowUpStatus::Sent ? 'gray' : 'success')
            ->visible(fn (FollowUp $record) => in_array($record->status, [FollowUpStatus::Pending, FollowUpStatus::Sent], true))
            ->url(fn (FollowUp $record) => route('filament.admin.follow-ups.whatsapp', $record), shouldOpenInNewTab: true);
    }

    /**
     * Moves a pending one to another day, or proposes again one that was skipped.
     */
    public static function postpone(): Action
    {
        return Action::make('postponeFollowUp')
            ->label(fn (FollowUp $record) => $record->status === FollowUpStatus::Skipped ? 'Volver a proponer' : 'Posponer')
            ->icon(Heroicon::OutlinedClock)
            ->color('gray')
            ->visible(fn (FollowUp $record) => in_array($record->status, [FollowUpStatus::Pending, FollowUpStatus::Skipped], true))
            ->modalHeading(fn (FollowUp $record) => $record->status === FollowUpStatus::Skipped ? 'Volver a proponer el seguimiento' : 'Posponer seguimiento')
            ->modalWidth('sm')
            ->modalSubmitActionLabel('Guardar')
            ->fillForm(fn (FollowUp $record) => ['due_on' => ($record->status === FollowUpStatus::Skipped ? today() : today()->addWeekdays(2))->toDateString()])
            ->schema([
                DatePicker::make('due_on')
                    ->label('Mostrarlo el')
                    ->minDate(fn (FollowUp $record) => $record->status === FollowUpStatus::Skipped ? today() : today()->addDay())
                    ->required(),
            ])
            ->action(fn (FollowUp $record, array $data) => $record->postpone(Carbon::parse($data['due_on'])));
    }

    public static function skip(): Action
    {
        return Action::make('skipFollowUp')
            ->label('Omitir')
            ->icon(Heroicon::OutlinedXMark)
            ->color('gray')
            ->visible(fn (FollowUp $record) => $record->status === FollowUpStatus::Pending)
            ->action(fn (FollowUp $record) => $record->skip());
    }
}
