<?php

namespace App\Filament\Resources\FollowUps\Pages;

use App\Enums\FollowUpStatus;
use App\Filament\Resources\FollowUps\FollowUpResource;
use App\Models\FollowUp;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListFollowUps extends ListRecords
{
    protected static string $resource = FollowUpResource::class;

    public function getDefaultActiveTab(): string|int|null
    {
        return 'pendientes';
    }

    /**
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        return [
            'pendientes' => Tab::make('Pendientes')
                ->badge(fn () => FollowUp::query()->pending()->count() ?: null)
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', FollowUpStatus::Pending)),
            'enviados' => Tab::make('Enviados')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', FollowUpStatus::Sent)),
            'omitidos' => Tab::make('Omitidos y anulados')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('status', [FollowUpStatus::Skipped, FollowUpStatus::Cancelled])),
            'todos' => Tab::make('Todos'),
        ];
    }
}
