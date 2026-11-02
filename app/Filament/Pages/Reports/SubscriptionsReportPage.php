<?php

namespace App\Filament\Pages\Reports;

use App\Support\Reports\SubscriptionsReport;
use BackedEnum;
use Filament\Support\Icons\Heroicon;

class SubscriptionsReportPage extends ReportPage
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowPath;

    protected static ?string $slug = 'informes/abonos';

    protected static ?int $navigationSort = 5;

    public static function reportClass(): string
    {
        return SubscriptionsReport::class;
    }
}
