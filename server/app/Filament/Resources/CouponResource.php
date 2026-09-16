<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CouponResource\Pages;
use App\Models\Coupon;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class CouponResource extends Resource
{
    protected static ?string $model = Coupon::class;

    protected static ?string $navigationIcon = 'heroicon-o-ticket';

    protected static ?string $navigationGroup = 'Marketing';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make()->schema([
                Forms\Components\TextInput::make('code')
                    ->required()->unique(ignoreRecord: true)
                    ->dehydrateStateUsing(fn (string $state) => strtoupper(trim($state))),
                Forms\Components\Select::make('type')
                    ->options(['FIXED' => 'Fixed ₹', 'PERCENTAGE' => 'Percentage %'])
                    ->required()->live(),
                Forms\Components\TextInput::make('discount')->numeric()->required()->minValue(0)
                    ->maxValue(fn (Forms\Get $get) => $get('type') === 'PERCENTAGE' ? 100 : null),
                Forms\Components\TextInput::make('max_discount_amount')->numeric()->prefix('₹')
                    ->visible(fn (Forms\Get $get) => $get('type') === 'PERCENTAGE')
                    ->helperText('Cap on the discount amount.'),
                Forms\Components\TextInput::make('min_order_value')->numeric()->prefix('₹')->default(0),
                Forms\Components\Textarea::make('description')->rows(2)->columnSpanFull(),
            ])->columns(2),

            Forms\Components\Section::make('Validity & limits')->schema([
                Forms\Components\DatePicker::make('starts_at')->label('Starts on')->timezone('Asia/Kolkata'),
                Forms\Components\DatePicker::make('expires_at')->label('Expires on')->timezone('Asia/Kolkata'),
                Forms\Components\TextInput::make('usage_limit')->numeric()->integer()->helperText('Total redemptions allowed. Blank = unlimited.'),
                Forms\Components\TextInput::make('per_user_limit')->numeric()->integer()->default(1),
                Forms\Components\Toggle::make('first_order_only'),
                Forms\Components\Toggle::make('is_active')->default(true),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')->searchable()->weight('bold'),
                Tables\Columns\TextColumn::make('type')->badge(),
                Tables\Columns\TextColumn::make('discount')
                    ->formatStateUsing(fn ($state, Coupon $r) => $r->type === 'PERCENTAGE' ? "{$state}%" : "₹{$state}"),
                Tables\Columns\TextColumn::make('min_order_value')->money('INR')->label('Min order')->toggleable(),
                Tables\Columns\TextColumn::make('expires_at')->date()->placeholder('No expiry')->sortable(),
                Tables\Columns\ToggleColumn::make('is_active'),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('type')->options(['FIXED' => 'Fixed', 'PERCENTAGE' => 'Percentage']),
                Tables\Filters\TernaryFilter::make('is_active'),
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
        return parent::getEloquentQuery()->withoutGlobalScopes([SoftDeletingScope::class]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCoupons::route('/'),
            'create' => Pages\CreateCoupon::route('/create'),
            'edit' => Pages\EditCoupon::route('/{record}/edit'),
        ];
    }
}
