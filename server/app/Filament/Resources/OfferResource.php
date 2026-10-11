<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OfferResource\Pages;
use App\Models\Offer;
use Carbon\Carbon;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class OfferResource extends Resource
{
    protected static ?string $model = Offer::class;

    protected static ?string $navigationIcon = 'heroicon-o-megaphone';

    protected static ?string $navigationGroup = 'Marketing';

    protected static ?int $navigationSort = 1;

    /** Quick-set presets for "show for N days" — writes straight into expires_at, nothing of its own is stored. */
    protected static array $durationPresets = [
        '7' => '7 days',
        '10' => '10 days',
        '15' => '15 days',
        '30' => '30 days',
        '60' => '60 days',
        '' => 'Custom / no auto-expiry',
    ];

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make()->schema([
                Forms\Components\TextInput::make('title')->required()->maxLength(255),
                Forms\Components\Textarea::make('description')->rows(2)->columnSpanFull(),
                Forms\Components\FileUpload::make('image')
                    ->label('Banner image')
                    ->image()
                    ->disk('web')->directory('uploads')->visibility('public')
                    ->columnSpanFull(),
            ])->columns(2),

            Forms\Components\Section::make('Validity')->schema([
                Forms\Components\DatePicker::make('starts_at')->label('Starts on')
                    ->timezone('Asia/Kolkata')
                    ->helperText('Blank = visible immediately.'),
                Forms\Components\Select::make('duration_preset')
                    ->label('Show for')
                    ->options(self::$durationPresets)
                    ->default('')
                    ->dehydrated(false)
                    ->live()
                    ->afterStateUpdated(function (Forms\Set $set, Forms\Get $get, ?string $state) {
                        if (blank($state)) {
                            return;
                        }
                        $from = $get('starts_at') ? Carbon::parse($get('starts_at')) : now();
                        $set('expires_at', $from->copy()->addDays((int) $state)->toDateString());
                    })
                    ->helperText('Picking a duration fills in "Expires on" below — you can still edit that directly.'),
                Forms\Components\DatePicker::make('expires_at')->label('Expires on')
                    ->timezone('Asia/Kolkata')
                    ->helperText('Blank = never expires on its own.'),
                Forms\Components\Toggle::make('is_active')->default(true),
                Forms\Components\TextInput::make('sort_order')->numeric()->integer()->default(0)
                    ->helperText('Lower numbers show first.'),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('image')->disk('web')->label(''),
                Tables\Columns\TextColumn::make('title')->searchable()->weight('bold'),
                Tables\Columns\TextColumn::make('starts_at')->date()->label('Starts')
                    ->placeholder('Immediately')->sortable(),
                Tables\Columns\TextColumn::make('expires_at')->date()->label('Expires')
                    ->placeholder('Never')->sortable(),
                Tables\Columns\TextColumn::make('sort_order')->label('Order')->sortable()->toggleable(),
                Tables\Columns\ToggleColumn::make('is_active'),
            ])
            ->defaultSort('sort_order')
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
            'index' => Pages\ListOffers::route('/'),
            'create' => Pages\CreateOffer::route('/create'),
            'edit' => Pages\EditOffer::route('/{record}/edit'),
        ];
    }
}
