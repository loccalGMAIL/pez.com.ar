<?php

namespace App\Models;

use App\Casts\Money;
use App\Enums\ServiceUnit;
use Database\Factories\ServiceItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ServiceItem extends Model
{
    /** @use HasFactory<ServiceItemFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'unit',
        'price',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'unit' => ServiceUnit::class,
            'price' => Money::class,
            'is_active' => 'boolean',
        ];
    }
}
