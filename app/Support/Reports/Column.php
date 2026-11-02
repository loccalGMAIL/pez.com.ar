<?php

namespace App\Support\Reports;

final readonly class Column
{
    public function __construct(
        public string $key,
        public string $label,
        public Format $format = Format::Text,
    ) {}

    /**
     * @param  array<string, mixed>  $row
     */
    public function value(array $row): string
    {
        return $this->format->render($row[$this->key] ?? null);
    }
}
