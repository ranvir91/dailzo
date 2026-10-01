<?php

namespace App\Filament\Octa\Resources;

use App\Filament\Octa\Resources\OrderResource\Pages;
use App\Models\DeliveryPartner;
use App\Models\Order;
use App\Services\DeliveryAssignmentService;
use Filament\Forms;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Orders superadmin has routed to the logged-in vendor. Read-mostly — a
 * vendor's one action here is assigning one of their own delivery partners;
 * the order's overall status lifecycle stays superadmin's (Atlas) and the
 * delivery-partner mobile app's job.
 */
class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static ?string $navigationIcon = 'heroicon-o-shopping-bag';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('vendor_id', auth()->user()->vendorProfile->id);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Section::make()->schema([
                Infolists\Components\TextEntry::make('id')->label('Order #'),
                Infolists\Components\TextEntry::make('user.name')->label('Customer'),
                Infolists\Components\TextEntry::make('user.phone')->label('Phone'),
                Infolists\Components\TextEntry::make('user.email')->label('Email')->placeholder('—'),
                Infolists\Components\TextEntry::make('status')->badge(),
                Infolists\Components\TextEntry::make('payment_method')->label('Payment method'),
                Infolists\Components\TextEntry::make('activeAssignment.deliveryPartner.name')->label('Delivery partner')->placeholder('Unassigned'),
                Infolists\Components\TextEntry::make('created_at')->label('Placed on')->dateTime(),
                Infolists\Components\TextEntry::make('updated_at')->label('Last updated')->dateTime(),
                Infolists\Components\TextEntry::make('address')
                    ->label('Delivery address')
                    ->state(fn (Order $record) => $record->address
                        ? collect([
                            $record->address->line1,
                            $record->address->line2,
                            $record->address->city,
                            $record->address->state,
                            $record->address->pincode,
                        ])->filter()->implode(', ')
                        : null)
                    ->placeholder('No address on file')
                    ->columnSpan(2),
            ])->columns(3),
            Infolists\Components\Section::make('Payment summary')->schema([
                Infolists\Components\TextEntry::make('items_subtotal')
                    ->label('Items subtotal')
                    ->state(fn (Order $record) => $record->items->sum(fn ($item) => $item->price * $item->quantity))
                    ->money('INR'),
                Infolists\Components\TextEntry::make('coupon.code')->label('Coupon')->placeholder('No coupon applied'),
                Infolists\Components\TextEntry::make('discount_amount')->label('Discount')->money('INR')
                    ->visible(fn (Order $record) => (float) $record->discount_amount > 0),
                Infolists\Components\TextEntry::make('total')->label('Total charged')->money('INR')->weight('bold'),
            ])->columns(4),
            Infolists\Components\RepeatableEntry::make('items')->label('Items')->schema([
                Infolists\Components\ImageEntry::make('product_image')
                    ->label('')
                    ->state(fn ($record) => $record->product?->images[0] ?? null)
                    ->disk('web')
                    ->height(48)
                    ->width(48)
                    ->circular(),
                Infolists\Components\TextEntry::make('product.name')->label('Product')->placeholder('Deleted product'),
                Infolists\Components\TextEntry::make('quantity'),
                Infolists\Components\TextEntry::make('price')->label('Unit price')->money('INR'),
                Infolists\Components\TextEntry::make('line_total')
                    ->label('Line total')
                    ->state(fn ($record) => $record->price * $record->quantity)
                    ->money('INR'),
            ])->columns(5),
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
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['items.product', 'coupon']))
            ->columns([
                Tables\Columns\ImageColumn::make('item_images')
                    ->label('')
                    ->state(fn (Order $record) => $record->items
                        ->map(fn ($item) => $item->product?->images[0] ?? null)
                        ->filter()
                        ->values()
                        ->all())
                    ->disk('web')
                    ->stacked()
                    ->limit(3)
                    ->circular(),
                Tables\Columns\TextColumn::make('id')->label('Order #')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('user.name')->label('Customer')->searchable(),
                Tables\Columns\TextColumn::make('activeAssignment.deliveryPartner.name')->label('Delivery partner')->placeholder('Unassigned'),
                Tables\Columns\TextColumn::make('status')->badge()->sortable()
                    ->color(fn (string $state) => match (true) {
                        in_array($state, ['DELIVERED']) => 'success',
                        in_array($state, ['CANCELLED']) => 'danger',
                        in_array($state, ['OUT_FOR_DELIVERY', 'PICKED_UP', 'ASSIGNED']) => 'warning',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('coupon.code')->label('Coupon')->placeholder('—')->toggleable(),
                Tables\Columns\TextColumn::make('discount_amount')->label('Discount')->money('INR')
                    ->placeholder('—')->toggleable(),
                Tables\Columns\TextColumn::make('total')->money('INR')->sortable(),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options(array_combine(
                    $statuses = array_unique(array_merge(
                        array_keys(Order::STATUS_TRANSITIONS),
                        ...array_values(Order::STATUS_TRANSITIONS),
                    )),
                    $statuses,
                )),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\Action::make('assignDeliveryPartner')
                    ->label(fn (Order $record) => $record->activeAssignment ? 'Reassign delivery partner' : 'Assign delivery partner')
                    ->icon('heroicon-o-truck')
                    ->form(fn (Order $record) => [
                        Forms\Components\Select::make('delivery_partner_id')
                            ->label('Delivery partner')
                            ->options(fn () => DeliveryPartner::active()
                                ->where('vendor_id', auth()->user()->vendorProfile->id)
                                ->orderBy('name')
                                ->pluck('name', 'id'))
                            ->searchable()
                            ->required(),
                    ])
                    ->action(function (Order $record, array $data) {
                        $partner = DeliveryPartner::where('vendor_id', auth()->user()->vendorProfile->id)
                            ->findOrFail($data['delivery_partner_id']);
                        app(DeliveryAssignmentService::class)->assign($record, $partner);
                    })
                    // A delivered order is done — nothing left to reassign.
                    ->visible(fn (Order $record) => $record->status !== 'DELIVERED'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('bulkAssignDeliveryPartner')
                        ->label('Assign delivery partner')
                        ->icon('heroicon-o-truck')
                        ->form([
                            Forms\Components\Select::make('delivery_partner_id')
                                ->label('Delivery partner')
                                ->options(fn () => DeliveryPartner::active()
                                    ->where('vendor_id', auth()->user()->vendorProfile->id)
                                    ->orderBy('name')
                                    ->pluck('name', 'id'))
                                ->searchable()
                                ->required(),
                        ])
                        ->action(function (Collection $records, array $data) {
                            // Same guard as the row action: re-resolve the
                            // partner scoped to this vendor, so a tampered
                            // request can't hand an order to another
                            // vendor's partner. The orders themselves are
                            // already scoped by getEloquentQuery() above.
                            $partner = DeliveryPartner::where('vendor_id', auth()->user()->vendorProfile->id)
                                ->findOrFail($data['delivery_partner_id']);
                            $service = app(DeliveryAssignmentService::class);
                            self::assignableOnly($records)->each(
                                fn (Order $order) => $service->assign($order, $partner)
                            );
                        })
                        ->deselectRecordsAfterCompletion(),
                ]),
            ]);
    }

    /** Delivered orders are excluded from bulk reassignment — same rule as the row action. */
    protected static function assignableOnly(Collection $records): Collection
    {
        $assignable = $records->reject(fn (Order $order) => $order->status === 'DELIVERED');

        if ($assignable->count() < $records->count()) {
            Notification::make()
                ->warning()
                ->title('Delivered orders skipped')
                ->body('Delivered orders can no longer be reassigned, so they were left untouched.')
                ->send();
        }

        return $assignable;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOrders::route('/'),
            'view' => Pages\ViewOrder::route('/{record}'),
        ];
    }
}
