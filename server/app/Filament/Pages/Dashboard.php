<?php

namespace App\Filament\Pages;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;

/**
 * Adds a shared period filter (week / month / custom range) above the
 * dashboard's widgets. Any widget that wants to react to it uses
 * InteractsWithPageFilters + HasDashboardDateRange (see
 * App\Filament\Concerns\HasDashboardDateRange) to read it back out.
 */
class Dashboard extends BaseDashboard
{
    use HasFiltersForm;

    public function filtersForm(Form $form): Form
    {
        return $form->schema([
            Select::make('period')
                ->label('Period')
                ->options([
                    'week' => 'Last 7 days',
                    'month' => 'Last 30 days',
                    'custom' => 'Custom range',
                ])
                ->default('week')
                ->native(false)
                ->selectablePlaceholder(false),
            DatePicker::make('from')
                ->label('From')
                ->native(false)
                ->maxDate(now())
                ->visible(fn (Get $get) => $get('period') === 'custom'),
            DatePicker::make('to')
                ->label('To')
                ->native(false)
                ->maxDate(now())
                ->visible(fn (Get $get) => $get('period') === 'custom'),
        ]);
    }
}
