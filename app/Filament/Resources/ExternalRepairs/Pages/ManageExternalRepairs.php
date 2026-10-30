<?php

namespace App\Filament\Resources\ExternalRepairs\Pages;

use App\Filament\Resources\ExternalRepairs\ExternalRepairResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageExternalRepairs extends ManageRecords
{
    protected static string $resource = ExternalRepairResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
