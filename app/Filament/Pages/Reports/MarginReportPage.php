<?php

namespace App\Filament\Pages\Reports;

use App\Support\Reports\MarginReport;
use BackedEnum;
use Filament\Support\Icons\Heroicon;

class MarginReportPage extends ReportPage
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedScale;

    protected static ?string $slug = 'informes/margen';

    protected static ?int $navigationSort = 2;

    public static function reportClass(): string
    {
        return MarginReport::class;
    }
}
