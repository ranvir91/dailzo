<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DeliveryPartnerResource\Pages;
use App\Models\DeliveryPartner;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class DeliveryPartnerResource extends Resource
{
    protected static ?string $model = DeliveryPartner::class;

    protected static ?string $navigationIcon = 'heroicon-o-truck';

    protected static ?string $navigationGroup = 'Sales';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')->required(),
            Forms\Components\TextInput::make('phone')->tel()->required()->unique(ignoreRecord: true)
                ->helperText('This is also the login for the delivery-partner mobile app.'),
            // The model casts `password` as `hashed`, so the raw value is
            // hashed on save; only persist it when something was typed.
            Forms\Components\TextInput::make('password')
                ->password()->revealable()
                ->dehydrated(fn (?string $state) => filled($state))
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
                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
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
