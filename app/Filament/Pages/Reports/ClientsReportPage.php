<?php

namespace App\Filament\Pages\Reports;

use App\Support\Reports\ClientsReport;
use BackedEnum;
use Filament\Support\Icons\Heroicon;

class ClientsReportPage extends ReportPage
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static ?string $slug = 'informes/clientes';

    protected static ?int $navigationSort = 4;

    public static function reportClass(): string
    {
        return ClientsReport::class;
    }
}
