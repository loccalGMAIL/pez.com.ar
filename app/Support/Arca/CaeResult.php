<?php

namespace App\Support\Arca;

use Illuminate\Support\Carbon;

/**
 * ARCA's answer to a CAE request.
 */
final class CaeResult
{
    /**
     * @param  list<string>  $messages  errors and observations, as "code: text"
     * @param  array<string, mixed>  $response  the answer as received, for the record
     */
    public function __construct(
        public readonly bool $approved,
        public readonly ?string $cae,
        public readonly ?Carbon $caeDueOn,
        public readonly array $messages,
        public readonly array $response,
    ) {}
}
