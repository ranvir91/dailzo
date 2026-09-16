<?php

namespace App\Models;

use App\Support\SerializesToCamelCase;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class StoreSetting extends Model
{
    use HasUuids;
    use SerializesToCamelCase;

    /** Defaults used when the settings row does not exist yet. */
    public const DEFAULTS = [
        'delivery_charges' => 29,
        'tax' => 5,
        'min_order_value' => 0,
        'min_order_value_enabled' => false,
        'maintenance_mode' => false,
        'app_version' => '1.0.0',
        'store_open_time' => '09:00',
        'store_close_time' => '21:00',
        'payment_methods' => ['COD', 'UPI', 'Cards'],
    ];

    protected $fillable = [
        'delivery_charges',
        'tax',
        'min_order_value',
        'min_order_value_enabled',
        'maintenance_mode',
        'app_version',
        'store_open_time',
        'store_close_time',
        'payment_methods',
    ];

    protected function casts(): array
    {
        return [
            'delivery_charges' => 'decimal:2',
            'tax' => 'decimal:2',
            'min_order_value' => 'decimal:2',
            'min_order_value_enabled' => 'boolean',
            'maintenance_mode' => 'boolean',
            'payment_methods' => 'array',
        ];
    }

    /** Returns the single settings row, creating it from DEFAULTS on first access. */
    public static function current(): self
    {
        return static::query()->firstOr(fn () => static::query()->create(self::DEFAULTS));
    }
}
