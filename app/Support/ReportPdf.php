<?php

namespace App\Support;

use App\Support\Reports\Report;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Str;

class ReportPdf
{
    public static function filename(Report $report): string
    {
        $period = $report::usesPeriod()
            ? $report->period->from->format('Y-m-d').'-al-'.$report->period->to->format('Y-m-d')
            : today()->format('Y-m-d');

        return 'Informe-'.Str::slug($report::title()).'-'.$period.'.pdf';
    }

    /**
     * Generated on demand with the filters chosen on screen.
     */
    public static function render(Report $report): string
    {
        return Pdf::loadView('pdf.report', [
            'report' => $report,
            'company' => Settings::company(),
        ])->setPaper('a4')->output();
    }
}
