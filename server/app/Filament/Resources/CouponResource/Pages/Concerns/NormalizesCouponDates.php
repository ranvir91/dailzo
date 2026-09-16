<?php

namespace App\Filament\Resources\CouponResource\Pages\Concerns;

use Carbon\CarbonImmutable;

/**
 * A bare date from the picker means the whole day in IST: a coupon "expiring on
 * the 15th" is valid through 23:59 IST on the 15th, not from its start. Keeps the
 * panel consistent with the API's coupon date parsing.
 */
trait NormalizesCouponDates
{
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return $this->normalizeCouponDates($data);
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return $this->normalizeCouponDates($data);
    }

    private function normalizeCouponDates(array $data): array
    {
        if (! empty($data['starts_at'])) {
            $data['starts_at'] = CarbonImmutable::parse($data['starts_at'], 'Asia/Kolkata')->startOfDay();
        }

        if (! empty($data['expires_at'])) {
            $data['expires_at'] = CarbonImmutable::parse($data['expires_at'], 'Asia/Kolkata')->endOfDay();
        }

        return $data;
    }
}
