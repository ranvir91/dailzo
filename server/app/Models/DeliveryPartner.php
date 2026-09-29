<?php

namespace App\Models;

use App\Models\Concerns\SyncsDeliveryPartnerUser;
use App\Support\SerializesToCamelCase;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A delivery partner's profile and delivery history. Login now goes through
 * the linked User (role = DELIVERY_PARTNER, see PartnerAuthController) —
 * this table is no longer itself an auth principal (it used to extend
 * Authenticatable with HasApiTokens before partner login was consolidated
 * onto users). `phone` here is kept as a mirror of user.phone (written
 * together by DeliveryPartnerResource/Concerns\SyncsDeliveryPartnerUser) so
 * existing search/display queries against this table don't need to join
 * through user. `password` is vestigial — no longer read or written — left
 * in the schema rather than dropped for now.
 */
class DeliveryPartner extends Model
{
    use HasFactory;
    use HasUuids;
    use SerializesToCamelCase;
    use SoftDeletes;
    use SyncsDeliveryPartnerUser;

    protected $fillable = [
        'user_id',
        'vendor_id',
        'name',
        'phone',
        'is_active',
    ];

    protected $hidden = [
        'password',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function vendor()
    {
        return $this->belongsTo(Vendor::class);
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

    public function comments()
    {
        return $this->hasMany(OrderComment::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
