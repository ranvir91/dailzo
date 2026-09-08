<?php

namespace App\Models;

use App\Support\SerializesToCamelCase;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Coupon extends Model
{
    use HasFactory;
    use HasUuids;
    use SerializesToCamelCase;
    use SoftDeletes;

    public const TYPES = ['FIXED', 'PERCENTAGE'];

    protected $fillable = [
        'code',
        'description',
        'type',
        'discount',
        'max_discount_amount',
        'min_order_value',
        'starts_at',
        'expires_at',
        'usage_limit',
        'per_user_limit',
        'first_order_only',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'discount' => 'decimal:2',
            'max_discount_amount' => 'decimal:2',
            'min_order_value' => 'decimal:2',
            'starts_at' => 'datetime',
            'expires_at' => 'datetime',
            'usage_limit' => 'integer',
            'per_user_limit' => 'integer',
            'first_order_only' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function scopeActive($query)
    {
        $now = now();

        return $query->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now))
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>=', $now));
    }

    public function usages()
    {
        return $this->hasMany(CouponUsage::class);
    }
}
