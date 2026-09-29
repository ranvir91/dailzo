<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DeliveryPartnerResource\Pages;
use App\Models\DeliveryPartner;
use App\Models\Vendor;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Validation\Rules\Unique;

class DeliveryPartnerResource extends Resource
{
    protected static ?string $model = DeliveryPartner::class;

    protected static ?string $navigationIcon = 'heroicon-o-truck';

    protected static ?string $navigationGroup = 'People';

    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')->required(),
            // name/phone/password here collect the partner's login (a User,
            // role = DELIVERY_PARTNER) — DeliveryPartner::createWithUser() /
            // ->updateWithUser() (see Pages\ManageDeliveryPartners and the
            // table's EditAction below) persist them there, not on this record.
            Forms\Components\TextInput::make('phone')->tel()->required()
                // Validated against users.phone (that's the actual login table
                // now), not delivery_partners — ignoreRecord: true would compare
                // the wrong id space (this record's id isn't users.id), so the
                // record's own linked user is excluded explicitly instead.
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
            Forms\Components\Select::make('vendor_id')
                ->label('Vendor')
                ->options(fn () => Vendor::active()->orderBy('business_name')->pluck('business_name', 'id'))
                ->searchable()
                ->helperText('Which vendor this partner delivers for. Leave blank if unassigned.'),
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
                Tables\Columns\TextColumn::make('vendor.business_name')->label('Vendor')->placeholder('—')->searchable(),
                Tables\Columns\ToggleColumn::make('is_active'),
                Tables\Columns\TextColumn::make('assignments_count')->counts('assignments')->label('Deliveries'),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable()->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('vendor_id')
                    ->label('Vendor')
                    ->options(fn () => Vendor::active()->orderBy('business_name')->pluck('business_name', 'id')),
                Tables\Filters\TernaryFilter::make('is_active'),
                Tables\Filters\TrashedFilter::make(),
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

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withoutGlobalScopes([SoftDeletingScope::class]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageDeliveryPartners::route('/'),
        ];
    }
}
