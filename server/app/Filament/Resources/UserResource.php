<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationGroup = 'People';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')->maxLength(255),
            Forms\Components\TextInput::make('phone')->tel()->required()->unique(ignoreRecord: true),
            Forms\Components\TextInput::make('email')->email()->unique(ignoreRecord: true),
            Forms\Components\Select::make('gender')
                ->options(['male' => 'Male', 'female' => 'Female', 'other' => 'Other']),
            Forms\Components\Select::make('role')
                ->options(['CUSTOMER' => 'Customer', 'ADMIN' => 'Admin'])
                ->default('CUSTOMER')->required(),
            // The User model casts `password` as `hashed`, so the raw value is
            // hashed on save; only persist it when something was typed.
            Forms\Components\TextInput::make('password')
                ->password()->revealable()
                ->dehydrated(fn (?string $state) => filled($state))
                ->helperText('Set this only for admin panel access. Leave blank to keep unchanged.'),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user_number')->label('#')->sortable(),
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('phone')->searchable(),
                Tables\Columns\TextColumn::make('email')->searchable()->toggleable(),
                Tables\Columns\TextColumn::make('role')->badge()
                    ->color(fn (string $state) => $state === 'ADMIN' ? 'warning' : 'gray'),
                Tables\Columns\TextColumn::make('orders_count')->counts('orders')->label('Orders'),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('role')->options(['CUSTOMER' => 'Customer', 'ADMIN' => 'Admin']),
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
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
