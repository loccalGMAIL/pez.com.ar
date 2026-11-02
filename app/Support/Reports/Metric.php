<?php

namespace App\Support\Reports;

/**
 * A headline figure shown above the report tables.
 */
final readonly class Metric
{
    public function __construct(
        public string $label,
        public mixed $value,
        public Format $format = Format::Money,
        public ?string $hint = null,
    ) {}

    public function formatted(): string
    {
        return $this->format->render($this->value);
    }
}
