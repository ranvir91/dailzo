<?php

namespace App\Models;

use App\Support\SerializesToCamelCase;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

/**
 * A delivery partner. Also the auth principal for the delivery-partner mobile
 * app (phone + password, Sanctum tokens) — see PartnerAuthController.
 */
class DeliveryPartner extends Authenticatable
{
    use HasApiTokens;
    use HasFactory;
    use HasUuids;
    use SerializesToCamelCase;
    use SoftDeletes;

    protected $fillable = [
        'name',
        'phone',
        'password',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    public function assignments()
    {
        return $this->hasMany(DeliveryAssignment::class);
    }

    /** The assignment currently owned by this partner for a given order, if any. */
    public function activeAssignmentFor(Order $order): ?DeliveryAssignment
    {
        return $this->assignments()
            ->where('order_id', $order->id)
            ->where('status', '!=', DeliveryAssignment::STATUS_REASSIGNED)
            ->latest()
            ->first();
    }

    public function refreshTokens()
    {
        return $this->hasMany(PartnerRefreshToken::class);
    }

    public function comments()
    {
        return $this->hasMany(OrderComment::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
