<?php

namespace App\Services;

use App\Models\DeliveryAssignment;
use App\Models\DeliveryPartner;
use App\Models\Order;

/**
 * One place to create a DeliveryAssignment, shared by every place an order
 * gets handed to a delivery partner: the admin API (DeliveryController),
 * the Atlas "assign delivery partner" override action, and the Octa
 * "assign delivery partner" action a vendor uses on their own orders.
 */
class DeliveryAssignmentService
{
    public function assign(Order $order, DeliveryPartner $partner): DeliveryAssignment
    {
        return DeliveryAssignment::create([
            'order_id' => $order->id,
            'delivery_partner_id' => $partner->id,
            'status' => DeliveryAssignment::STATUS_ASSIGNED,
        ]);
    }
}
