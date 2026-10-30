<?php

namespace App\Filament\Resources\ServiceItems;

use App\Filament\Resources\ServiceItems\Pages\CreateServiceItem;
use App\Filament\Resources\ServiceItems\Pages\EditServiceItem;
use App\Filament\Resources\ServiceItems\Pages\ListServiceItems;
use App\Filament\Resources\ServiceItems\Schemas\ServiceItemForm;
use App\Filament\Resources\ServiceItems\Tables\ServiceItemsTable;
use App\Models\ServiceItem;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ServiceItemResource extends Resource
{
    protected static ?string $model = ServiceItem::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedWrench;

    protected static ?string $slug = 'catalogo';

    protected static ?string $modelLabel = 'servicio';

    protected static ?string $pluralModelLabel = 'catálogo de servicios';

    protected static ?string $navigationLabel = 'Catálogo';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return ServiceItemForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ServiceItemsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListServiceItems::route('/'),
            'create' => CreateServiceItem::route('/create'),
            'edit' => EditServiceItem::route('/{record}/edit'),
        ];
    }
}
