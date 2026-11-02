<?php

namespace App\Support\Reports;

use App\Support\QuotePdf;
use Carbon\CarbonInterface;

/**
 * How a report value is printed, the same on screen and in the PDF.
 */
enum Format: string
{
    case Text = 'text';
    case Money = 'money';
    case Number = 'number';
    case Decimal = 'decimal';
    case Percent = 'percent';
    case Date = 'date';
    case Days = 'days';

    public function render(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        return match ($this) {
            self::Text => (string) $value,
            self::Money => QuotePdf::money((float) $value),
            self::Number => number_format((float) $value, 0, ',', '.'),
            self::Decimal => number_format((float) $value, 1, ',', '.'),
            self::Percent => number_format((float) $value, 1, ',', '.').' %',
            self::Date => $value instanceof CarbonInterface ? $value->format('d/m/Y') : (string) $value,
            self::Days => number_format((float) $value, 0, ',', '.').' '.((int) round((float) $value) === 1 ? 'día' : 'días'),
        };
    }

    /**
     * Figures line up on the right; text and dates on the left.
     */
    public function isNumeric(): bool
    {
        return ! in_array($this, [self::Text, self::Date], true);
    }
}
