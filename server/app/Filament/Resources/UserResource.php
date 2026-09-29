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

    protected static ?int $navigationSort = 1;

    /**
     * Shared with the inline "Show" select rendered via a render hook (see
     * AdminPanelProvider + resources/views/filament/tables/user-type-filter)
     * so the two never drift apart.
     *
     * @return array<string, string>
     */
    public static function roleFilterOptions(): array
    {
        return [
            'CUSTOMER' => 'Users',
            'VENDOR' => 'Vendors',
            'DELIVERY_PARTNER' => 'Delivery partners',
        ];
    }

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
                    ->color(fn (string $state) => match ($state) {
                        'ADMIN' => 'warning',
                        'VENDOR' => 'info',
                        'DELIVERY_PARTNER' => 'success',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('orders_count')->counts('orders')->label('Orders'),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            // The "Show" (user type) control is NOT a Filament filter — it's
            // rendered as a plain always-visible select immediately left of
            // the search box (see AdminPanelProvider's render hook +
            // Pages\ListUsers::$userType), so it doesn't share the Trashed
            // filter's popover layout or visibility.
            ->modifyQueryUsing(fn (Builder $query, $livewire) => $query->when(
                $livewire->userType ?? null,
                fn (Builder $query, string $type) => $query->where('role', $type),
            ))
            ->filters([
                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                // Vendors/delivery partners are visible here (via "All user
                // types") for a unified view, but this form's role select only
                // knows Customer/Admin — editing one here would corrupt its
                // role and orphan its vendors/delivery_partners profile row.
                // Their own resources (People > Vendors / Delivery Partners) do
                // the correct dual-table save; this stays read-only for them.
                Tables\Actions\EditAction::make()
                    ->visible(fn (User $record) => in_array($record->role, ['CUSTOMER', 'ADMIN'], true)),
                Tables\Actions\DeleteAction::make()
                    ->visible(fn (User $record) => in_array($record->role, ['CUSTOMER', 'ADMIN'], true)),
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
