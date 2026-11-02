<?php

namespace App\Support\Reports;

use App\Models\Client;
use Illuminate\Database\Eloquent\Builder;

/**
 * A report of the panel: headline figures plus tables, rendered the same
 * on screen and in the PDF. Money follows the payment date: what was
 * collected in the period, not what was billed.
 */
abstract class Report
{
    public function __construct(
        public readonly Period $period,
        public readonly ?Client $client = null,
    ) {}

    abstract public static function title(): string;

    /**
     * One line telling what the figures mean, printed under the title.
     */
    abstract public function description(): string;

    /**
     * @return list<Metric>
     */
    abstract public function metrics(): array;

    /**
     * @return list<Table>
     */
    abstract public function tables(): array;

    /**
     * Reports that show the situation as of today ignore the period filter.
     */
    public static function usesPeriod(): bool
    {
        return true;
    }

    public function periodLabel(): string
    {
        return static::usesPeriod() ? $this->period->label() : 'Al '.today()->format('d/m/Y');
    }

    public function table(string $title): ?Table
    {
        foreach ($this->tables() as $table) {
            if ($table->title === $title) {
                return $table;
            }
        }

        return null;
    }

    public function metric(string $label): mixed
    {
        foreach ($this->metrics() as $metric) {
            if ($metric->label === $label) {
                return $metric->value;
            }
        }

        return null;
    }

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    protected function forClient(Builder $query, string $column = 'client_id'): Builder
    {
        return $query->when($this->client, fn (Builder $query, Client $client) => $query->where($column, $client->id));
    }

    protected static function share(float $part, float $total): ?float
    {
        return $total > 0 ? round($part * 100 / $total, 1) : null;
    }
}
