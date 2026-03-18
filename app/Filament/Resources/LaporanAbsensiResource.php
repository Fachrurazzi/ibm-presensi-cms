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

    // Mengatur urutan agar rapi di sidebar
    protected static ?int $navigationSort = 1;

    /**
     * Mencegah duplikasi Roles/Permissions di Filament Shield.
     * Kita hanya butuh izin 'view' dan 'view_any' untuk rekap laporan.
     */
    public static function getPermissionPrefixes(): array
    {
        return [];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    // Mengatur akses berdasarkan Role
    public static function canAccess(): bool
    {
        return auth()->user()->hasRole(['super_admin', 'admin']);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn(Builder $query) => $query->latest())
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
            ])
            ->filters([
                // Filter Periode Tanggal
                Filter::make('periode')
                    ->form([
                        DatePicker::make('dari')->label('Mulai Tanggal'),
                        DatePicker::make('sampai')->label('Sampai Tanggal'),
                    ])
                    ->query(
                        fn(Builder $query, array $data) => $query
                            ->when($data['dari'], fn($q, $date) => $q->whereDate('created_at', '>=', $date))
                            ->when($data['sampai'], fn($q, $date) => $q->whereDate('created_at', '<=', $date))
                    ),
                // Filter Multiple Cabang/Area
                SelectFilter::make('office_id')
                    ->label('Pilih Area/Depo')
                    ->multiple()
                    ->options(fn() => Office::pluck('name', 'id'))
                    ->query(
                        fn(Builder $query, array $data) => $query
                            ->when($data['values'], function ($q, $ids) {
                                return $q->whereHas('user.schedules', fn($sq) => $sq->whereIn('office_id', $ids));
                            })
                    ),
            ])
            ->headerActions([
                Tables\Actions\Action::make('export_pdf')
                    ->label('Export PDF Area Terpilih')
                    ->color('danger')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->action(function ($livewire) {
                        // 1. Ambil Parameter Tanggal dari Filter
                        $startDate = $livewire->tableFilters['periode']['dari'] ?? now()->startOfMonth()->format('Y-m-d');
                        $endDate = $livewire->tableFilters['periode']['sampai'] ?? now()->endOfMonth()->format('Y-m-d');

                        // 2. Ambil Nama Area Terpilih untuk Nama File
                        $officeIds = $livewire->tableFilters['office_id']['values'] ?? [];
                        $officeNames = Office::whereIn('id', $officeIds)->pluck('name')->toArray();

                        // Nama file dinamis: maksimal 3 area untuk menjaga panjang karakter
                        $areaString = count($officeNames) > 0
                            ? implode('_', array_slice($officeNames, 0, 3)) . (count($officeNames) > 3 ? '_dst' : '')
                            : 'Semua_Area';

                        // 3. Format Tanggal untuk Nama File
                        $formattedStart = Carbon::parse($startDate)->format('dM');
                        $formattedEnd = Carbon::parse($endDate)->format('dM_Y');
                        $dateRange = "{$formattedStart}_sd_{$formattedEnd}";

                        // 4. Query Data User (Unique) & Eager Load Attendance
                        $users = \App\Models\User::with([
                            'attendances' => fn($q) => $q->whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59']),
                            'position',
                            'schedules.office'
                        ])
                            ->whereHas('roles', fn($q) => $q->where('name', 'karyawan'))
                            ->when($officeIds, function ($query, $ids) {
                                return $query->whereHas('schedules', fn($q) => $q->whereIn('office_id', $ids));
                            })
                            ->get()
                            ->unique('id'); // Pastikan tidak ada user double di PDF

                        $dates = iterator_to_array(CarbonPeriod::create($startDate, $endDate));

                        // 5. Generate PDF dengan kertas Legal Landscape
                        $pdf = Pdf::loadView('exports.pdf.absensi_rekap', [
                            'users' => $users,
                            'dates' => $dates,
                            'title' => 'REKAPITULASI ABSENSI KARYAWAN'
                        ])->setPaper('legal', 'landscape');

                        // 6. Download dengan Nama File Dinamis
                        $fileName = "Rekap_{$areaString}_{$dateRange}.pdf";

                        return response()->streamDownload(fn() => print($pdf->output()), $fileName);
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageLaporanAbsensis::route('/')];
    }
}
