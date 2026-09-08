<?php

namespace App\Models;

use App\Models\Concerns\AssignsSequentialNumber;
use App\Support\SerializesToCamelCase;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use AssignsSequentialNumber;
    use HasFactory;
    use HasUuids;
    use SerializesToCamelCase;

    /**
     * Allowed status transitions, ported from the NestJS OrdersService.
     * Every non-terminal state can also move to CANCELLED.
     */
    public const STATUS_TRANSITIONS = [
        'PENDING' => ['PAYMENT_PENDING', 'CANCELLED'],
        'PAYMENT_PENDING' => ['CONFIRMED', 'CANCELLED'],
        'CONFIRMED' => ['PROCESSING', 'CANCELLED'],
        'PROCESSING' => ['PACKED', 'CANCELLED'],
        'PACKED' => ['ASSIGNED', 'CANCELLED'],
        'ASSIGNED' => ['PICKED_UP', 'CANCELLED'],
        'PICKED_UP' => ['OUT_FOR_DELIVERY', 'CANCELLED'],
        'OUT_FOR_DELIVERY' => ['DELIVERED', 'CANCELLED'],
    ];

    protected $fillable = [
        'user_id',
        'status',
        'payment_method',
        'total',
        'address_id',
    ];

    protected function casts(): array
    {
        return [
            'total' => 'decimal:2',
        ];
    }

    public function sequentialNumberColumn(): string
    {
        return 'order_number';
    }

    public function canTransitionTo(string $status): bool
    {
        return in_array($status, self::STATUS_TRANSITIONS[$this->status] ?? [], true);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function deliveries()
    {
        return $this->hasMany(DeliveryAssignment::class);
    }

    public function appNotifications()
    {
        return $this->hasMany(AppNotification::class);
    }
}
