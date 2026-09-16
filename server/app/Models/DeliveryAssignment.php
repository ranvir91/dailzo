<?php

namespace App\Models;

use App\Support\SerializesToCamelCase;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class DeliveryAssignment extends Model
{
    use HasUuids;
    use SerializesToCamelCase;

    /**
     * Partner-app status — deliberately a smaller, closed set than
     * Order::STATUS_TRANSITIONS: the partner only ever reports "picked this
     * order up and is heading out", "delivered it", or "it's no longer mine".
     */
    public const STATUS_ASSIGNED = 'ASSIGNED';

    public const STATUS_OUT_FOR_DELIVERY = 'OUT_FOR_DELIVERY';

    public const STATUS_DELIVERED = 'DELIVERED';

    public const STATUS_REASSIGNED = 'REASSIGNED';

    protected $fillable = [
        'order_id',
        'delivery_partner_id',
        'status',
        'status_changed_at',
    ];

    protected function casts(): array
    {
        return [
            'status_changed_at' => 'datetime',
        ];
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function deliveryPartner()
    {
        return $this->belongsTo(DeliveryPartner::class);
    }

    public function markStatus(string $status): void
    {
        $this->update(['status' => $status, 'status_changed_at' => now()]);
    }
}
