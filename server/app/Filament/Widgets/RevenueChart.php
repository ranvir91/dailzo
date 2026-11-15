<?php

namespace App\Filament\Widgets;

use App\Filament\Concerns\HasDashboardDateRange;
use App\Models\Order;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

class RevenueChart extends ChartWidget
{
    use HasDashboardDateRange;
    use InteractsWithPageFilters;

    protected static ?string $heading = 'Revenue';

    protected static ?int $sort = -1;

    protected int|string|array $columnSpan = 'full';

    protected function getType(): string
    {
        return 'line';
    }

    public function getDescription(): ?string
    {
        [, , $label] = $this->dashboardDateRange();

        return $label.' · delivered orders only';
    }

    protected function getData(): array
    {
        [$start, $end] = $this->dashboardDateRange();

        $totalsByDay = Order::where('status', 'DELIVERED')
            ->whereBetween('created_at', [$start, $end])
            ->selectRaw('DATE(created_at) as day, SUM(total) as total')
            ->groupBy('day')
            ->pluck('total', 'day');

        $labels = [];
        $data = [];
        $cursor = $start->copy()->startOfDay();
        while ($cursor->lte($end)) {
            $labels[] = $cursor->format('d M');
            $data[] = (float) ($totalsByDay[$cursor->format('Y-m-d')] ?? 0);
            $cursor->addDay();
        }

        return [
            'datasets' => [
                [
                    'label' => 'Revenue (₹)',
                    'data' => $data,
                    'borderColor' => '#10b981',
                    'backgroundColor' => 'rgba(16, 185, 129, 0.1)',
                    'fill' => true,
                    'tension' => 0.3,
                ],
            ],
            'labels' => $labels,
        ];
    }
}
