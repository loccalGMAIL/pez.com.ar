<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

/**
 * Values editable from the panel's settings page. Keys mirror config/pez.php
 * ("quotes.default_warranty"), which provides the value until one is saved.
 */
class Settings
{
    private const CACHE_KEY = 'pez.settings';

    public static function get(string $key): mixed
    {
        $saved = Cache::rememberForever(self::CACHE_KEY, fn () => Setting::pluck('value', 'key')->all());

        return $saved[$key] ?? config("pez.{$key}");
    }

    /**
     * Company data printed on quotes and mails.
     *
     * @return array{name: ?string, cuit: ?string, email: ?string, phone: ?string, address: ?string, website: ?string, owner: ?string, slogan: ?string}
     */
    public static function company(): array
    {
        return collect(array_keys(config('pez.company')))
            ->mapWithKeys(fn (string $field) => [$field => self::get("company.{$field}")])
            ->all();
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public static function set(array $values): void
    {
        foreach ($values as $key => $value) {
            Setting::updateOrCreate(['key' => $key], ['value' => $value]);
        }

        Cache::forget(self::CACHE_KEY);
    }
}
