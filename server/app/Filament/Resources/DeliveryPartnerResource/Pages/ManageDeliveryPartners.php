<?php

namespace App\Filament\Resources\DeliveryPartnerResource\Pages;

use App\Filament\Resources\DeliveryPartnerResource;
use App\Models\DeliveryPartner;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageDeliveryPartners extends ManageRecords
{
    protected static string $resource = DeliveryPartnerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // The form also collects the partner's login (name/phone/password,
            // which live on a linked User) — createWithUser() persists both.
            Actions\CreateAction::make()
                ->using(fn (array $data): DeliveryPartner => DeliveryPartner::createWithUser($data)),
        ];
    }
}
