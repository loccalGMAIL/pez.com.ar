<?php

namespace App\Filament\Pages\Reports;

use App\Support\Reports\WorkshopReport;
use BackedEnum;
use Filament\Support\Icons\Heroicon;

class WorkshopReportPage extends ReportPage
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedWrenchScrewdriver;

    protected static ?string $slug = 'informes/taller';

    protected static ?int $navigationSort = 6;

    public static function reportClass(): string
    {
        return WorkshopReport::class;
    }
}
