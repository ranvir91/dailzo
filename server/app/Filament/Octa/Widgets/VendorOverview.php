<?php

namespace App\Filament\Octa\Widgets;

use App\Filament\Octa\Resources\DeliveryPartnerResource;
use App\Filament\Octa\Resources\OrderResource;
use App\Models\DeliveryAssignment;
use App\Models\DeliveryPartner;
use App\Models\Order;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class VendorOverview extends BaseWidget
{
    protected function getStats(): array
    {
        $vendor = auth()->user()->vendorProfile;

        // canAccessPanel() (User::vendorProfile check) already guarantees this
        // for anyone who reaches the dashboard, but stay defensive rather than
        // let a null-property-access 500 happen if that ever changes.
        if (! $vendor) {
            return [];
        }

        $todayStart = now('Asia/Kolkata')->startOfDay();

        $vendorOrders = Order::where('vendor_id', $vendor->id);
        $vendorAssignments = DeliveryAssignment::whereHas(
            'order',
            fn ($query) => $query->where('vendor_id', $vendor->id),
        );

        return [
            Stat::make('Total orders', (clone $vendorOrders)->count())
                ->description('All orders routed to you')
                ->color('gray')
                ->url(OrderResource::getUrl()),

            Stat::make("Today's orders", (clone $vendorOrders)->where('created_at', '>=', $todayStart)->count())
                ->description('Received today')
                ->color('info')
                ->url(OrderResource::getUrl()),

            Stat::make('Pending pickup', (clone $vendorAssignments)->where('status', DeliveryAssignment::STATUS_ASSIGNED)->count())
                ->description('Assigned to a partner, not yet out')
                ->color('warning')
                ->url(OrderResource::getUrl()),

            Stat::make('Out for delivery', (clone $vendorAssignments)->where('status', DeliveryAssignment::STATUS_OUT_FOR_DELIVERY)->count())
                ->description('On the way right now')
                ->color('warning')
                ->url(OrderResource::getUrl()),

            Stat::make('Completed today', (clone $vendorAssignments)
                ->where('status', DeliveryAssignment::STATUS_DELIVERED)
                ->where('status_changed_at', '>=', $todayStart)
                ->count())
                ->description('Delivered since midnight')
                ->color('success')
                ->url(OrderResource::getUrl()),

            Stat::make('Active delivery partners', DeliveryPartner::where('vendor_id', $vendor->id)->where('is_active', true)->count())
                ->description('Available to receive orders')
                ->color('primary')
                ->url(DeliveryPartnerResource::getUrl()),
        ];
    }
}
