<?php

namespace App\Filament\Pages;

use App\Models\StoreSetting;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
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
            'business_name' => $s->business_name,
            'business_address' => $s->business_address,
            'business_state' => $s->business_state,
            'business_pincode' => $s->business_pincode,
            'gstin' => $s->gstin,
            'pan' => $s->pan,
            'fssai_license' => $s->fssai_license,
            'cin' => $s->cin,
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

                Section::make('Invoice details')
                    ->description('Printed on every order invoice as the seller block, and used to decide CGST+SGST vs IGST against the delivery address state.')
                    ->schema([
                        TextInput::make('business_name')->label('Legal business name')->maxLength(255),
                        TextInput::make('gstin')->label('GSTIN')->maxLength(20),
                        Textarea::make('business_address')->rows(2)->columnSpanFull(),
                        TextInput::make('business_state')->label('State'),
                        TextInput::make('business_pincode')->label('Pincode'),
                        TextInput::make('pan')->label('PAN'),
                        TextInput::make('fssai_license')->label('FSSAI license no.')
                            ->helperText('Leave blank if not applicable.'),
                        TextInput::make('cin')->label('CIN')
                            ->helperText('Leave blank if not applicable.'),
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
