<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AttendanceResource\Pages;
use App\Models\Attendance;
use App\Models\Office;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;

class AttendanceResource extends Resource
{
    protected static ?string $model = Attendance::class;
    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';
    protected static ?string $navigationGroup = 'Manajemen Absensi';
    protected static ?int $navigationSort = 1;

    public static function getModelLabel(): string
    {
        return 'Data Presensi';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Data Presensi';
    }

    public static function getNavigationBadge(): ?string
    {
        $query = static::getModel()::query();

        if (!auth()->user()->hasRole(['super_admin', 'admin'])) {
            $query->where('user_id', auth()->id());
        }

        $count = $query->count();
        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'info';
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['user.name', 'user.email'];
    }

    public static function form(Form $form): Form
    {
        $isCreating = request()->routeIs('filament.admin.resources.attendances.create');
        $isEditing = request()->routeIs('filament.admin.resources.attendances.edit');

        return $form
            ->schema([
                Forms\Components\Grid::make(3)
                    ->schema([
                        // BAGIAN KIRI (Detail Lokasi & Karyawan)
                        Forms\Components\Group::make()
                            ->schema([
                                Forms\Components\Section::make('Informasi Karyawan & Jadwal')
                                    ->icon('heroicon-m-user-circle')
                                    ->schema([
                                        Forms\Components\Select::make('user_id')
                                            ->relationship('user', 'name')
                                            ->label('Nama Karyawan')
                                            ->disabled(!$isCreating)
                                            ->required($isCreating)
                                            ->searchable()
                                            ->preload()
                                            ->columnSpanFull(),

                                        Forms\Components\Select::make('schedule_id')
                                            ->label('Jadwal Kerja')
                                            ->relationship('schedule', 'id', function ($query) {
                                                $query->with(['user', 'shift', 'office']);
                                            })
                                            ->getOptionLabelFromRecordUsing(function ($record) {
                                                return "{$record->user->name} - {$record->shift->name} ({$record->date->format('d M Y')}) - {$record->office->name}";
                                            })
                                            ->disabled(!$isCreating)
                                            ->required($isCreating)
                                            ->searchable()
                                            ->preload()
                                            ->visible($isCreating)
                                            ->live()
                                            ->afterStateUpdated(function ($state, Forms\Set $set, $get) {
                                                if ($state) {
                                                    $schedule = \App\Models\Schedule::with(['shift', 'office'])->find($state);
                                                    if ($schedule) {
                                                        $set('schedule_start_time', $schedule->shift->start_time);
                                                        $set('schedule_end_time', $schedule->shift->end_time);
                                                        $set('schedule_latitude', $schedule->office->latitude);
                                                        $set('schedule_longitude', $schedule->office->longitude);
                                                    }
                                                }
                                            }),

                                        Forms\Components\Grid::make(2)->schema([
                                            Forms\Components\TextInput::make('schedule_start_time')
                                                ->label('Jadwal Masuk')
                                                ->readonly(),
                                            Forms\Components\TextInput::make('schedule_end_time')
                                                ->label('Jadwal Keluar')
                                                ->readonly(),
                                        ])->visible(!$isCreating),

                                        Forms\Components\Hidden::make('schedule_latitude'),
                                        Forms\Components\Hidden::make('schedule_longitude'),
                                    ]),

                                Forms\Components\Section::make('Titik Koordinat Absensi')
                                    ->description('Lokasi GPS saat karyawan melakukan aksi absen.')
                                    ->icon('heroicon-m-map-pin')
                                    ->collapsible()
                                    ->schema([
                                        Forms\Components\Grid::make(2)->schema([
                                            Forms\Components\Fieldset::make('Lokasi Masuk')
                                                ->schema([
                                                    Forms\Components\TextInput::make('start_latitude')
                                                        ->label('Latitude')
                                                        ->required($isCreating)
                                                        ->numeric()
                                                        ->step(0.000001),
                                                    Forms\Components\TextInput::make('start_longitude')
                                                        ->label('Longitude')
                                                        ->required($isCreating)
                                                        ->numeric()
                                                        ->step(0.000001),
                                                ]),
                                            Forms\Components\Fieldset::make('Lokasi Keluar')
                                                ->schema([
                                                    Forms\Components\TextInput::make('end_latitude')
                                                        ->label('Latitude')
                                                        ->numeric()
                                                        ->step(0.000001),
                                                    Forms\Components\TextInput::make('end_longitude')
                                                        ->label('Longitude')
                                                        ->numeric()
                                                        ->step(0.000001),
                                                ]),
                                        ]),
                                    ]),
                            ])->columnSpan(2),

                        // BAGIAN KANAN (Waktu & Analisa)
                        Forms\Components\Group::make()
                            ->schema([
                                Forms\Components\Section::make('Waktu Aktual')
                                    ->icon('heroicon-m-clock')
                                    ->schema([
                                        Forms\Components\DateTimePicker::make('start_time')
                                            ->label('Jam Masuk')
                                            ->displayFormat('d/m/Y H:i')
                                            ->required($isCreating)
                                            ->seconds(false),

                                        Forms\Components\DateTimePicker::make('end_time')
                                            ->label('Jam Keluar')
                                            ->displayFormat('d/m/Y H:i')
                                            ->seconds(false),

                                        Forms\Components\Placeholder::make('created_at')
                                            ->label('Tanggal Presensi')
                                            ->content(fn($record) => $record?->created_at?->format('d F Y') ?? date('d F Y')),
                                    ]),

                                Forms\Components\Section::make('Izin Terkait')
                                    ->icon('heroicon-m-document-text')
                                    ->schema([
                                        Forms\Components\Select::make('attendance_permission_id')
                                            ->label('Izin')
                                            ->relationship('permission', 'type')
                                            ->getOptionLabelFromRecordUsing(function ($record) {
                                                return "{$record->type_label} - {$record->date->format('d M Y')} ({$record->status_label})";
                                            })
                                            ->searchable()
                                            ->preload()
                                            ->placeholder('Tidak ada izin'),
                                    ]),

                                Forms\Components\Section::make('Analisa Sistem')
                                    ->schema([
                                        Forms\Components\Placeholder::make('status_late')
                                            ->label('Status Terlambat')
                                            ->content(fn($record) => $record?->isLate() ? '🔴 Ya, Terlambat' : '🟢 Tepat Waktu'),

                                        Forms\Components\Placeholder::make('work_dur')
                                            ->label('Total Durasi Kerja')
                                            ->content(fn($record) => $record?->workDuration() ?? '-'),

                                        Forms\Components\Placeholder::make('lunch_money')
                                            ->label('Uang Makan')
                                            ->content(fn($record) => $record?->lunch_money_label ?? '-'),
                                    ]),
                            ])->columnSpan(1),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function (Builder $query) {
                $query->with(['user.position', 'schedule.office', 'permission']);

                if (!auth()->user()->hasRole(['super_admin', 'admin'])) {
                    return $query->where('user_id', auth()->id());
                }
            })
            ->defaultSort('created_at', 'desc')
            ->persistFiltersInSession(false)
            ->columns([
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Karyawan')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->description(fn($record) => ($record->user->position?->name ?? 'Staff') . " | " . ($record->schedule->office->name ?? 'Mobile')),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Tanggal')
                    ->date('d M Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('absensi_aktual')
                    ->label('Masuk / Keluar')
                    ->getStateUsing(fn($record) => ($record->start_time?->format('H:i') ?? '--:--') . ' / ' . ($record->end_time?->format('H:i') ?? '--:--'))
                    ->description(fn($record) => "Jadwal: " . ($record->schedule_start_time?->format('H:i') ?? '--:--') . " - " . ($record->schedule_end_time?->format('H:i') ?? '--:--'))
                    ->badge()
                    ->color('info')
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('is_late')
                    ->label('Kedisiplinan')
                    ->badge()
                    ->getStateUsing(fn($record) => $record->isLate() ? 'Terlambat' : 'Tepat Waktu')
                    ->color(fn($record) => $record->isLate() ? 'danger' : 'success')
                    ->icon(fn($record) => $record->isLate() ? 'heroicon-m-exclamation-triangle' : 'heroicon-m-check-badge'),

                Tables\Columns\TextColumn::make('lunch_money')
                    ->label('Uang Makan')
                    ->getStateUsing(fn($record) => $record->lunch_money_label)
                    ->badge()
                    ->color(fn($record) => str_contains($record->lunch_money_label, '15.000') ? 'success' : 'danger')
                    ->icon('heroicon-m-currency-dollar'),

                Tables\Columns\TextColumn::make('work_duration')
                    ->label('Durasi Kerja')
                    ->getStateUsing(fn($record) => $record->workDuration())
                    ->icon('heroicon-m-clock')
                    ->color('gray'),
            ])
            ->filters([
                SelectFilter::make('position_id')
                    ->label('Jabatan')
                    ->relationship('user.position', 'name'),

                SelectFilter::make('office_id')
                    ->label('Cabang Kantor')
                    ->options(fn() => Office::pluck('name', 'id'))
                    ->query(
                        fn(Builder $query, array $data) =>
                        $query->when(
                            $data['value'],
                            fn($q) =>
                            $q->whereHas('schedule', fn($sq) => $sq->where('office_id', $data['value']))
                        )
                    ),

                Tables\Filters\TernaryFilter::make('is_late_filter')
                    ->label('Status Terlambat')
                    ->placeholder('Semua')
                    ->trueLabel('Terlambat')
                    ->falseLabel('Tepat Waktu')
                    ->queries(
                        true: fn(Builder $query) => $query->whereRaw('TIME(start_time) > TIME(schedule_start_time)'),
                        false: fn(Builder $query) => $query->whereRaw('TIME(start_time) <= TIME(schedule_start_time)'),
                        blank: fn(Builder $query) => $query, // Tidak difilter
                    ),

                Filter::make('created_at')
                    ->label('Rentang Tanggal')
                    ->form([
                        Forms\Components\DatePicker::make('from')
                            ->label('Dari Tanggal')
                            ->displayFormat('d/m/Y'),
                        Forms\Components\DatePicker::make('until')
                            ->label('Sampai Tanggal')
                            ->displayFormat('d/m/Y'),
                    ])
                    ->query(
                        fn(Builder $query, array $data) =>
                        $query->when($data['from'], fn($q, $date) => $q->whereDate('created_at', '>=', $date))
                            ->when($data['until'], fn($q, $date) => $q->whereDate('created_at', '<=', $date))
                    ),
            ])
            ->actions([
                Tables\Actions\Action::make('view_location')
                    ->label('Lihat Lokasi')
                    ->icon('heroicon-m-map')
                    ->color('info')
                    ->url(
                        fn(Attendance $record) =>
                        "https://www.google.com/maps/search/?api=1&query={$record->start_latitude},{$record->start_longitude}"
                    )
                    ->openUrlInNewTab()
                    ->visible(fn(Attendance $record) => $record->start_latitude && $record->start_longitude),

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
            'index' => Pages\ListAttendances::route('/'),
            'create' => Pages\CreateAttendance::route('/create'),
            'edit' => Pages\EditAttendance::route('/{record}/edit'),
        ];
    }
}
