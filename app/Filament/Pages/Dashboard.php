<?php

namespace App\Filament\Pages;

use App\Filament\Resources\WorkOrders\WorkOrderResource;
use Filament\Actions\Action;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Support\Icons\Heroicon;

class Dashboard extends BaseDashboard
{
    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('deviceIntake')
                ->label('Crear orden de trabajo')
                ->icon(Heroicon::OutlinedWrenchScrewdriver)
                ->url(WorkOrderResource::getUrl('create')),
        ];
    }
}
