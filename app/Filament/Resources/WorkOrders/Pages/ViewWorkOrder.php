<?php

namespace App\Filament\Resources\WorkOrders\Pages;

use App\Enums\WorkOrderStatus;
use App\Filament\Resources\WorkOrders\WorkOrderResource;
use App\Models\WorkOrder;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
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
            Action::make('charge')
                ->label('Cobrar')
                ->icon(Heroicon::OutlinedReceiptPercent)
                ->color('gray')
                ->modalHeading('Cargar el trabajo en la cuenta corriente')
                ->modalDescription(fn (WorkOrder $record) => $record->quote_id
                    ? "Este trabajo viene del presupuesto {$record->quote?->code}: si ya se cobró por ahí, cargá solo lo adicional."
                    : null)
                ->fillForm(fn (WorkOrder $record) => [
                    'concept' => "Trabajo {$record->code} — {$record->title}",
                    'amount' => $record->externalRepairsPrice() ?: null,
                ])
                ->schema([
                    TextInput::make('concept')
                        ->label('Concepto')
                        ->required()
                        ->maxLength(255),
                    TextInput::make('amount')
                        ->label('Monto')
                        ->helperText('Se propone lo que se le cobra al cliente por service externo.')
                        ->numeric()
                        ->minValue(0.01)
                        ->prefix('$')
                        ->required(),
                ])
                ->action(fn (WorkOrder $record, array $data) => $record->charges()->create([...$data, 'client_id' => $record->client_id]))
                ->successNotificationTitle('Cargo generado'),
            EditAction::make(),
        ];
    }
}
