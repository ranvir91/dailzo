<?php

namespace App\Filament\Imports;

use App\Models\Category;
use Filament\Actions\Imports\Exceptions\RowImportFailedException;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class CategoryImporter extends Importer
{
    protected static ?string $model = Category::class;

    /**
     * id is the only match key, and it's never user-assigned (see
     * fillRecordUsing on the column below, which keeps it out of
     * fillRecord entirely): leave it blank to create a new category, or set
     * it to the id of an existing one to update that row. A filled id that
     * matches nothing is a failed row (likely a typo), never a new category
     * under a made-up id.
     */
    public function resolveRecord(): ?Model
    {
        $id = $this->data['id'] ?? null;
        if (blank($id)) {
            return new Category;
        }

        $category = Category::find($id);
        if (! $category) {
            throw new RowImportFailedException("No category found with id \"{$id}\" — leave id blank to create a new category instead.");
        }

        return $category;
    }

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('id')
                ->integer()
                ->fillRecordUsing(fn () => null) // id is only a match key, never written to the record
                ->rules(['nullable', 'integer'])
                ->example(5)
                ->helperText('Leave blank to create a new category. Set it to update an existing one — ids are assigned automatically, so don\'t make one up.'),
            ImportColumn::make('slug')
                ->ignoreBlankState()
                ->rules(fn ($record) => [$record?->exists ? 'nullable' : 'required', 'string', Rule::unique('categories', 'slug')->ignore($record?->id)])
                ->example('groceries')
                ->helperText('Required when creating a new category.'),
            ImportColumn::make('name')
                ->ignoreBlankState()
                ->rules(fn ($record) => [$record?->exists ? 'nullable' : 'required', 'string', 'max:255'])
                ->example('Groceries'),
            ImportColumn::make('tax_rate')
                ->label('Tax rate (%)')
                ->numeric()
                ->ignoreBlankState()
                ->rules(['nullable', 'numeric', 'min:0', 'max:100'])
                ->example(18),
            ImportColumn::make('is_active')
                ->label('Active (1/0)')
                ->boolean()
                ->ignoreBlankState()
                ->rules(['nullable', 'boolean'])
                ->example(1),
            ImportColumn::make('sort_order')
                ->integer()
                ->ignoreBlankState()
                ->rules(['nullable', 'integer'])
                ->example(1),
        ];
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = 'Your category import has completed and '.number_format($import->successful_rows).' '.str('row')->plural($import->successful_rows).' imported.';

        $failedRowsCount = $import->getFailedRowsCount();
        if ($failedRowsCount > 0) {
            $body .= ' '.number_format($failedRowsCount).' '.str('row')->plural($failedRowsCount).' failed to import.';
        }

        return $body;
    }
}
