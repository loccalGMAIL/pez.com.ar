<?php

namespace App\Filament\Resources\Charges;

use App\Enums\ChargeStatus;
use App\Filament\Resources\Charges\Pages\CreateCharge;
use App\Filament\Resources\Charges\Pages\EditCharge;
use App\Filament\Resources\Charges\Pages\ListCharges;
use App\Filament\Resources\Charges\Pages\ViewCharge;
use App\Filament\Resources\Charges\Schemas\ChargeForm;
use App\Filament\Resources\Charges\Schemas\ChargeInfolist;
use App\Filament\Resources\Charges\Tables\ChargesTable;
use App\Models\Charge;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class ChargeResource extends Resource
{
    protected static ?string $model = Charge::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedReceiptPercent;

    protected static ?string $slug = 'cargos';

    protected static ?string $modelLabel = 'cargo';

    protected static ?string $pluralModelLabel = 'cargos';

    protected static string|UnitEnum|null $navigationGroup = 'Cobranza';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'concept';

    public static function form(Schema $schema): Schema
    {
        return ChargeForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ChargeInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ChargesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    /**
     * Drafts and unpaid charges; once payments are allocated, the amount is fixed.
     */
    public static function canEdit(Model $record): bool
    {
        return in_array($record->status, [ChargeStatus::Draft, ChargeStatus::Pending], true)
            && $record->paid == 0
            && parent::canEdit($record);
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    /**
     * Variable subscriptions waiting for their amount to be confirmed.
     */
    public static function getNavigationBadge(): ?string
    {
        $drafts = Charge::where('status', ChargeStatus::Draft)->count();

        return $drafts > 0 ? (string) $drafts : null;
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Cargos a confirmar';
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCharges::route('/'),
            'create' => CreateCharge::route('/create'),
            'view' => ViewCharge::route('/{record}'),
            'edit' => EditCharge::route('/{record}/edit'),
        ];
    }
}
