<?php

namespace App\Filament\Concerns;

use Carbon\Carbon;

/**
 * Resolves the dashboard's shared period filter (see App\Filament\Pages\
 * Dashboard's filtersForm) into a concrete [start, end, label] range. Used
 * by every widget that reacts to it via Filament\Widgets\Concerns\
 * InteractsWithPageFilters — add that trait alongside this one, since this
 * one reads $this->filters it provides.
 */
trait HasDashboardDateRange
{
    /**
     * @return array{0: Carbon, 1: Carbon, 2: string}
     */
    protected function dashboardDateRange(): array
    {
        $timezone = 'Asia/Kolkata';
        $filters = $this->filters ?? [];
        $period = $filters['period'] ?? 'week';

        if ($period === 'month') {
            return [now($timezone)->subDays(29)->startOfDay(), now($timezone)->endOfDay(), 'Last 30 days'];
        }

        if ($period === 'custom') {
            $from = $filters['from'] ?? null;
            $to = $filters['to'] ?? null;

            return [
                $from ? Carbon::parse($from, $timezone)->startOfDay() : now($timezone)->subDays(6)->startOfDay(),
                $to ? Carbon::parse($to, $timezone)->endOfDay() : now($timezone)->endOfDay(),
                'Custom range',
            ];
        }

        return [now($timezone)->subDays(6)->startOfDay(), now($timezone)->endOfDay(), 'Last 7 days'];
    }
}
