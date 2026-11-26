<?php

namespace App\Filament\Resources\FollowUps\Pages;

use App\Filament\Resources\FollowUps\Actions\FollowUpActions;
use App\Filament\Resources\FollowUps\FollowUpResource;
use App\Models\FollowUp;
use Filament\Resources\Pages\ViewRecord;

class ViewFollowUp extends ViewRecord
{
    protected static string $resource = FollowUpResource::class;

    public function getTitle(): string
    {
        /** @var FollowUp $followUp */
        $followUp = $this->getRecord();

        return "Seguimiento a {$followUp->client->business_name}";
    }

    protected function getHeaderActions(): array
    {
        return [
            FollowUpActions::send(),
            FollowUpActions::postpone(),
            FollowUpActions::skip(),
        ];
    }
}
