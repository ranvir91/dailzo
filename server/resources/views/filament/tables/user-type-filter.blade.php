{{--
    Rendered via a TOOLBAR_SEARCH_BEFORE render hook (see AdminPanelProvider),
    so it sits inline in the same toolbar row as the search box, immediately
    to its left — not in a separate row, not hidden behind a filters popover.
    wire:model.live binds straight to the page's $userType property (Pages\ListUsers).
--}}
<div class="w-full shrink-0 sm:w-44" style="width:210px">
    <x-filament::input.wrapper>
        <x-filament::input.select wire:model.live="userType">
            <option value="">All user types</option>
            @foreach ($options as $value => $label)
                <option value="{{ $value }}">{{ $label }}</option>
            @endforeach
        </x-filament::input.select>
    </x-filament::input.wrapper>
</div>
