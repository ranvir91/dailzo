<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    /**
     * Bound (wire:model.live="userType") by the inline select rendered via a
     * TOOLBAR_SEARCH_BEFORE render hook — see AdminPanelProvider and
     * resources/views/filament/tables/user-type-filter.blade.php. Deliberately
     * a plain Livewire property rather than a Filament SelectFilter, so it
     * doesn't share layout/visibility with — and can't accidentally hide —
     * the table's other filters (e.g. Trashed).
     */
    public ?string $userType = 'CUSTOMER';

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
