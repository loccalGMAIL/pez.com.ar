<?php

namespace App\Filament\Resources\WorkOrders\Pages;

use App\Enums\WorkOrderStatus;
use App\Filament\Resources\WorkOrders\Actions\WorkOrderActions;
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
        // The resource's record title is its number (P-00100, T-00001, R-00001).
        return "Orden de trabajo {$this->getRecordTitle()}";
    }

    protected function getHeaderActions(): array
    {
        return [
            WorkOrderActions::shareOnWhatsApp(),
            WorkOrderActions::downloadPdf(),
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
                ->modalHeading('Cargar la orden de trabajo en la cuenta corriente')
                ->modalDescription(fn (WorkOrder $record) => $record->quote_id
                    ? "Esta orden viene del presupuesto {$record->quote?->code}: si ya se cobró por ahí, cargá solo lo adicional."
                    : null)
                ->fillForm(fn (WorkOrder $record) => [
                    'concept' => "Orden de trabajo {$record->code} — {$record->title}",
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
