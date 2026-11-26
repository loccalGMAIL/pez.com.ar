<?php

namespace App\Filament\Resources\FollowUps;

use App\Filament\Resources\Charges\ChargeResource;
use App\Filament\Resources\FollowUps\Pages\ListFollowUps;
use App\Filament\Resources\FollowUps\Pages\ViewFollowUp;
use App\Filament\Resources\FollowUps\Schemas\FollowUpInfolist;
use App\Filament\Resources\FollowUps\Tables\FollowUpsTable;
use App\Filament\Resources\Quotes\QuoteResource;
use App\Filament\Resources\WorkOrders\WorkOrderResource;
use App\Models\FollowUp;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Read-only: follow-ups are created when a job is delivered or something is
 * sold, and handled from the dashboard, this list or the client page.
 */
class FollowUpResource extends Resource
{
    protected static ?string $model = FollowUp::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    protected static ?string $slug = 'seguimientos';

    protected static ?string $modelLabel = 'seguimiento';

    protected static ?string $pluralModelLabel = 'seguimientos';

    protected static string|UnitEnum|null $navigationGroup = 'Ventas';

    protected static ?int $navigationSort = 3;

    public static function shouldRegisterNavigation(): bool
    {
        return FollowUp::isEnabled() || FollowUp::query()->exists();
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getNavigationBadge(): ?string
    {
        $due = FollowUp::query()->due()->count();

        return $due > 0 ? (string) $due : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Para enviar hoy';
    }

    /**
     * The job, quote or charge it follows up.
     */
    public static function subjectUrl(FollowUp $followUp): ?string
    {
        return match (true) {
            $followUp->work_order_id !== null => WorkOrderResource::getUrl('view', ['record' => $followUp->work_order_id]),
            $followUp->quote_id !== null => QuoteResource::getUrl('view', ['record' => $followUp->quote_id]),
            $followUp->charge_id !== null => ChargeResource::getUrl('view', ['record' => $followUp->charge_id]),
            default => null,
        };
    }

    public static function infolist(Schema $schema): Schema
    {
        return FollowUpInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return FollowUpsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFollowUps::route('/'),
            'view' => ViewFollowUp::route('/{record}'),
        ];
    }
}
