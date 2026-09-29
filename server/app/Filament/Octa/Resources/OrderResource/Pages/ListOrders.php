<?php

namespace App\Filament\Octa\Resources\OrderResource\Pages;

use App\Filament\Octa\Resources\OrderResource;
use Filament\Resources\Pages\ListRecords;

class ListOrders extends ListRecords
{
    protected static string $resource = OrderResource::class;
}
