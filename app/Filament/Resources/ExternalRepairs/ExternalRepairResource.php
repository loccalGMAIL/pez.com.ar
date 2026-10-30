<?php

namespace App\Filament\Resources\ExternalRepairs;

use App\Filament\Resources\ExternalRepairs\Pages\ManageExternalRepairs;
use App\Filament\Resources\ExternalRepairs\Schemas\ExternalRepairForm;
use App\Filament\Resources\ExternalRepairs\Tables\ExternalRepairsTable;
use App\Models\ExternalRepair;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class ExternalRepairResource extends Resource
{
    protected static ?string $model = ExternalRepair::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowTopRightOnSquare;

    protected static ?string $slug = 'service-externo';

    protected static ?string $modelLabel = 'envío a service';

    protected static ?string $pluralModelLabel = 'service externo';

    protected static string|UnitEnum|null $navigationGroup = 'Taller';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return ExternalRepairForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ExternalRepairsTable::configure($table)
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    /**
     * Devices currently at a supplier's.
     */
    public static function getNavigationBadge(): ?string
    {
        $out = ExternalRepair::out()->count();

        return $out > 0 ? (string) $out : null;
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Equipos en service externo';
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageExternalRepairs::route('/'),
        ];
    }
}
