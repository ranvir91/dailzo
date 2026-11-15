<?php

namespace App\Filament\Exports;

use App\Models\Product;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

/**
 * Column names/order here must stay in sync with ProductImporter so an
 * exported CSV can be edited and re-imported (via "Bulk import via CSV")
 * without remapping — that parity is the whole point of this exporter.
 */
class ProductExporter extends Exporter
{
    protected static ?string $model = Product::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id')->label('id'),
            ExportColumn::make('sku')->label('sku'),
            ExportColumn::make('name')->label('name'),
            ExportColumn::make('price')->label('price'),
            ExportColumn::make('discounted_price')->label('discounted_price'),
            ExportColumn::make('stock')->label('stock'),
            ExportColumn::make('is_active')->label('is_active')
                ->formatStateUsing(fn (?bool $state): string => $state ? '1' : '0'),
            ExportColumn::make('unit')->label('unit'),
            ExportColumn::make('variant_label')->label('variant_label'),
            ExportColumn::make('category.slug')->label('category'),
            ExportColumn::make('brand.name')->label('brand'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your product export has completed and '.number_format($export->successful_rows).' '.str('row')->plural($export->successful_rows).' exported.';

        $failedRowsCount = $export->getFailedRowsCount();
        if ($failedRowsCount > 0) {
            $body .= ' '.number_format($failedRowsCount).' '.str('row')->plural($failedRowsCount).' failed to export.';
        }

        return $body;
    }
}
