<?php

namespace App\Filament\Resources;

use App\Filament\Resources\VendorResource\Pages;
use App\Models\ServicePincode;
use App\Models\Vendor;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Validation\Rules\Unique;

class VendorResource extends Resource
{
    protected static ?string $model = Vendor::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-storefront';

    protected static ?string $navigationGroup = 'People';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('business_name')->required(),
            // name/phone/password collect the vendor's own login (a User,
            // role = VENDOR, used to sign in to the Octa panel) —
            // Vendor::createWithUser()/->updateWithUser() persist them there,
            // this form doesn't write name/phone/password to the vendors table.
            Forms\Components\TextInput::make('name')->label('Contact name')->required(),
            Forms\Components\TextInput::make('phone')->tel()->required()
                ->unique('users', 'phone', modifyRuleUsing: function (Unique $rule, ?Vendor $record) {
                    $rule->where('role', 'VENDOR');
                    if ($record?->user_id) {
                        $rule->ignore($record->user_id);
                    }

                    return $rule;
                })
                ->helperText('This is the login you share with the vendor for the Octa panel.'),
            Forms\Components\TextInput::make('password')
                ->password()->revealable()
                ->required(fn (string $operation): bool => $operation === 'create')
                ->dehydrated(fn (?string $state): bool => filled($state))
                ->helperText('Leave blank to keep the current password. Share the phone + password with the vendor to log in at /octa.'),
            Forms\Components\Select::make('service_pincode_ids')
                ->label('Serviceable pincodes covered')
                ->multiple()
                ->options(fn () => ServicePincode::active()->orderBy('pincode')->get()
                    ->mapWithKeys(fn (ServicePincode $p) => [$p->id => $p->area_name ? "{$p->pincode} — {$p->area_name}" : $p->pincode]))
                ->searchable()
                ->helperText('Orders to these pincodes can be routed to this vendor.'),
            Forms\Components\Toggle::make('is_active')->default(true)
                ->helperText('Inactive vendors cannot log in to Octa or receive new orders.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->label('#')->sortable(),
                Tables\Columns\TextColumn::make('business_name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('user.phone')->label('Login phone')->searchable(),
                Tables\Columns\TextColumn::make('servicePincodes_count')->counts('servicePincodes')->label('Pincodes'),
                Tables\Columns\TextColumn::make('deliveryPartners_count')->counts('deliveryPartners')->label('Partners'),
                Tables\Columns\ToggleColumn::make('is_active'),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable()->toggleable(),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active'),
                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->mutateRecordDataUsing(function (array $data, Vendor $record): array {
                        $data['service_pincode_ids'] = $record->servicePincodes->pluck('id')->all();

                        return $data;
                    })
                    ->using(function (Vendor $record, array $data): Vendor {
                        $record->updateWithUser($data);

                        return $record->fresh();
                    }),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withoutGlobalScopes([SoftDeletingScope::class]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageVendors::route('/'),
        ];
    }
}
