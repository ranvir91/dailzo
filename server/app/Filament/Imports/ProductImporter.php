<?php

namespace App\Filament\Imports;

use App\Models\Product;
use Filament\Actions\Imports\Exceptions\RowImportFailedException;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class ProductImporter extends Importer
{
    protected static ?string $model = Product::class;

    /**
     * id is the only match key, and it's never user-assigned (see
     * fillRecordUsing on the column below, which keeps it out of
     * fillRecord entirely): leave it blank to create a new product, or set
     * it to the id of an existing one to update that row. A filled id that
     * matches nothing is a failed row (likely a typo), never a new product
     * under a made-up id.
     *
     * Column order/names here must stay in sync with ProductExporter so an
     * exported CSV can be edited and re-imported without remapping.
     */
    public function resolveRecord(): ?Model
    {
        $id = $this->data['id'] ?? null;
        if (blank($id)) {
            return new Product;
        }

        $product = Product::find($id);
        if (! $product) {
            throw new RowImportFailedException("No product found with id \"{$id}\" — leave id blank to create a new product instead.");
        }

        return $product;
    }

    /** images has no DB default and isn't a CSV column — a new row needs one to satisfy the NOT NULL column. */
    protected function beforeSave(): void
    {
        if (! $this->record->exists) {
            $this->record->images ??= [];
        }
    }

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('id')
                ->integer()
                ->fillRecordUsing(fn () => null) // id is only a match key, never written to the record
                ->rules(['nullable', 'integer'])
                ->example(101)
                ->helperText('Leave blank to create a new product. Set it to update an existing one — ids are assigned automatically, so don\'t make one up.'),
            ImportColumn::make('sku')
                ->ignoreBlankState()
                ->rules(fn ($record) => ['nullable', 'string', Rule::unique('products', 'sku')->ignore($record?->id)])
                ->example('CHG-001'),
            ImportColumn::make('name')
                ->label('Title')
                ->ignoreBlankState()
                ->rules(fn ($record) => [$record?->exists ? 'nullable' : 'required', 'string', 'max:255'])
                ->example('Mobile Charger'),
            ImportColumn::make('price')
                ->label('MRP')
                ->numeric()
                ->ignoreBlankState()
                ->rules(fn ($record) => [$record?->exists ? 'nullable' : 'required', 'numeric', 'min:0'])
                ->example(1200),
            ImportColumn::make('discounted_price')
                ->label('Selling price')
                ->numeric()
                ->ignoreBlankState()
                ->rules(['nullable', 'numeric', 'min:0'])
                ->example(999),
            ImportColumn::make('stock')
                ->integer()
                ->ignoreBlankState()
                ->rules(['nullable', 'integer', 'min:0'])
                ->example(50),
            ImportColumn::make('is_active')
                ->label('Active (1/0)')
                ->boolean()
                ->ignoreBlankState()
                ->rules(['nullable', 'boolean'])
                ->example(1),
            ImportColumn::make('unit')
                ->ignoreBlankState()
                ->rules(['nullable', 'string'])
                ->example('PCS'),
            ImportColumn::make('variant_label')
                ->label('Pack size')
                ->ignoreBlankState()
                ->rules(['nullable', 'string', 'max:255'])
                ->example('10kg'),
            ImportColumn::make('category')
                ->relationship(resolveUsing: 'slug')
                ->ignoreBlankState()
                ->rules(fn ($record) => [$record?->exists ? 'nullable' : 'required', 'string'])
                ->example('groceries')
                ->helperText('The category\'s slug, as shown in Catalog > Categories. Required when creating a new product.'),
            ImportColumn::make('brand')
                ->relationship(resolveUsing: 'name')
                ->ignoreBlankState()
                ->rules(['nullable', 'string'])
                ->example('Acme'),
        ];
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = 'Your product import has completed and '.number_format($import->successful_rows).' '.str('row')->plural($import->successful_rows).' imported.';

        $failedRowsCount = $import->getFailedRowsCount();
        if ($failedRowsCount > 0) {
            $body .= ' '.number_format($failedRowsCount).' '.str('row')->plural($failedRowsCount).' failed to import.';
        }

        return $body;
    }
}
