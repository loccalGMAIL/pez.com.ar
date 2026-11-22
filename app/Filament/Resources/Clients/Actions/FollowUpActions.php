<?php

namespace App\Filament\Resources\Clients\Actions;

use App\Enums\FollowUpStatus;
use App\Models\FollowUp;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;

/**
 * Actions on after-sales follow-ups, shared by the dashboard and the client page.
 */
class FollowUpActions
{
    /**
     * A plain link, so the phone opens WhatsApp from the tap itself (a popup
     * opened after a server round trip gets blocked).
     */
    public static function send(): Action
    {
        return Action::make('sendFollowUp')
            ->label('Enviar por WhatsApp')
            ->icon(Heroicon::OutlinedChatBubbleLeftRight)
            ->color('success')
            ->visible(fn (FollowUp $record) => $record->status === FollowUpStatus::Pending)
            ->url(fn (FollowUp $record) => route('filament.admin.follow-ups.whatsapp', $record), shouldOpenInNewTab: true);
    }

    public static function postpone(): Action
    {
        return Action::make('postponeFollowUp')
            ->label('Posponer')
            ->icon(Heroicon::OutlinedClock)
            ->color('gray')
            ->visible(fn (FollowUp $record) => $record->status === FollowUpStatus::Pending)
            ->modalHeading('Posponer seguimiento')
            ->modalWidth('sm')
            ->modalSubmitActionLabel('Posponer')
            ->fillForm(fn () => ['due_on' => today()->addWeekdays(2)->toDateString()])
            ->schema([
                DatePicker::make('due_on')
                    ->label('Mostrarlo de nuevo el')
                    ->minDate(today()->addDay())
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
