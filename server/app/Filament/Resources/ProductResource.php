<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductResource\Pages;
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
                Forms\Components\TextInput::make('name')->required()->maxLength(255),
                Forms\Components\Select::make('category_id')
                    ->relationship('category', 'name')
                    ->searchable()->preload()->required(),
                Forms\Components\Textarea::make('description')->rows(3)->columnSpanFull(),
                Forms\Components\TextInput::make('sku')->maxLength(255)->unique(ignoreRecord: true),
            ])->columns(2),

            Forms\Components\Section::make('Pricing & stock')->schema([
                Forms\Components\TextInput::make('price')->numeric()->prefix('₹')->required()->minValue(0),
                Forms\Components\TextInput::make('discounted_price')->numeric()->prefix('₹')->minValue(0)
                    ->helperText('Leave blank for no discount.'),
                Forms\Components\TextInput::make('stock')->numeric()->integer()->default(0)->minValue(0),
                Forms\Components\TextInput::make('reserved')->numeric()->integer()->default(0)->minValue(0),
                Forms\Components\TextInput::make('sort_order')->numeric()->integer()->default(0)
                    ->helperText('Lower numbers show first.'),
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
                Tables\Columns\TextColumn::make('category.name')->badge()->sortable(),
                Tables\Columns\TextColumn::make('price')->money('INR')->sortable(),
                Tables\Columns\TextColumn::make('discounted_price')->money('INR')->placeholder('—')->sortable(),
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
