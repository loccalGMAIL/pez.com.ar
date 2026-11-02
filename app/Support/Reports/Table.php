<?php

namespace App\Support\Reports;

/**
 * A block of rows of a report. Values stay raw (numbers, dates) so they
 * can be checked in tests; each column knows how to print them.
 */
final readonly class Table
{
    /**
     * @param  list<Column>  $columns
     * @param  list<array<string, mixed>>  $rows
     * @param  array<string, mixed>|null  $totals  row printed at the bottom, keyed like the rows
     */
    public function __construct(
        public string $title,
        public array $columns,
        public array $rows,
        public ?array $totals = null,
        public ?string $description = null,
        public string $emptyMessage = 'No hay datos para el período elegido.',
    ) {}

    /**
     * Values of one column, to check a report without depending on the order of the rest.
     *
     * @return list<mixed>
     */
    public function pluck(string $key): array
    {
        return array_map(fn (array $row) => $row[$key] ?? null, $this->rows);
    }
}
