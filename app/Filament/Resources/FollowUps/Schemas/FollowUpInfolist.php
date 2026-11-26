<?php

namespace App\Filament\Resources\FollowUps\Schemas;

use App\Enums\FollowUpStatus;
use App\Filament\Resources\Clients\ClientResource;
use App\Filament\Resources\FollowUps\FollowUpResource;
use App\Models\FollowUp;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;

class FollowUpInfolist
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
                            ->url(fn (FollowUp $record) => ClientResource::getUrl('view', ['record' => $record->client_id])),
                        TextEntry::make('phone')
                            ->label('WhatsApp')
                            ->state(fn (FollowUp $record) => $record->client->whatsappPhone())
                            ->placeholder('Sin teléfono'),
                        TextEntry::make('status')
                            ->label('Estado')
                            ->badge(),
                        TextEntry::make('kind')
                            ->label('Tipo'),
                        TextEntry::make('subject')
                            ->label('Motivo')
                            ->state(fn (FollowUp $record) => $record->subject())
                            ->url(fn (FollowUp $record) => FollowUpResource::subjectUrl($record))
                            ->columnSpan(2),
                        TextEntry::make('occurred_on')
                            ->label('Entrega / venta')
                            ->date('d/m/Y'),
                        TextEntry::make('due_on')
                            ->label('Fecha del seguimiento')
                            ->date('d/m/Y'),
                        TextEntry::make('sent_at')
                            ->label('Enviado')
                            ->dateTime('d/m/Y H:i')
                            ->placeholder('—'),
                    ]),
                Section::make(fn (FollowUp $record) => match ($record->status) {
                    FollowUpStatus::Sent => 'Mensaje enviado',
                    FollowUpStatus::Pending => 'Mensaje que se va a enviar',
                    default => 'Mensaje',
                })
                    ->description(fn (FollowUp $record) => match ($record->status) {
                        FollowUpStatus::Sent => 'Texto con el que se abrió WhatsApp; el envío final se hizo desde WhatsApp.',
                        FollowUpStatus::Pending => 'Sale de los textos de Configuración → Seguimientos y se guarda tal cual al enviarlo.',
                        default => 'No se envió.',
                    })
                    ->schema([
                        TextEntry::make('message')
                            ->hiddenLabel()
                            ->state(fn (FollowUp $record) => new HtmlString(nl2br(e($record->message ?? $record->composeMessage())))),
                    ]),
            ]);
    }
}
