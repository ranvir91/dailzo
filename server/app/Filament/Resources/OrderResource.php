<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OrderResource\Pages;
use App\Models\DeliveryPartner;
use App\Models\Order;
use App\Models\Vendor;
use App\Services\DeliveryAssignmentService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Collection;

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

    /** Vendors, with ones covering the order's delivery pincode listed first and labelled. */
    protected static function vendorOptionsFor(Order $order): array
    {
        $pincode = $order->address?->pincode;

        return Vendor::active()
            ->with('servicePincodes')
            ->orderBy('business_name')
            ->get()
            ->sortByDesc(fn (Vendor $vendor) => $pincode && $vendor->servicePincodes->contains('pincode', $pincode))
            ->mapWithKeys(function (Vendor $vendor) use ($pincode) {
                $covers = $pincode && $vendor->servicePincodes->contains('pincode', $pincode);
                $label = $vendor->business_name.($covers ? ' — covers this pincode' : '');

                return [$vendor->id => $label];
            })
            ->all();
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
                Infolists\Components\TextEntry::make('user.email')->label('Email')->placeholder('—'),
                Infolists\Components\TextEntry::make('status')->badge(),
                Infolists\Components\TextEntry::make('payment_method')->label('Payment method'),
                Infolists\Components\TextEntry::make('vendor.business_name')->label('Vendor')->placeholder('Unassigned'),
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
                Infolists\Components\TextEntry::make('delivery_charges')->label('Delivery charges')->money('INR')
                    ->visible(fn (Order $record) => (float) $record->delivery_charges > 0),
                Infolists\Components\TextEntry::make('coupon.code')->label('Coupon')->placeholder('No coupon applied'),
                Infolists\Components\TextEntry::make('discount_amount')->label('Discount')->money('INR')
                    ->visible(fn (Order $record) => (float) $record->discount_amount > 0),
                Infolists\Components\TextEntry::make('total')->label('Total charged')->money('INR')->weight('bold'),
            ])->columns(5),
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
            ->columns([
                Tables\Columns\TextColumn::make('order_number')->label('Order #')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('user.name')->label('Customer')->searchable(),
                Tables\Columns\TextColumn::make('vendor.business_name')->label('Vendor')->placeholder('Unassigned')->toggleable(),
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
                Tables\Filters\SelectFilter::make('vendor_id')
                    ->label('Vendor')
                    ->options(fn () => Vendor::active()->orderBy('business_name')->pluck('business_name', 'id')),
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
                // Superadmin routes each order to whichever vendor covers its
                // pincode (there can be more than one — no safe auto-assign
                // rule, so this stays manual). Always visible so it doubles as
                // "reassign vendor".
                Tables\Actions\Action::make('assignVendor')
                    ->label(fn (Order $record) => $record->vendor_id ? 'Reassign vendor' : 'Assign vendor')
                    ->icon('heroicon-o-building-storefront')
                    ->form(fn (Order $record) => [
                        Forms\Components\Select::make('vendor_id')
                            ->label('Vendor')
                            ->options(fn () => self::vendorOptionsFor($record))
                            ->default($record->vendor_id)
                            ->searchable()
                            ->required(),
                    ])
                    ->action(fn (Order $record, array $data) => $record->update(['vendor_id' => $data['vendor_id']]))
                    // Once delivered there's nothing left to route — the
                    // order can only be viewed from here on.
                    ->visible(fn (Order $record) => $record->status !== 'DELIVERED'),
                // The vendor normally does this themselves in Octa once an order
                // is routed to them; this is superadmin's retained escalation
                // path (e.g. an unresponsive vendor), so it's always available
                // too, not just before a vendor is assigned.
                Tables\Actions\Action::make('assignDeliveryPartner')
                    ->label('Assign delivery partner')
                    ->icon('heroicon-o-truck')
                    ->form(fn (Order $record) => [
                        Forms\Components\Select::make('delivery_partner_id')
                            ->label('Delivery partner')
                            ->options(fn () => DeliveryPartner::active()
                                ->when($record->vendor_id, fn ($query) => $query->where('vendor_id', $record->vendor_id))
                                ->orderBy('name')
                                ->pluck('name', 'id'))
                            ->searchable()
                            ->required(),
                    ])
                    ->action(function (Order $record, array $data) {
                        $partner = DeliveryPartner::findOrFail($data['delivery_partner_id']);
                        app(DeliveryAssignmentService::class)->assign($record, $partner);
                    })
                    ->visible(fn (Order $record) => $record->status !== 'DELIVERED'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('bulkAssignVendor')
                        ->label('Assign vendor')
                        ->icon('heroicon-o-building-storefront')
                        ->form([
                            Forms\Components\Select::make('vendor_id')
                                ->label('Vendor')
                                ->options(fn () => Vendor::active()->orderBy('business_name')->pluck('business_name', 'id'))
                                ->searchable()
                                ->required(),
                        ])
                        ->action(function (Collection $records, array $data) {
                            self::assignableOnly($records)->each(
                                fn (Order $order) => $order->update(['vendor_id' => $data['vendor_id']])
                            );
                        })
                        ->deselectRecordsAfterCompletion(),
                    // Not scoped to a single vendor's partners like the
                    // row-level action — a bulk selection can span orders
                    // from different vendors, so every active partner is
                    // offered (same superadmin override already allowed
                    // one order at a time).
                    Tables\Actions\BulkAction::make('bulkAssignDeliveryPartner')
                        ->label('Assign delivery partner')
                        ->icon('heroicon-o-truck')
                        ->form([
                            Forms\Components\Select::make('delivery_partner_id')
                                ->label('Delivery partner')
                                ->options(fn () => DeliveryPartner::active()->orderBy('name')->pluck('name', 'id'))
                                ->searchable()
                                ->required(),
                        ])
                        ->action(function (Collection $records, array $data) {
                            $partner = DeliveryPartner::findOrFail($data['delivery_partner_id']);
                            $service = app(DeliveryAssignmentService::class);
                            self::assignableOnly($records)->each(
                                fn (Order $order) => $service->assign($order, $partner)
                            );
                        })
                        ->deselectRecordsAfterCompletion(),
                ]),
            ]);
    }

    /** Delivered orders are excluded from bulk vendor/partner (re)assignment — same rule as the row actions. */
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
            'edit' => Pages\EditOrder::route('/{record}/edit'),
        ];
    }
}
