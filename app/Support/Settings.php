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
     * Issuer data for ARCA invoices.
     *
     * @return array{enabled: bool, point_of_sale: ?int, legal_name: ?string, address: ?string, gross_income: ?string, activity_start: ?string}
     */
    public static function invoicing(): array
    {
        $pointOfSale = self::get('invoicing.point_of_sale');

        return [
            'enabled' => (bool) self::get('invoicing.enabled'),
            'point_of_sale' => blank($pointOfSale) ? null : (int) $pointOfSale,
            'legal_name' => self::get('invoicing.legal_name'),
            'address' => self::get('invoicing.address'),
            'gross_income' => self::get('invoicing.gross_income'),
            'activity_start' => self::get('invoicing.activity_start'),
        ];
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
