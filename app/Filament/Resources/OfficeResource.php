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

class OfficeResource extends Resource
{
    protected static ?string $model = Office::class;
    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';
    protected static ?string $navigationGroup = 'Master Data';

    public static function getModelLabel(): string
    {
        return 'Lokasi Kantor';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Lokasi Kantor';
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                // SECTION UTAMA: Kiri (lg => 2)
                Forms\Components\Group::make()
                    ->schema([
                        Forms\Components\Section::make('Informasi Kantor')
                            ->icon('heroicon-m-information-circle')
                            ->schema([
                                Forms\Components\TextInput::make('name')
                                    ->label('Nama Kantor')
                                    ->required()
                                    ->placeholder('Contoh: Kantor Pusat')
                                    ->maxLength(255),

                                Forms\Components\TextInput::make('supervisor_name')
                                    ->label('Nama Supervisor / PIC')
                                    ->required()
                                    ->prefixIcon('heroicon-m-user-circle'),

                                Forms\Components\TextInput::make('radius')
                                    ->label('Radius Absensi')
                                    ->numeric()
                                    ->suffix('Meter')
                                    ->default(100)
                                    ->required()
                                    ->helperText('Jarak maksimal karyawan bisa melakukan absen.')
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
                                            ->live(onBlur: true)
                                            ->afterStateUpdated(fn($state, Forms\Set $set, Forms\Get $get) =>
                                            $set('location', ['lat' => $state, 'lng' => $get('longitude')])),

                                        Forms\Components\TextInput::make('longitude')
                                            ->numeric()
                                            ->required()
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
                                            // Default Koordinat Kalimantan (Area Kalsel/Tengah)
                                            'lat' => $record?->latitude ?? -3.316694,
                                            'lng' => $record?->longitude ?? 114.590111
                                        ]);
                                    })
                                    ->afterStateUpdated(function (Forms\Set $set, $state) {
                                        $set('latitude', $state['lat'] ?? null);
                                        $set('longitude', $state['lng'] ?? null);
                                    })
                                    // Menghapus baris tilesUrl agar kembali ke tampilan peta standar OSM yang lebih jelas
                                    ->dehydrated(false),
                            ]),
                    ])->columnSpan(['lg' => 2]),

                // SECTION METADATA: Kanan (lg => 1)
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
            ->actions([
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
