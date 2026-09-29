<?php

namespace App\Filament\Octa\Resources;

use App\Filament\Octa\Resources\DeliveryPartnerResource\Pages;
use App\Models\DeliveryPartner;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rules\Unique;

/**
 * A vendor's own delivery partners. vendor_id is fixed to the logged-in
 * vendor (not a form field) — superadmin manages partners across every
 * vendor via the equivalent Atlas resource, which does expose vendor_id.
 */
class DeliveryPartnerResource extends Resource
{
    protected static ?string $model = DeliveryPartner::class;

    protected static ?string $navigationIcon = 'heroicon-o-truck';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('vendor_id', auth()->user()->vendorProfile->id);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')->required(),
            // name/phone/password here collect the partner's login (a User,
            // role = DELIVERY_PARTNER) — DeliveryPartner::createWithUser() /
            // ->updateWithUser() persist them there, not on this record.
            Forms\Components\TextInput::make('phone')->tel()->required()
                ->unique(
                    table: 'users',
                    column: 'phone',
                    modifyRuleUsing: function (Unique $rule, ?DeliveryPartner $record) {
                        $rule->where('role', 'DELIVERY_PARTNER');
                        if ($record?->user_id) {
                            $rule->ignore($record->user_id);
                        }

                        return $rule;
                    },
                )
                ->helperText('This is also the login for the delivery-partner mobile app.'),
            Forms\Components\TextInput::make('password')
                ->password()->revealable()
                ->required(fn (string $operation): bool => $operation === 'create')
                ->dehydrated(fn (?string $state): bool => filled($state))
                ->helperText('Leave blank to keep the current password.'),
            Forms\Components\Toggle::make('is_active')->default(true)
                ->helperText('Inactive partners cannot log in to the mobile app or receive reassignments.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('phone')->searchable(),
                Tables\Columns\ToggleColumn::make('is_active'),
                Tables\Columns\TextColumn::make('assignments_count')->counts('assignments')->label('Deliveries'),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable()->toggleable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active'),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->using(function (DeliveryPartner $record, array $data): DeliveryPartner {
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

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageDeliveryPartners::route('/'),
        ];
    }
}
