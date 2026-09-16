<?php

namespace App\Filament\Resources\CouponResource\Pages;

use App\Filament\Resources\CouponResource;
use App\Filament\Resources\CouponResource\Pages\Concerns\NormalizesCouponDates;
use Filament\Resources\Pages\CreateRecord;

class CreateCoupon extends CreateRecord
{
    use NormalizesCouponDates;

    protected static string $resource = CouponResource::class;
}
