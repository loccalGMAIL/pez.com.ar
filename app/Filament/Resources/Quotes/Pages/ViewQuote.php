<?php

namespace App\Filament\Resources\Quotes\Pages;

use App\Filament\Resources\Quotes\Actions\QuoteActions;
use App\Filament\Resources\Quotes\QuoteResource;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewQuote extends ViewRecord
{
    protected static string $resource = QuoteResource::class;

    public function getTitle(): string
    {
        // The resource's record title is its number (P-00100, T-00001, R-00001).
        return "Presupuesto {$this->getRecordTitle()}";
    }

    protected function getHeaderActions(): array
    {
        return [
            QuoteActions::convertToWorkOrder(),
            QuoteActions::charge(),
            QuoteActions::accept(),
            QuoteActions::reject(),
            QuoteActions::shareOnWhatsApp(),
            EditAction::make(),
            ActionGroup::make([
                QuoteActions::send(),
                QuoteActions::downloadPdf(),
                QuoteActions::downloadSentPdf(),
                QuoteActions::markAsSent(),
                QuoteActions::duplicate(),
            ]),
        ];
    }
}
