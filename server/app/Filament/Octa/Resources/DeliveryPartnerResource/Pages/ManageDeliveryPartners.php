<?php

namespace App\Filament\Octa\Resources\DeliveryPartnerResource\Pages;

use App\Filament\Octa\Resources\DeliveryPartnerResource;
use App\Models\DeliveryPartner;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageDeliveryPartners extends ManageRecords
{
    protected static string $resource = DeliveryPartnerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // vendor_id is forced to the logged-in vendor — it's not a field on
            // this panel's form, so a vendor can only ever create partners for
            // themselves regardless of what's submitted.
            Actions\CreateAction::make()
                ->using(fn (array $data): DeliveryPartner => DeliveryPartner::createWithUser([
                    ...$data,
                    'vendor_id' => auth()->user()->vendorProfile->id,
                ])),
        ];
    }
}
