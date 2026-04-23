<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OfficeResource\Pages;
use App\Models\Office;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Humaidem\FilamentMapPicker\Fields\OSMMap;
use Filament\Support\Enums\FontWeight;
use Illuminate\Database\Eloquent\Builder;

class OfficeResource extends Resource
{
    protected static ?string $model = Office::class;
    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';
    protected static ?string $navigationGroup = 'Master Data';
    protected static ?int $navigationSort = 3;

    public static function getModelLabel(): string
    {
        return 'Lokasi Kantor';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Lokasi Kantor';
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::count();
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'info';
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Group::make()
                    ->schema([
                        Forms\Components\Section::make('Informasi Kantor')
                            ->icon('heroicon-m-information-circle')
                            ->schema([
                                Forms\Components\TextInput::make('name')
                                    ->label('Nama Kantor')
                                    ->required()
                                    ->placeholder('Contoh: Kantor Pusat')
                                    ->maxLength(255)
                                    ->unique(ignoreRecord: true),

                                Forms\Components\TextInput::make('supervisor_name')
                                    ->label('Nama Supervisor / PIC')
                                    ->required()
                                    ->prefixIcon('heroicon-m-user-circle'),

                                Forms\Components\TextInput::make('radius')
                                    ->label('Radius Absensi')
                                    ->numeric()
                                    ->minValue(10)
                                    ->maxValue(1000)
                                    ->suffix('Meter')
                                    ->default(100)
                                    ->required()
                                    ->helperText('Jarak maksimal karyawan bisa melakukan absen (10-1000 meter).')
                                    ->prefixIcon('heroicon-m-arrows-pointing-out'),
                            ])->columns(2),

                        Forms\Components\Section::make('Titik Koordinat')
                            ->icon('heroicon-m-map-pin')
                            ->schema([
                                Forms\Components\Grid::make(2)
                                    ->schema([
                                        Forms\Components\TextInput::make('latitude')
                                            ->numeric()
                                            ->required()
                                            ->minValue(-90)
                                            ->maxValue(90)
                                            ->step(0.000001)
                                            ->live(onBlur: true)
                                            ->afterStateUpdated(fn($state, Forms\Set $set, Forms\Get $get) =>
                                            $set('location', ['lat' => $state, 'lng' => $get('longitude')])),

                                        Forms\Components\TextInput::make('longitude')
                                            ->numeric()
                                            ->required()
                                            ->minValue(-180)
                                            ->maxValue(180)
                                            ->step(0.000001)
                                            ->live(onBlur: true)
                                            ->afterStateUpdated(fn($state, Forms\Set $set, Forms\Get $get) =>
                                            $set('location', ['lat' => $get('latitude'), 'lng' => $state])),
                                    ]),

                                OSMMap::make('location')
                                    ->label('Pilih Titik Lokasi Kantor')
                                    ->showMarker()
                                    ->draggable()
                                    ->reactive()
                                    ->extraAttributes([
                                        'style' => 'height: 400px; border-radius: 12px; border: 1px solid #d1d5db;',
                                    ])
                                    ->afterStateHydrated(function (Forms\Set $set, ?Office $record) {
                                        $set('location', [
                                            'lat' => $record?->latitude ?? -3.316694,
                                            'lng' => $record?->longitude ?? 114.590111
                                        ]);
                                    })
                                    ->afterStateUpdated(function (Forms\Set $set, $state) {
                                        $set('latitude', $state['lat'] ?? null);
                                        $set('longitude', $state['lng'] ?? null);
                                    }),
                            ]),
                    ])->columnSpan(['lg' => 2]),

                Forms\Components\Section::make('Metadata')
                    ->schema([
                        Forms\Components\Placeholder::make('created_at')
                            ->label('Daftar Sejak')
                            ->content(fn(?Office $record): string => $record ? $record->created_at->diffForHumans() : '-'),

                        Forms\Components\Placeholder::make('updated_at')
                            ->label('Terakhir Diperbarui')
                            ->content(fn(?Office $record): string => $record ? $record->updated_at->diffForHumans() : '-'),
                    ])
                    ->columnSpan(['lg' => 1])
                    ->hidden(fn(?Office $record) => $record === null),
            ])->columns(3);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name', 'asc')
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nama Kantor')
                    ->weight(FontWeight::Bold)
                    ->searchable()
                    ->description(fn(Office $record) => "PIC: {$record->supervisor_name}"),

                Tables\Columns\TextColumn::make('radius')
                    ->label('Radius')
                    ->badge()
                    ->color('info')
                    ->suffix(' meter')
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('latitude')
                    ->label('Lokasi (Lat, Long)')
                    ->state(fn(Office $record): string => "{$record->latitude}, {$record->longitude}")
                    ->icon('heroicon-m-map-pin')
                    ->color('gray')
                    ->size(Tables\Columns\TextColumn\TextColumnSize::ExtraSmall)
                    ->copyable()
                    ->copyMessage('Koordinat disalin'),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Update')
                    ->since()
                    ->color('gray'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('radius')
                    ->label('Radius Absensi')
                    ->options([
                        '50' => '≤ 50 meter',
                        '100' => '≤ 100 meter',
                        '200' => '≤ 200 meter',
                        '500' => '≤ 500 meter',
                    ]),
            ])
            ->actions([
                Tables\Actions\Action::make('view_on_maps')
                    ->label('Lihat Maps')
                    ->icon('heroicon-m-map')
                    ->color('success')
                    ->url(fn(Office $record) => "https://www.google.com/maps/search/?api=1&query={$record->latitude},{$record->longitude}")
                    ->openUrlInNewTab(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOffices::route('/'),
            'create' => Pages\CreateOffice::route('/create'),
            'edit' => Pages\EditOffice::route('/{record}/edit'),
        ];
    }
}
    