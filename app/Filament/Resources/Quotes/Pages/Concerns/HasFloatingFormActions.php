<?php

namespace App\Filament\Resources\Quotes\Pages\Concerns;

use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Group;
use Filament\Support\Components\Attributes\Unexposed;

/**
 * The quote form draws its own buttons next to the totals (see QuoteForm), so the page
 * hands them over instead of rendering its usual footer.
 */
trait HasFloatingFormActions
{
    /**
     * @return array<Action|ActionGroup>
     */
    #[Unexposed]
    public function getFormActions(): array
    {
        return parent::getFormActions();
    }

    #[Unexposed]
    public function getFormActionsContentComponent(): Component
    {
        return Group::make([]);
    }
}
