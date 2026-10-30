<?php

namespace App\Filament\Resources\WorkOrders\Pages;

use App\Enums\WorkOrderStatus;
use App\Filament\Resources\WorkOrders\WorkOrderResource;
use App\Models\WorkOrder;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

class ViewWorkOrder extends ViewRecord
{
    protected static string $resource = WorkOrderResource::class;

    public function getTitle(): string
    {
        return "Trabajo {$this->getRecord()->code}";
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('changeStatus')
                ->label('Cambiar estado')
                ->icon(Heroicon::OutlinedArrowPath)
                ->fillForm(fn (WorkOrder $record) => ['status' => $record->status])
                ->schema([
                    Select::make('status')
                        ->label('Estado')
                        ->options(WorkOrderStatus::class)
                        ->required(),
                ])
                ->action(fn (WorkOrder $record, array $data) => $record->update(['status' => $data['status']]))
                ->successNotificationTitle('Estado actualizado'),
            EditAction::make(),
        ];
    }
}
