<?php

namespace App\Filament\Pages\Reports;

use App\Support\Reports\IncomeReport;
use BackedEnum;
use Filament\Support\Icons\Heroicon;

class IncomeReportPage extends ReportPage
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static ?string $slug = 'informes/ingresos';

    protected static ?int $navigationSort = 1;

    public static function reportClass(): string
    {
        return IncomeReport::class;
    }
}
