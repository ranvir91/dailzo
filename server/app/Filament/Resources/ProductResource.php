<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductResource\Pages;
use App\Models\Category;
use App\Models\Product;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static ?string $navigationIcon = 'heroicon-o-cube';

    protected static ?string $navigationGroup = 'Catalog';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make()->schema([
                Forms\Components\Select::make('item_type')
                    ->options(['GOODS' => 'Goods', 'SERVICE' => 'Service'])
                    ->default('GOODS')->required(),
                Forms\Components\TextInput::make('name')->label('Item name')->required()->maxLength(255),
                Forms\Components\Select::make('category_id')
                    ->label('Item category')
                    ->relationship('category', 'name')
                    ->searchable()->preload()->required()
                    ->live(),
                Forms\Components\TextInput::make('item_code')->maxLength(255),
                Forms\Components\TextInput::make('sku')->label('SKU code')->maxLength(255)->unique(ignoreRecord: true),
                Forms\Components\TextInput::make('hsn_sac_code')->label('HSN/SAC code')->maxLength(255)
                    ->helperText('Required on GST invoices.'),
                Forms\Components\TextInput::make('brand')->label('Brand / Make')->maxLength(255),
                Forms\Components\Select::make('unit')->label('Item unit')
                    ->options([
                        'PCS' => 'PCS', 'KG' => 'KG', 'GM' => 'GM', 'L' => 'L',
                        'ML' => 'ML', 'BOX' => 'BOX', 'PACK' => 'PACK', 'DOZEN' => 'DOZEN',
                    ])
                    ->searchable(),
                Forms\Components\TextInput::make('part_no')->label('Part no.')->maxLength(255),
                Forms\Components\Textarea::make('description')->rows(3)->columnSpanFull(),
            ])->columns(3),

            Forms\Components\Section::make('Pricing & stock')->schema([
                Forms\Components\TextInput::make('price')->label('Sales price')->numeric()->prefix('₹')
                    ->required()->minValue(0)->live(onBlur: true)
                    ->helperText('Tax-inclusive — this is what the customer pays.'),
                Forms\Components\TextInput::make('mrp')->numeric()->prefix('₹')->minValue(0)
                    ->helperText('Leave blank to just use the sales price.'),
                Forms\Components\TextInput::make('discounted_price')->numeric()->prefix('₹')->minValue(0)
                    ->live(onBlur: true)
                    ->helperText('Leave blank for no discount.'),
                Forms\Components\TextInput::make('purchase_price')->label('Purchase price')->numeric()->prefix('₹')->minValue(0)
                    ->helperText('Your cost — not shown to customers.'),
                Forms\Components\TextInput::make('stock')->numeric()->integer()->default(0)->minValue(0),
                Forms\Components\TextInput::make('reserved')->numeric()->integer()->default(0)->minValue(0),
                Forms\Components\TextInput::make('sort_order')->numeric()->integer()->default(0)
                    ->helperText('Lower numbers show first.'),
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

            Forms\Components\Section::make('Images')->schema([
                Forms\Components\FileUpload::make('images')
                    ->multiple()->image()->reorderable()->appendFiles()
                    ->disk('web')->directory('uploads')->visibility('public')
                    ->columnSpanFull(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('images')->disk('web')->stacked()->limit(3)->label('')->circular(),
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('item_code')->label('Item code')->searchable()->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('sku')->label('SKU')->searchable()->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('hsn_sac_code')->label('HSN/SAC')->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('brand')->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('category.name')->badge()->sortable(),
                Tables\Columns\TextColumn::make('category.tax_rate')->label('Tax')->suffix('%')->toggleable(),
                Tables\Columns\TextColumn::make('mrp')->money('INR')->placeholder('—')->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('price')->label('Sales price')->money('INR')->sortable(),
                Tables\Columns\TextColumn::make('discounted_price')->money('INR')->placeholder('—')->sortable(),
                Tables\Columns\TextColumn::make('purchase_price')->money('INR')->placeholder('—')->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('unit')->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('stock')->numeric()->sortable()
                    ->color(fn ($state) => $state > 0 ? 'success' : 'danger'),
                Tables\Columns\TextColumn::make('sort_order')->numeric()->sortable()->toggleable(),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('sort_order')
            ->filters([
                Tables\Filters\SelectFilter::make('category')->relationship('category', 'name'),
                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withoutGlobalScopes([
            \Illuminate\Database\Eloquent\SoftDeletingScope::class,
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
