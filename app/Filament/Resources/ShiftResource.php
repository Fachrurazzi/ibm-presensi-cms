<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ShiftResource\Pages;
use App\Models\Shift;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ShiftResource extends Resource
{
    protected static ?string $model = Shift::class;
    protected static ?string $navigationIcon = 'heroicon-o-clock';
    protected static ?string $navigationGroup = 'Master Data';

    public static function getModelLabel(): string
    {
        return 'Jadwal Shift';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Jadwal Shift';
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Konfigurasi Shift')
                    ->description('Tentukan nama shift dan jam operasional kerja.')
                    ->icon('heroicon-m-clock')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Nama Shift')
                            ->placeholder('Contoh: Shift Pagi / Shift Malam')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),

                        Forms\Components\TimePicker::make('start_time')
                            ->label('Jam Mulai Kerja')
                            ->required()
                            ->seconds(false)
                            ->displayFormat('H:i')
                            ->suffixIcon('heroicon-m-play-circle'),

                        Forms\Components\TimePicker::make('end_time')
                            ->label('Jam Selesai Kerja')
                            ->required()
                            ->seconds(false)
                            ->displayFormat('H:i')
                            ->suffixIcon('heroicon-m-stop-circle'),
                    ])->columnSpan(['lg' => 2])->columns(2),

                Forms\Components\Section::make('Informasi Tambahan')
                    ->schema([
                        Forms\Components\Placeholder::make('created_at')
                            ->label('Dibuat Pada')
                            ->content(fn(?Shift $record): string => $record ? $record->created_at->diffForHumans() : '-'),

                        Forms\Components\Placeholder::make('updated_at')
                            ->label('Pembaruan Terakhir')
                            ->content(fn(?Shift $record): string => $record ? $record->updated_at->diffForHumans() : '-'),
                    ])
                    ->columnSpan(['lg' => 1])
                    ->hidden(fn(?Shift $record) => $record === null),
            ])->columns(3);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nama Shift')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->color('primary'),

                Tables\Columns\TextColumn::make('start_time')
                    ->label('Mulai')
                    ->dateTime('H:i')
                    ->badge()
                    ->color('info')
                    ->icon('heroicon-m-play'),

                Tables\Columns\TextColumn::make('end_time')
                    ->label('Selesai')
                    ->dateTime('H:i')
                    ->badge()
                    ->color('warning')
                    ->icon('heroicon-m-stop'),

                // Menampilkan jumlah karyawan yang menggunakan shift ini (asumsi ada relasi)
                Tables\Columns\TextColumn::make('schedules_count')
                    ->label('Karyawan')
                    ->counts('schedules')
                    ->badge()
                    ->color('gray')
                    ->alignCenter(),
            ])
            ->defaultSort('start_time', 'asc')
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
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
            'index' => Pages\ListShifts::route('/'),
            'create' => Pages\CreateShift::route('/create'),
            'edit' => Pages\EditShift::route('/{record}/edit'),
        ];
    }
}
