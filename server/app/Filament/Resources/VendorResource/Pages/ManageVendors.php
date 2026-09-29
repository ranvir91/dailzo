<?php

namespace App\Filament\Resources\VendorResource\Pages;

use App\Filament\Resources\VendorResource;
use App\Models\Vendor;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageVendors extends ManageRecords
{
    protected static string $resource = VendorResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // The form also collects the vendor's login (name/phone/password,
            // which live on a linked User) — createWithUser() persists both.
            Actions\CreateAction::make()
                ->using(fn (array $data): Vendor => Vendor::createWithUser($data)),
        ];
    }
}
