<?php

namespace App\Filament\Resources;

use App\Filament\Resources\LaporanAbsensiResource\Pages;
use App\Models\Attendance;
use App\Models\Office;
use App\Models\Position;
use App\Models\User;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Carbon\CarbonPeriod;

class LaporanAbsensiResource extends Resource
{
    protected static ?string $model = Attendance::class;
    protected static ?string $navigationGroup = 'Laporan & Analitik';
    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';
    protected static ?string $navigationLabel = 'Rekap Absensi';
    protected static ?string $pluralModelLabel = 'Laporan Rekap Absensi';
    protected static ?int $navigationSort = 1;

    public static function getPermissionPrefixes(): array
    {
        return ['view', 'view_any'];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canAccess(): bool
    {
        return auth()->user()->hasRole(['super_admin', 'admin']);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn(Builder $query) => $query->latest()->with(['user', 'user.position', 'user.schedules.office']))
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Tanggal')
                    ->date('d M Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Karyawan')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('user.position.name')
                    ->label('Jabatan')
                    ->badge()
                    ->color('gray'),
                Tables\Columns\TextColumn::make('start_time')
                    ->label('Masuk')
                    ->time('H:i')
                    ->color('success'),
                Tables\Columns\TextColumn::make('end_time')
                    ->label('Pulang')
                    ->time('H:i')
                    ->color('danger')
                    ->placeholder('--:--'),
                Tables\Columns\IconColumn::make('is_late')
                    ->label('Terlambat')
                    ->boolean()
                    ->getStateUsing(fn($record) => $record->isLate())
                    ->trueColor('danger')
                    ->falseColor('success'),
                Tables\Columns\TextColumn::make('schedule.office.name')
                    ->label('Area')
                    ->badge()
                    ->color('info')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Filter::make('periode')
                    ->form([
                        DatePicker::make('dari')
                            ->label('Mulai Tanggal')
                            ->default(now()->startOfMonth()),
                        DatePicker::make('sampai')
                            ->label('Sampai Tanggal')
                            ->default(now()->endOfMonth()),
                    ])
                    ->query(
                        fn(Builder $query, array $data) =>
                        $query->when($data['dari'], fn($q, $date) => $q->whereDate('created_at', '>=', $date))
                            ->when($data['sampai'], fn($q, $date) => $q->whereDate('created_at', '<=', $date))
                    )
                    ->columns(2),

                SelectFilter::make('office_id')
                    ->label('Pilih Area/Depo')
                    ->multiple()
                    ->options(fn() => Office::pluck('name', 'id'))
                    ->query(
                        fn(Builder $query, array $data) =>
                        $query->when(
                            $data['values'],
                            fn($q, $ids) =>
                            $q->whereHas('user.schedules', fn($sq) => $sq->whereIn('office_id', $ids))
                        )
                    ),

                SelectFilter::make('position_id')
                    ->label('Jabatan')
                    ->multiple()
                    ->options(fn() => Position::pluck('name', 'id'))
                    ->query(
                        fn(Builder $query, array $data) =>
                        $query->when(
                            $data['values'],
                            fn($q, $ids) =>
                            $q->whereHas('user.position', fn($sq) => $sq->whereIn('id', $ids))
                        )
                    ),

                SelectFilter::make('status')
                    ->label('Status Kehadiran')
                    ->options([
                        'hadir' => 'Hadir',
                        'terlambat' => 'Terlambat',
                        'tidak_hadir' => 'Tidak Hadir',
                    ])
                    ->query(
                        fn(Builder $query, array $data) =>
                        $query->when($data['value'] === 'hadir', fn($q) => $q->whereNotNull('start_time'))
                            ->when($data['value'] === 'terlambat', fn($q) => $q->whereRaw('TIME(start_time) > TIME(schedule_start_time)'))
                            ->when($data['value'] === 'tidak_hadir', fn($q) => $q->whereNull('start_time'))
                    ),
            ])
            ->headerActions([
                Tables\Actions\Action::make('export_pdf')
                    ->label('Export PDF')
                    ->color('danger')
                    ->icon('heroicon-o-document-arrow-down')
                    ->requiresConfirmation()
                    ->modalHeading('Export PDF Rekap Absensi')
                    ->modalDescription('Data akan diexport berdasarkan filter yang dipilih.')
                    ->action(function ($livewire) {
                        // Ambil Parameter Tanggal
                        $startDate = $livewire->tableFilters['periode']['dari'] ?? now()->startOfMonth()->format('Y-m-d');
                        $endDate = $livewire->tableFilters['periode']['sampai'] ?? now()->endOfMonth()->format('Y-m-d');

                        // Ambil Nama Area Terpilih
                        $officeIds = $livewire->tableFilters['office_id']['values'] ?? [];
                        $officeNames = Office::whereIn('id', $officeIds)->pluck('name')->toArray();
                        $positionIds = $livewire->tableFilters['position_id']['values'] ?? [];
                        $statusFilter = $livewire->tableFilters['status']['value'] ?? null;

                        // Nama file dinamis
                        $areaString = count($officeNames) > 0
                            ? implode('_', array_slice($officeNames, 0, 3)) . (count($officeNames) > 3 ? '_dst' : '')
                            : 'Semua_Area';

                        $formattedStart = Carbon::parse($startDate)->format('dM');
                        $formattedEnd = Carbon::parse($endDate)->format('dM_Y');
                        $dateRange = "{$formattedStart}_sd_{$formattedEnd}";

                        // Query Data User
                        $users = User::with([
                            'attendances' => fn($q) => $q->whereBetween('created_at', [
                                Carbon::parse($startDate)->startOfDay(),
                                Carbon::parse($endDate)->endOfDay()
                            ])->with('permission'),
                            'position',
                            'schedules.office'
                        ])
                            ->whereHas('roles', fn($q) => $q->where('name', 'karyawan'))
                            ->when($officeIds, fn($q, $ids) => $q->whereHas('schedules', fn($sq) => $sq->whereIn('office_id', $ids)))
                            ->when($positionIds, fn($q, $ids) => $q->whereIn('position_id', $ids))
                            ->when($statusFilter === 'hadir', fn($q) => $q->whereHas('attendances', fn($sq) => $sq->whereNotNull('start_time')))
                            ->when($statusFilter === 'terlambat', fn($q) => $q->whereHas('attendances', fn($sq) => $sq->whereRaw('TIME(start_time) > TIME(schedule_start_time)')))
                            ->orderBy('name')
                            ->get()
                            ->unique('id');

                        $dates = CarbonPeriod::create($startDate, $endDate)->toArray();

                        // Generate PDF
                        $pdf = Pdf::loadView('exports.pdf.absensi_rekap', [
                            'users' => $users,
                            'dates' => $dates,
                            'title' => 'REKAPITULASI ABSENSI KARYAWAN',
                            'startDate' => $startDate,
                            'endDate' => $endDate,
                        ])->setPaper('legal', 'landscape');

                        $fileName = "Rekap_Absensi_{$areaString}_{$dateRange}.pdf";

                        return response()->streamDownload(fn() => print($pdf->output()), $fileName);
                    }),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageLaporanAbsensis::route('/'),
        ];
    }
}
