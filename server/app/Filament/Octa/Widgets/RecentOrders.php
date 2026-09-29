<?php

namespace App\Filament\Octa\Widgets;

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
            ->query(
                Order::query()
                    ->where('vendor_id', auth()->user()->vendorProfile?->id ?? '00000000-0000-0000-0000-000000000000')
                    ->latest(),
            )
            ->columns([
                Tables\Columns\TextColumn::make('order_number')->label('Order #'),
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
