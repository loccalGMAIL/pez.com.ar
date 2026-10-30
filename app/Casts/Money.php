<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Stores amounts as integer cents and exposes them as pesos, so forms and
 * tables work with "1500.50" while the database never holds floats.
 *
 * @implements CastsAttributes<float|null, float|int|string|null>
 */
class Money implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?float
    {
        return $value === null ? null : $value / 100;
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?int
    {
        return $value === null || $value === '' ? null : (int) round((float) $value * 100);
    }
}
