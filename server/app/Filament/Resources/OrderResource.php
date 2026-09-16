<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OrderResource\Pages;
use App\Models\Order;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static ?string $navigationIcon = 'heroicon-o-shopping-bag';

    protected static ?string $navigationGroup = 'Sales';

    protected static ?int $navigationSort = 1;

    public static function canCreate(): bool
    {
        return false;
    }

    protected static function statusOptions(): array
    {
        $all = array_unique(array_merge(
            array_keys(Order::STATUS_TRANSITIONS),
            ...array_values(Order::STATUS_TRANSITIONS),
        ));

        return array_combine($all, $all);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('status')
                ->options(self::statusOptions())
                ->required()
                ->helperText('Only valid transitions are accepted; the storefront enforces the same rules.'),
        ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Section::make()->schema([
                Infolists\Components\TextEntry::make('order_number')->label('Order #'),
                Infolists\Components\TextEntry::make('user.name')->label('Customer'),
                Infolists\Components\TextEntry::make('user.phone')->label('Phone'),
                Infolists\Components\TextEntry::make('status')->badge(),
                Infolists\Components\TextEntry::make('payment_method'),
                Infolists\Components\TextEntry::make('total')->money('INR'),
                Infolists\Components\TextEntry::make('created_at')->dateTime(),
            ])->columns(3),
            Infolists\Components\RepeatableEntry::make('items')->schema([
                Infolists\Components\TextEntry::make('product.name')->label('Product'),
                Infolists\Components\TextEntry::make('quantity'),
                Infolists\Components\TextEntry::make('price')->money('INR'),
            ])->columns(3),
            Infolists\Components\RepeatableEntry::make('comments')
                ->label('Delivery notes')
                ->schema([
                    Infolists\Components\TextEntry::make('body')->label('')->columnSpanFull(),
                    Infolists\Components\TextEntry::make('deliveryPartner.name')->label('By')->placeholder('—'),
                    Infolists\Components\TextEntry::make('type')->badge(),
                    Infolists\Components\TextEntry::make('created_at')->dateTime(),
                ])->columns(3)
                ->visible(fn ($record) => $record->comments->isNotEmpty()),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('order_number')->label('Order #')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('user.name')->label('Customer')->searchable(),
                Tables\Columns\TextColumn::make('status')->badge()->sortable()
                    ->color(fn (string $state) => match (true) {
                        in_array($state, ['DELIVERED']) => 'success',
                        in_array($state, ['CANCELLED']) => 'danger',
                        in_array($state, ['OUT_FOR_DELIVERY', 'PICKED_UP', 'ASSIGNED']) => 'warning',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('total')->money('INR')->sortable(),
                Tables\Columns\TextColumn::make('payment_method')->badge()->toggleable(),
                Tables\Columns\TextColumn::make('items_count')->counts('items')->label('Items'),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options(self::statusOptions()),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\Action::make('updateStatus')
                    ->label('Update status')
                    ->icon('heroicon-o-arrow-path')
                    ->form(fn (Order $record) => [
                        Forms\Components\Select::make('status')
                            ->options(collect(Order::STATUS_TRANSITIONS[$record->status] ?? [])
                                ->mapWithKeys(fn ($s) => [$s => $s])->all())
                            ->required(),
                    ])
                    ->visible(fn (Order $record) => ! empty(Order::STATUS_TRANSITIONS[$record->status] ?? []))
                    ->action(fn (Order $record, array $data) => $record->update(['status' => $data['status']])),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOrders::route('/'),
            'view' => Pages\ViewOrder::route('/{record}'),
            'edit' => Pages\EditOrder::route('/{record}/edit'),
        ];
    }
}
