<?php

namespace App\Filament\Widgets;

use App\Filament\Concerns\HasDashboardDateRange;
use App\Filament\Resources\ProductResource;
use App\Filament\Resources\UserResource;
use App\Models\Product;
use App\Models\User;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Lower priority than Revenue/Orders (see the dashboard ordering) — products
 * and customers are a catalog/base snapshot, not something that needs its
 * own chart, so this stays as simple counts for the selected period.
 */
class CatalogAndCustomersOverview extends BaseWidget
{
    use HasDashboardDateRange;
    use InteractsWithPageFilters;

    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        [$start, $end, $label] = $this->dashboardDateRange();

        $newProducts = Product::whereBetween('created_at', [$start, $end])->count();
        $newCustomers = User::where('role', 'CUSTOMER')
            ->whereBetween('created_at', [$start, $end])
            ->count();

        return [
            Stat::make('New products', $newProducts)
                ->description($label)
                ->color('gray')
                ->url(ProductResource::getUrl()),

            Stat::make('New customers', $newCustomers)
                ->description($label)
                ->color('gray')
                ->url(UserResource::getUrl()),
        ];
    }
}
