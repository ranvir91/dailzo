<?php

namespace App\Filament\Pages;

use App\Models\StoreSetting;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class ManageStoreSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?string $navigationGroup = 'Settings';

    protected static ?string $title = 'Store settings';

    protected static string $view = 'filament.pages.manage-store-settings';

    public ?array $data = [];

    public function mount(): void
    {
        $s = StoreSetting::current();

        $this->form->fill([
            'delivery_charges' => $s->delivery_charges,
            'tax' => $s->tax,
            'min_order_value' => $s->min_order_value,
            'min_order_value_enabled' => $s->min_order_value_enabled,
            'maintenance_mode' => $s->maintenance_mode,
            'app_version' => $s->app_version,
            'store_open_time' => $s->store_open_time,
            'store_close_time' => $s->store_close_time,
            'payment_methods' => $s->payment_methods,
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Charges')->schema([
                    TextInput::make('delivery_charges')->numeric()->prefix('₹')->required(),
                    TextInput::make('tax')->numeric()->suffix('%')->required(),
                    TextInput::make('min_order_value')->numeric()->prefix('₹')->required(),
                    Toggle::make('min_order_value_enabled')->label('Enforce minimum order value'),
                ])->columns(2),

                Section::make('Store')->schema([
                    TextInput::make('app_version')->required(),
                    TimePicker::make('store_open_time')->seconds(false)->required(),
                    TimePicker::make('store_close_time')->seconds(false)->required(),
                    TagsInput::make('payment_methods')->placeholder('COD, UPI, Cards')->required(),
                    Toggle::make('maintenance_mode')
                        ->label('Maintenance mode')
                        ->helperText('When on, the mobile app shows a maintenance screen.'),
                ])->columns(2),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        StoreSetting::current()->update($this->form->getState());

        Notification::make()->success()->title('Store settings saved')->send();
    }
}
