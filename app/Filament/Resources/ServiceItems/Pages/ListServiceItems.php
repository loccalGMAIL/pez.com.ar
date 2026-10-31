<?php

namespace App\Filament\Resources\ServiceItems\Pages;

use App\Enums\CatalogItemType;
use App\Filament\Resources\ServiceItems\ServiceItemResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListServiceItems extends ListRecords
{
    protected static string $resource = ServiceItemResource::class;

    public function getDefaultActiveTab(): string|int|null
    {
        return CatalogItemType::Service->value;
    }

    /**
     * One tab per catalog type; the key is the stored type, so new items start in the open tab.
     *
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        return collect(CatalogItemType::cases())
            ->mapWithKeys(fn (CatalogItemType $type) => [
                $type->value => Tab::make($type === CatalogItemType::Service ? 'Servicios' : 'Productos')
                    ->modifyQueryUsing(fn (Builder $query) => $query->where('type', $type)),
            ])
            ->all();
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->url(fn () => ServiceItemResource::getUrl('create', ['type' => $this->activeTab])),
        ];
    }
}
