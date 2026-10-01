<?php

namespace App\Filament\Octa\Widgets;

use App\Filament\Octa\Resources\OrderResource;
use App\Models\Order;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class RecentOrders extends BaseWidget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Recent orders')
            ->recordUrl(fn (Order $record) => OrderResource::getUrl('view', ['record' => $record]))
            ->query(
                Order::query()
                    // 0 never matches a real id — safe "nothing" fallback
                    // now that ids are auto-increment ints, not UUIDs.
                    ->where('vendor_id', auth()->user()->vendorProfile?->id ?? 0)
                    ->latest(),
            )
            ->columns([
                Tables\Columns\TextColumn::make('id')->label('Order #'),
                Tables\Columns\TextColumn::make('user.name')->label('Customer'),
                Tables\Columns\TextColumn::make('activeAssignment.deliveryPartner.name')->label('Delivery partner')->placeholder('Unassigned'),
                Tables\Columns\TextColumn::make('status')->badge()
                    ->color(fn (string $state) => match (true) {
                        $state === 'DELIVERED' => 'success',
                        $state === 'CANCELLED' => 'danger',
                        in_array($state, ['OUT_FOR_DELIVERY', 'PICKED_UP', 'ASSIGNED']) => 'warning',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('total')->money('INR'),
                Tables\Columns\TextColumn::make('created_at')->since()->label('Placed'),
            ])
            ->paginated([5, 10, 25])
            ->defaultPaginationPageOption(5);
    }
}
