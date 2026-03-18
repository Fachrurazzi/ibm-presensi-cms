<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ScheduleResource\Pages;
use App\Models\Schedule;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ScheduleResource extends Resource
{
    protected static ?string $model = Schedule::class;
    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';
    protected static ?string $navigationGroup = 'Manajemen Absensi';
    protected static ?int $navigationSort = 3;

    public static function getModelLabel(): string
    {
        return 'Jadwal Kerja';
    }
    public static function getPluralModelLabel(): string
    {
        return 'Jadwal Kerja';
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Group::make()
                    ->schema([
                        Forms\Components\Section::make('Penugasan Karyawan')
                            ->description('Hubungkan karyawan dengan shift dan lokasi kantor.')
                            ->icon('heroicon-m-user-group')
                            ->schema([
                                Forms\Components\Select::make('user_id')
                                    ->label('Karyawan')
                                    ->relationship('user', 'name')
                                    // Multiple hanya aktif saat Create
                                    ->multiple(fn(string $context): bool => $context === 'create')
                                    // Disabled saat Edit agar user tidak bisa diganti (keamanan data)
                                    ->disabled(fn(string $context): bool => $context === 'edit')
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->dehydrated(true)
                                    ->columnSpanFull()
                                    ->helperText(
                                        fn(string $context): string =>
                                        $context === 'create'
                                            ? 'Pilih satu atau lebih karyawan yang belum memiliki jadwal.'
                                            : 'Karyawan tidak dapat diubah. Hapus dan buat baru jika terjadi kesalahan.'
                                    )
                                    // Hanya tampilkan user yang belum punya jadwal saat Create
                                    ->options(function (string $context) {
                                        if ($context === 'create') {
                                            return User::whereDoesntHave('schedule')->pluck('name', 'id');
                                        }
                                        return User::pluck('name', 'id');
                                    }),

                                Forms\Components\Select::make('shift_id')
                                    ->label('Shift Kerja')
                                    ->relationship('shift', 'name')
                                    ->required()
                                    ->preload()
                                    ->native(false),

                                Forms\Components\Select::make('office_id')
                                    ->label('Lokasi Penempatan')
                                    ->relationship('office', 'name')
                                    ->required()
                                    ->preload()
                                    ->native(false),
                            ])->columns(2),

                        Forms\Components\Section::make('Kebijakan Absensi')
                            ->icon('heroicon-m-adjustments-horizontal')
                            ->schema([
                                Forms\Components\Toggle::make('is_wfa')
                                    ->label('Izin WFA')
                                    ->helperText('Bisa absen dari mana saja (radius diabaikan).')
                                    ->onColor('success'),

                                Forms\Components\Toggle::make('is_banned')
                                    ->label('Blokir Absensi')
                                    ->helperText('Karyawan tidak akan bisa menekan tombol absen.')
                                    ->onColor('danger'),
                            ])->columns(2),
                    ])->columnSpan(['lg' => 2]),

                Forms\Components\Section::make('Audit Log')
                    ->schema([
                        Forms\Components\Placeholder::make('created_at')
                            ->label('Dibuat pada')
                            ->content(fn(?Schedule $record): string => $record ? $record->created_at->format('d M Y H:i') : '-'),
                        Forms\Components\Placeholder::make('updated_at')
                            ->label('Update Terakhir')
                            ->content(fn(?Schedule $record): string => $record ? $record->updated_at->diffForHumans() : '-'),
                    ])->columnSpan(['lg' => 1])
                    ->hidden(fn(?Schedule $record) => $record === null),
            ])->columns(3);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function (Builder $query) {
                $query->with(['user', 'shift', 'office']);
                if (!auth()->user()->hasRole(['super_admin', 'admin'])) {
                    $query->where('user_id', auth()->id());
                }
            })
            ->columns([
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Karyawan')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->description(fn(Schedule $record): string => $record->user->email),

                Tables\Columns\TextColumn::make('shift.name')
                    ->label('Shift')
                    ->badge()
                    ->color('info')
                    ->description(fn(Schedule $record): string =>
                    $record->shift->start_time->format('H:i') . ' - ' . $record->shift->end_time->format('H:i')),

                Tables\Columns\TextColumn::make('office.name')
                    ->label('Kantor')
                    ->icon('heroicon-m-map-pin')
                    ->description(fn(Schedule $record): string => $record->is_wfa ? '🔓 Mode WFA' : '📍 Mode On-Site'),

                Tables\Columns\IconColumn::make('is_banned')
                    ->label('Blokir Absensi')
                    ->boolean()
                    ->trueIcon('heroicon-o-x-circle')
                    ->falseIcon('heroicon-o-check-circle')
                    ->trueColor('danger')
                    ->falseColor('success')
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Update')
                    ->since()
                    ->color('gray')
                    ->size('xs'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('office_id')
                    ->label('Kantor')
                    ->relationship('office', 'name'),
                Tables\Filters\TernaryFilter::make('is_wfa')->label('WFA'),
                Tables\Filters\TernaryFilter::make('is_banned')->label('Blokir Absensi'),
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
            'index' => Pages\ListSchedules::route('/'),
            'create' => Pages\CreateSchedule::route('/create'),
            'edit' => Pages\EditSchedule::route('/{record}/edit'),
        ];
    }
}
