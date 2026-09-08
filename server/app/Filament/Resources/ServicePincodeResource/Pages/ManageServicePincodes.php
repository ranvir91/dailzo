<?php

namespace App\Filament\Resources\ServicePincodeResource\Pages;

use App\Filament\Resources\ServicePincodeResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageServicePincodes extends ManageRecords
{
    protected static string $resource = ServicePincodeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
