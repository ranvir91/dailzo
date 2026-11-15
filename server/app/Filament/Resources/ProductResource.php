<?php

namespace App\Filament\Resources;

use App\Filament\Exports\ProductExporter;
use App\Filament\Imports\ProductImporter;
use App\Filament\Resources\ProductResource\Pages;
use App\Models\Category;
use App\Models\Product;
use Filament\Actions\Exports\Enums\ExportFormat;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\ExportBulkAction;
use Filament\Tables\Actions\ImportAction;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Str;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static ?string $navigationIcon = 'heroicon-o-cube';

    protected static ?string $navigationGroup = 'Catalog';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Tabs::make('Product')
                ->persistTabInQueryString()
                ->columnSpanFull()
                ->tabs([
                    Forms\Components\Tabs\Tab::make('General')
                        ->icon('heroicon-o-information-circle')
                        ->schema([
                            Forms\Components\TextInput::make('name')->label('Item name')->required()->maxLength(255)
                                ->columnSpan(2),
                            Forms\Components\Select::make('item_type')
                                ->options(['GOODS' => 'Goods', 'SERVICE' => 'Service'])
                                ->default('GOODS')->required(),
                            Forms\Components\Select::make('category_id')
                                ->label('Item category')
                                ->relationship('category', 'name')
                                ->searchable()->preload()->required()
                                ->live(),
                            Forms\Components\Select::make('brand_id')
                                ->label('Brand')
                                ->relationship('brand', 'name')
                                ->searchable()->preload()
                                ->createOptionForm([
                                    Forms\Components\TextInput::make('name')->required()
                                        ->live(onBlur: true)
                                        ->afterStateUpdated(fn (Forms\Set $set, ?string $state) => $set('slug', Str::slug((string) $state))),
                                    Forms\Components\TextInput::make('slug')->required(),
                                ]),
                            Forms\Components\Select::make('unit')->label('Item unit')
                                ->options([
                                    'PCS' => 'PCS', 'KG' => 'KG', 'GM' => 'GM', 'L' => 'L',
                                    'ML' => 'ML', 'BOX' => 'BOX', 'PACK' => 'PACK', 'DOZEN' => 'DOZEN',
                                ])
                                ->searchable(),
                            Forms\Components\TextInput::make('sku')->label('SKU code')->maxLength(255)->unique(ignoreRecord: true),
                            Forms\Components\TextInput::make('item_code')->maxLength(255),
                            Forms\Components\TextInput::make('hsn_sac_code')->label('HSN/SAC code')->maxLength(255)
                                ->helperText('Required on GST invoices.'),
                            Forms\Components\Textarea::make('description')->rows(2)->columnSpanFull(),
                            Forms\Components\TagsInput::make('tags')
                                ->suggestions(fn () => Product::query()
                                    ->whereNotNull('tags')
                                    ->pluck('tags')
                                    ->flatten()
                                    ->unique()
                                    ->values()
                                    ->all())
                                ->placeholder('BOGO, Combo, Festive…')
                                ->helperText('Lets an Offer banner (Marketing > Offers) link to this product by tag.')
                                ->columnSpanFull(),
                        ])->columns(3),

                    Forms\Components\Tabs\Tab::make('Pricing & stock')
                        ->icon('heroicon-o-banknotes')
                        ->schema([
                            Forms\Components\TextInput::make('price')->label('MRP')->numeric()->prefix('₹')
                                ->required()->minValue(0)->live(onBlur: true)
                                ->helperText('Tax-inclusive MRP. Charged unless a selling price is set.'),
                            Forms\Components\TextInput::make('discounted_price')->label('Selling price')->numeric()->prefix('₹')->minValue(0)
                                ->live(onBlur: true)
                                ->helperText('Leave blank to charge the MRP directly.'),
                            Forms\Components\TextInput::make('stock')->numeric()->integer()->default(0)->minValue(0),
                            Forms\Components\TextInput::make('reserved')->numeric()->integer()->default(0)->minValue(0),
                            Forms\Components\TextInput::make('sort_order')->numeric()->integer()->default(0)
                                ->helperText('Lower numbers show first.'),
                            Forms\Components\Toggle::make('is_active')->label('Active')->default(true)
                                ->helperText('Off hides this product from the storefront.'),
                            Forms\Components\Placeholder::make('tax_breakdown')
                                ->label('Tax breakdown at this price')
                                ->content(function (Forms\Get $get) {
                                    $price = (float) ($get('discounted_price') ?: $get('price') ?: 0);
                                    if ($price <= 0) {
                                        return 'Enter a price to see the base/tax split.';
                                    }
                                    $rate = (float) (Category::find($get('category_id'))?->tax_rate ?? 0);
                                    $base = $rate > 0 ? round($price / (1 + $rate / 100), 2) : round($price, 2);
                                    $tax = round($price - $base, 2);

                                    return "Base price ₹{$base} + Tax ({$rate}%) ₹{$tax} = ₹{$price}";
                                })
                                ->columnSpanFull(),
                        ])->columns(3),

                    Forms\Components\Tabs\Tab::make('Pack sizes')
                        ->icon('heroicon-o-squares-2x2')
                        ->schema([
                            Forms\Components\Select::make('parent_id')
                                ->label('Pack size/weight variant of')
                                ->relationship(
                                    name: 'parent',
                                    titleAttribute: 'name',
                                    modifyQueryUsing: fn (Builder $query) => $query->whereNull('parent_id'),
                                    ignoreRecord: true,
                                )
                                ->searchable()->preload()
                                ->helperText('Leave blank for a standalone product. Pick an existing product to make this a different pack size of it (e.g. link "Aata 10kg" to "Aata 5kg") — it still gets its own price, stock and SKU; the app shows them together as a pack-size picker.'),
                            Forms\Components\TextInput::make('variant_label')
                                ->label('Variant label')
                                ->maxLength(255)
                                ->placeholder('e.g. 5kg, 10kg, 200g')
                                ->helperText('Shown as this pack size\'s option label in the app.'),
                        ])->columns(2),

                    Forms\Components\Tabs\Tab::make('Freebie')
                        ->icon('heroicon-o-gift')
                        ->schema([
                            Forms\Components\Toggle::make('is_freebie')
                                ->label('This product is a freebie')
                                ->helperText('Once the rest of the cart reaches the minimum order value below, this product becomes addable at the freebie price. The max freebie units per order is set once in Settings > Store settings.')
                                ->live()
                                ->columnSpanFull(),
                            Forms\Components\TextInput::make('freebie_price')
                                ->label('Freebie price')
                                ->numeric()->prefix('₹')->minValue(0)->default(1)
                                ->visible(fn (Forms\Get $get) => $get('is_freebie'))
                                ->required(fn (Forms\Get $get) => $get('is_freebie')),
                            Forms\Components\TextInput::make('freebie_min_order_value')
                                ->label('Minimum order value')
                                ->numeric()->prefix('₹')->minValue(0)->default(0)
                                ->visible(fn (Forms\Get $get) => $get('is_freebie'))
                                ->required(fn (Forms\Get $get) => $get('is_freebie'))
                                ->helperText('Rest of the cart (excluding freebie products) must reach this.'),
                        ])->columns(2),

                    Forms\Components\Tabs\Tab::make('Images')
                        ->icon('heroicon-o-photo')
                        ->schema([
                            Forms\Components\FileUpload::make('images')
                                ->multiple()->image()->reorderable()->appendFiles()
                                ->disk('web')->directory('uploads')->visibility('public')
                                ->columnSpanFull(),
                        ]),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->headerActions([
                ImportAction::make()
                    ->importer(ProductImporter::class)
                    ->label('Bulk import via CSV'),
            ])
            ->columns([
                Tables\Columns\ImageColumn::make('images')->disk('web')->stacked()->limit(3)->label('Images')->circular(),
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('item_code')->label('Item code')->searchable()->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('sku')->label('SKU')->searchable()->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('hsn_sac_code')->label('HSN/SAC')->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('brand.name')->label('Brand')->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('category.name')->badge()->sortable()->searchable(),
                Tables\Columns\TextColumn::make('category.tax_rate')->label('Tax')->suffix('%')->toggleable(),
                Tables\Columns\TextColumn::make('price')->label('MRP')->money('INR')->sortable(),
                Tables\Columns\TextColumn::make('discounted_price')->label('Selling price')->money('INR')->placeholder('—')->sortable(),
                Tables\Columns\TextColumn::make('unit')->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('parent.name')->label('Variant of')->placeholder('—')->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('variant_label')->label('Pack size')->placeholder('—')->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('tags')->badge()->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('stock')->numeric()->sortable()
                    ->color(fn ($state) => $state > 0 ? 'success' : 'danger'),
                Tables\Columns\TextColumn::make('sort_order')->numeric()->sortable()->toggleable(),
                Tables\Columns\ToggleColumn::make('is_active')->label('Active'),
                Tables\Columns\IconColumn::make('is_freebie')->label('Freebie')->boolean()->toggleable(),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('sort_order')
            ->filtersLayout(FiltersLayout::AboveContent)
            ->filters([
                Tables\Filters\SelectFilter::make('category')
                    ->relationship('category', 'name')
                    ->searchable()->preload(),
                Tables\Filters\TernaryFilter::make('is_active'),
                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('activate')
                        ->label('Activate')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->action(fn (Collection $records) => $records->each->update(['is_active' => true]))
                        ->deselectRecordsAfterCompletion(),
                    Tables\Actions\BulkAction::make('deactivate')
                        ->label('Deactivate')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->action(fn (Collection $records) => $records->each->update(['is_active' => false]))
                        ->deselectRecordsAfterCompletion(),
                    ExportBulkAction::make()
                        ->label('Export selected (CSV)')
                        ->exporter(ProductExporter::class)
                        ->formats([ExportFormat::Csv])
                        ->columnMapping(false),
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withoutGlobalScopes([
            SoftDeletingScope::class,
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProducts::route('/'),
            'create' => Pages\CreateProduct::route('/create'),
            'edit' => Pages\EditProduct::route('/{record}/edit'),
        ];
    }
}
