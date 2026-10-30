<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Casts\Attribute;

/**
 * Correlative `number` assigned on creation, never reused (trashed records
 * count), printed with a prefix: P-00100, T-00001.
 *
 * Models define NUMBER_PREFIX and NUMBER_CONFIG (config key of the first number).
 */
trait HasSequentialNumber
{
    public static function bootHasSequentialNumber(): void
    {
        static::creating(function (self $model) {
            $model->number ??= static::nextNumber();
        });
    }

    public static function nextNumber(): int
    {
        $last = static::query()->withoutGlobalScopes()->max('number');

        return max((int) $last + 1, (int) config(static::NUMBER_CONFIG));
    }

    protected function code(): Attribute
    {
        return Attribute::get(fn () => static::NUMBER_PREFIX.'-'.str_pad((string) $this->number, 5, '0', STR_PAD_LEFT));
    }
}
