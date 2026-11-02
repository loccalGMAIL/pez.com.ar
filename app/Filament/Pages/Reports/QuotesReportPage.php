<?php

namespace App\Filament\Pages\Reports;

use App\Support\Reports\QuotesReport;
use BackedEnum;
use Filament\Support\Icons\Heroicon;

class QuotesReportPage extends ReportPage
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static ?string $slug = 'informes/presupuestos';

    protected static ?int $navigationSort = 7;

    public static function reportClass(): string
    {
        return QuotesReport::class;
    }
}
