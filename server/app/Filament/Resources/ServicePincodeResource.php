<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ServicePincodeResource\Pages;
use App\Models\ServicePincode;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ServicePincodeResource extends Resource
{
    protected static ?string $model = ServicePincode::class;

    protected static ?string $navigationIcon = 'heroicon-o-map-pin';

    protected static ?string $navigationGroup = 'Settings';

    protected static ?string $modelLabel = 'serviceable pincode';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('pincode')->required()->unique(ignoreRecord: true),
            Forms\Components\Toggle::make('is_active')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('pincode')->searchable()->sortable(),
                Tables\Columns\ToggleColumn::make('is_active'),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable()->toggleable(),
            ])
            ->defaultSort('pincode')
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active'),
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

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageServicePincodes::route('/'),
        ];
    }
}
