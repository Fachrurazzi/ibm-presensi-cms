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

    public static function form(Form $form): Form
    {
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
                                            ->disabled()
                                            ->columnSpanFull(),

                                        Forms\Components\Grid::make(2)->schema([
                                            Forms\Components\TextInput::make('schedule_start_time')
                                                ->label('Jadwal Masuk')
                                                ->readonly(),
                                            Forms\Components\TextInput::make('schedule_end_time')
                                                ->label('Jadwal Keluar')
                                                ->readonly(),
                                        ]),
                                    ]),

                                Forms\Components\Section::make('Titik Koordinat Absensi')
                                    ->description('Lokasi GPS saat karyawan melakukan aksi absen.')
                                    ->icon('heroicon-m-map-pin')
                                    ->collapsible()
                                    ->schema([
                                        Forms\Components\Grid::make(2)->schema([
                                            Forms\Components\Fieldset::make('Lokasi Masuk')
                                                ->schema([
                                                    Forms\Components\TextInput::make('start_latitude')->label('Lat')->readonly(),
                                                    Forms\Components\TextInput::make('start_longitude')->label('Long')->readonly(),
                                                ])->columnSpan(1),

                                            Forms\Components\Fieldset::make('Lokasi Keluar')
                                                ->schema([
                                                    Forms\Components\TextInput::make('end_latitude')->label('Lat')->readonly(),
                                                    Forms\Components\TextInput::make('end_longitude')->label('Long')->readonly(),
                                                ])->columnSpan(1),
                                        ]),
                                    ]),
                            ])->columnSpan(2),

                        // BAGIAN KANAN (Waktu & Analisa)
                        Forms\Components\Group::make()
                            ->schema([
                                Forms\Components\Section::make('Waktu Aktual')
                                    ->icon('heroicon-m-clock')
                                    ->schema([
                                        Forms\Components\TimePicker::make('start_time')
                                            ->label('Jam Masuk')
                                            ->displayFormat('H:i')
                                            ->required(),
                                        Forms\Components\TimePicker::make('end_time')
                                            ->label('Jam Keluar')
                                            ->displayFormat('H:i'),

                                        Forms\Components\Placeholder::make('created_at')
                                            ->label('Tanggal Presensi')
                                            ->content(fn($record) => $record?->created_at?->format('d F Y') ?? '-'),
                                    ]),

                                Forms\Components\Section::make('Analisa Sistem')
                                    ->schema([
                                        Forms\Components\Placeholder::make('status_late')
                                            ->label('Status Terlambat')
                                            ->content(fn($record) => $record?->isLate() ? '🔴 Ya, Terlambat' : '🟢 Tepat Waktu'),

                                        Forms\Components\Placeholder::make('work_dur')
                                            ->label('Total Durasi Kerja')
                                            ->content(fn($record) => $record?->workDuration() ?? '-'),
                                    ]),
                            ])->columnSpan(1),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function (Builder $query) {
                // Eager loading sangat penting di sini karena relasi yang dalam
                $query->with(['user.position', 'schedule.office']);

                if (!auth()->user()->hasRole(['super_admin', 'admin'])) {
                    return $query->where('user_id', auth()->id());
                }
            })
            ->columns([
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Karyawan')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->description(
                        fn($record) => ($record->user->position?->name ?? 'Staff') . " | " . ($record->schedule->office->name ?? 'Mobile')
                    ),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Tanggal')
                    ->date('d M Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('absensi_aktual')
                    ->label('Masuk / Keluar')
                    ->getStateUsing(
                        fn($record) => ($record->start_time?->format('H:i') ?? '--:--') . ' / ' . ($record->end_time?->format('H:i') ?? '--:--')
                    )
                    ->description(fn($record) => "Jadwal: " . $record->schedule_start_time->format('H:i') . " - " . $record->schedule_end_time->format('H:i'))
                    ->badge()
                    ->color('info')
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('is_late')
                    ->label('Kedisiplinan')
                    ->badge()
                    ->getStateUsing(fn($record) => $record->isLate() ? 'Terlambat' : 'Tepat Waktu')
                    ->color(fn($record) => $record->isLate() ? 'danger' : 'success')
                    ->icon(fn($record) => $record->isLate() ? 'heroicon-m-exclamation-triangle' : 'heroicon-m-check-badge'),

                Tables\Columns\TextColumn::make('work_duration')
                    ->label('Durasi Kerja')
                    ->getStateUsing(fn($record) => $record->workDuration())
                    ->icon('heroicon-m-clock')
                    ->color('gray'),
            ])
            ->defaultSort('created_at', 'desc')
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
                            $q->whereHas('user.schedules', fn($sq) => $sq->where('office_id', $data['value']))
                        )
                    ),

                Filter::make('created_at')
                    ->form([
                        Forms\Components\DatePicker::make('from')->label('Mulai'),
                        Forms\Components\DatePicker::make('until')->label('Sampai'),
                    ])
                    ->query(
                        fn(Builder $query, array $data) =>
                        $query->when($data['from'], fn($q, $date) => $q->whereDate('created_at', '>=', $date))
                            ->when($data['until'], fn($q, $date) => $q->whereDate('created_at', '<=', $date))
                    ),
            ])
            ->headerActions([])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
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
