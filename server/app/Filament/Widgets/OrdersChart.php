<?php

namespace App\Filament\Widgets;

use App\Filament\Concerns\HasDashboardDateRange;
use App\Models\Order;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

class OrdersChart extends ChartWidget
{
    use HasDashboardDateRange;
    use InteractsWithPageFilters;

    protected static ?string $heading = 'Orders';

    protected static ?int $sort = 0;

    protected int|string|array $columnSpan = 'full';

    protected function getType(): string
    {
        return 'bar';
    }

    public function getDescription(): ?string
    {
        [, , $label] = $this->dashboardDateRange();

        return $label.' · every order placed, any status';
    }

    protected function getData(): array
    {
        [$start, $end] = $this->dashboardDateRange();

        $countsByDay = Order::whereBetween('created_at', [$start, $end])
            ->selectRaw('DATE(created_at) as day, COUNT(*) as total')
            ->groupBy('day')
            ->pluck('total', 'day');

        $labels = [];
        $data = [];
        $cursor = $start->copy()->startOfDay();
        while ($cursor->lte($end)) {
            $labels[] = $cursor->format('d M');
            $data[] = (int) ($countsByDay[$cursor->format('Y-m-d')] ?? 0);
            $cursor->addDay();
        }

        return [
            'datasets' => [
                [
                    'label' => 'Orders',
                    'data' => $data,
                    'backgroundColor' => '#6366f1',
                ],
            ],
            'labels' => $labels,
        ];
    }
}
