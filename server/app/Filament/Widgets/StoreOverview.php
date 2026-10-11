<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\OrderResource;
use App\Filament\Resources\ProductResource;
use App\Filament\Resources\ServicePincodeResource;
use App\Filament\Resources\UserResource;
use App\Models\Order;
use App\Models\Product;
use App\Models\ServicePincode;
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
                ->color('primary')
                ->url(OrderResource::getUrl()),

            Stat::make('Revenue (delivered)', '₹'.number_format((float) $revenue, 2))
                ->color('success')
                ->url(OrderResource::getUrl()),

            Stat::make('Customers', User::where('role', 'CUSTOMER')->count())
                ->url(UserResource::getUrl()),

            Stat::make('Products', Product::count())
                ->description(Product::where('stock', '<=', 0)->count().' out of stock')
                ->color(Product::where('stock', '<=', 0)->exists() ? 'warning' : 'gray')
                ->url(ProductResource::getUrl()),

            Stat::make('Pincodes', ServicePincode::count())
                ->description(ServicePincode::active()->count().' active')
                ->color('gray')
                ->url(ServicePincodeResource::getUrl()),
        ];
    }
}
