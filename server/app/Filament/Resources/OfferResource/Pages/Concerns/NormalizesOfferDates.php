<?php

namespace App\Filament\Resources\OfferResource\Pages\Concerns;

use Carbon\CarbonImmutable;

/**
 * Same rationale as CouponResource's NormalizesCouponDates: a bare date from
 * the picker (or from the duration-preset select) means the whole day in
 * IST — an offer "expiring on the 15th" stays visible through 23:59 IST on
 * the 15th, not from its start.
 */
trait NormalizesOfferDates
{
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return $this->normalizeOfferDates($data);
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return $this->normalizeOfferDates($data);
    }

    private function normalizeOfferDates(array $data): array
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
