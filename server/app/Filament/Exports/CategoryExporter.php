<?php

namespace App\Filament\Exports;

use App\Models\Category;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

/**
 * Column names/order here must stay in sync with CategoryImporter so an
 * exported CSV can be edited and re-imported (via "Bulk import via CSV")
 * without remapping — that parity is the whole point of this exporter.
 */
class CategoryExporter extends Exporter
{
    protected static ?string $model = Category::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id')->label('id'),
            ExportColumn::make('slug')->label('slug'),
            ExportColumn::make('name')->label('name'),
            ExportColumn::make('tax_rate')->label('tax_rate'),
            ExportColumn::make('is_active')->label('is_active')
                ->formatStateUsing(fn (?bool $state): string => $state ? '1' : '0'),
            ExportColumn::make('sort_order')->label('sort_order'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your category export has completed and '.number_format($export->successful_rows).' '.str('row')->plural($export->successful_rows).' exported.';

        $failedRowsCount = $export->getFailedRowsCount();
        if ($failedRowsCount > 0) {
            $body .= ' '.number_format($failedRowsCount).' '.str('row')->plural($failedRowsCount).' failed to export.';
        }

        return $body;
    }
}
