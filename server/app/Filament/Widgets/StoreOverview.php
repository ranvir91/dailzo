<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StoreOverview extends BaseWidget
{
    protected static ?int $sort = -2;

    protected function getStats(): array
    {
        $pendingStatuses = ['PENDING', 'PAYMENT_PENDING', 'CONFIRMED', 'PROCESSING', 'PACKED'];

        $revenue = Order::where('status', 'DELIVERED')->sum('total');

        return [
            Stat::make('Orders', Order::count())
                ->description(Order::whereIn('status', $pendingStatuses)->count().' open')
                ->color('primary'),

            Stat::make('Revenue (delivered)', '₹'.number_format((float) $revenue, 2))
                ->color('success'),

            Stat::make('Customers', User::where('role', 'CUSTOMER')->count()),

            Stat::make('Products', Product::count())
                ->description(Product::where('stock', '<=', 0)->count().' out of stock')
                ->color(Product::where('stock', '<=', 0)->exists() ? 'warning' : 'gray'),
        ];
    }
}
