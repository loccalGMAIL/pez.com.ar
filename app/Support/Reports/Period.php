<?php

namespace App\Support\Reports;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Date range a report covers, both ends included.
 */
final readonly class Period
{
    public const PRESETS = [
        'this_month' => 'Este mes',
        'last_month' => 'Mes anterior',
        'this_year' => 'Este año',
        'last_year' => 'Año anterior',
        'custom' => 'Otro período',
    ];

    public CarbonImmutable $from;

    public CarbonImmutable $to;

    public function __construct(CarbonInterface|string $from, CarbonInterface|string $to)
    {
        $from = CarbonImmutable::parse($from)->startOfDay();
        $to = CarbonImmutable::parse($to)->startOfDay();

        [$this->from, $this->to] = $from->lte($to) ? [$from, $to] : [$to, $from];
    }

    public static function preset(string $preset, ?string $from = null, ?string $to = null): self
    {
        $today = CarbonImmutable::today();

        return match ($preset) {
            'last_month' => new self($today->subMonthNoOverflow()->startOfMonth(), $today->subMonthNoOverflow()->endOfMonth()),
            'this_year' => new self($today->startOfYear(), $today->endOfYear()),
            'last_year' => new self($today->subYear()->startOfYear(), $today->subYear()->endOfYear()),
            'custom' => new self($from ?: $today->startOfMonth(), $to ?: $today),
            default => new self($today->startOfMonth(), $today->endOfMonth()),
        };
    }

    /**
     * Bounds for whereBetween on date and datetime columns alike.
     *
     * @return array{CarbonImmutable, CarbonImmutable}
     */
    public function bounds(): array
    {
        return [$this->from->startOfDay(), $this->to->endOfDay()];
    }

    public function contains(?CarbonInterface $date): bool
    {
        return $date !== null && $date->betweenIncluded($this->bounds()[0], $this->bounds()[1]);
    }

    public function label(): string
    {
        if ($this->from->isSameDay($this->from->startOfMonth()) && $this->to->isSameDay($this->from->endOfMonth())) {
            return ucfirst($this->from->locale('es')->translatedFormat('F Y'));
        }

        if ($this->from->isSameDay($this->from->startOfYear()) && $this->to->isSameDay($this->from->endOfYear())) {
            return 'Año '.$this->from->year;
        }

        return $this->from->format('d/m/Y').' al '.$this->to->format('d/m/Y');
    }

    /**
     * Months the period touches, as "Y-m" => "octubre 2026".
     *
     * @return array<string, string>
     */
    public function months(): array
    {
        $months = [];

        for ($month = $this->from->startOfMonth(); $month->lte($this->to); $month = $month->addMonthNoOverflow()) {
            $months[$month->format('Y-m')] = ucfirst($month->locale('es')->translatedFormat('F Y'));
        }

        return $months;
    }
}
