<?php

namespace App\Filament\Resources\OfferResource\Pages;

use App\Filament\Resources\OfferResource;
use App\Filament\Resources\OfferResource\Pages\Concerns\NormalizesOfferDates;
use Filament\Resources\Pages\CreateRecord;

class CreateOffer extends CreateRecord
{
    use NormalizesOfferDates;

    protected static string $resource = OfferResource::class;
}
