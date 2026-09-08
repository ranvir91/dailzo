<?php

namespace App\Filament\Resources\DeliveryPartnerResource\Pages;

use App\Filament\Resources\DeliveryPartnerResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageDeliveryPartners extends ManageRecords
{
    protected static string $resource = DeliveryPartnerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
