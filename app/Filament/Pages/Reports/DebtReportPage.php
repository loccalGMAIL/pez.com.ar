<?php

namespace App\Filament\Pages\Reports;

use App\Support\Reports\DebtReport;
use BackedEnum;
use Filament\Support\Icons\Heroicon;

class DebtReportPage extends ReportPage
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    protected static ?string $slug = 'informes/deuda';

    protected static ?int $navigationSort = 3;

    public static function reportClass(): string
    {
        return DebtReport::class;
    }
}
