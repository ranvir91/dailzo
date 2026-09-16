<?php

namespace App\Models;

use App\Support\SerializesToCamelCase;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class OrderComment extends Model
{
    use HasUuids;
    use SerializesToCamelCase;

    public const TYPE_INCIDENT = 'INCIDENT';

    public const TYPE_STATUS_CHANGE = 'STATUS_CHANGE';

    public const TYPE_REASSIGNMENT = 'REASSIGNMENT';

    protected $fillable = [
        'order_id',
        'delivery_partner_id',
        'type',
        'body',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function deliveryPartner()
    {
        return $this->belongsTo(DeliveryPartner::class);
    }
}
