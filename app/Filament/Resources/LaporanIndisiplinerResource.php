<?php

namespace App\Filament\Resources;

use App\Filament\Resources\LaporanIndisiplinerResource\Pages;
use App\Models\Attendance;
use App\Models\Office;
use App\Models\Position;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;
use Barryvdh\DomPDF\Facade\Pdf;

class LaporanIndisiplinerResource extends Resource
{
    protected static ?string $model = Attendance::class;
    protected static ?string $navigationGroup = 'Laporan & Analitik';
    protected static ?string $navigationIcon = 'heroicon-o-exclamation-triangle';
    protected static ?string $navigationLabel = 'Laporan Kedisiplinan';
    protected static ?string $pluralModelLabel = 'Laporan Kedisiplinan (Absen)';
    protected static ?int $navigationSort = 2;

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
            ->modifyQueryUsing(fn(Builder $query) => 
                $query->latest()
                    ->with(['user', 'user.position', 'user.schedules.office'])
                    ->whereNotNull('start_time')
                    ->whereNotNull('schedule_start_time')
                    ->whereRaw('TIME(start_time) > TIME(schedule_start_time)')
            )
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Tanggal')
                    ->date('d M Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('user.name')
                    ->label('Nama Karyawan')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('user.position.name')
                    ->label('Jabatan')
                    ->badge()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('user.schedules.office.name')
                    ->label('Area')
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('schedule_start_time')
                    ->label('Jadwal Masuk')
                    ->time('H:i')
                    ->color('gray'),

                Tables\Columns\TextColumn::make('start_time')
                    ->label('Jam Datang')
                    ->time('H:i')
                    ->color('danger')
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('late_duration')
                    ->label('Durasi Terlambat')
                    ->getStateUsing(fn($record) => 
                        $record->start_time->diffInMinutes($record->schedule_start_time) . ' menit'
                    )
                    ->badge()
                    ->color('danger')
                    ->icon('heroicon-m-clock'),

                Tables\Columns\TextColumn::make('end_time')
                    ->label('Jam Pulang')
                    ->time('H:i')
                    ->placeholder('Belum Pulang')
                    ->color('success'),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Filter::make('tanggal')
                    ->form([
                        DatePicker::make('dari')
                            ->label('Dari Tanggal')
                            ->default(now()->startOfMonth()),
                        DatePicker::make('sampai')
                            ->label('Sampai Tanggal')
                            ->default(now()->endOfMonth()),
                    ])
                    ->columns(2)
                    ->query(fn(Builder $query, array $data) => 
                        $query->when($data['dari'], fn($q, $date) => $q->whereDate('created_at', '>=', $date))
                            ->when($data['sampai'], fn($q, $date) => $q->whereDate('created_at', '<=', $date))
                    ),

                SelectFilter::make('office_id')
                    ->label('Cabang')
                    ->options(fn() => Office::pluck('name', 'id'))
                    ->query(fn(Builder $query, array $data) => 
                        $query->when($data['value'], fn($q, $id) => 
                            $q->whereHas('user.schedules', fn($sq) => $sq->where('office_id', $id))
                        )
                    ),

                SelectFilter::make('position_id')
                    ->label('Jabatan')
                    ->options(fn() => Position::pluck('name', 'id'))
                    ->query(fn(Builder $query, array $data) => 
                        $query->when($data['value'], fn($q, $id) => 
                            $q->whereHas('user', fn($uq) => $uq->where('position_id', $id))
                        )
                    ),

                SelectFilter::make('late_duration_filter')
                    ->label('Durasi Keterlambatan')
                    ->options([
                        '1-15' => '1-15 menit',
                        '16-30' => '16-30 menit',
                        '31-60' => '31-60 menit',
                        '60+' => '> 60 menit',
                    ])
                    ->query(fn(Builder $query, array $data) => 
                        $query->when($data['value'] === '1-15', fn($q) => 
                            $q->whereRaw("TIME_TO_SEC(TIMEDIFF(start_time, schedule_start_time)) BETWEEN 60 AND 900"))
                            ->when($data['value'] === '16-30', fn($q) => 
                                $q->whereRaw("TIME_TO_SEC(TIMEDIFF(start_time, schedule_start_time)) BETWEEN 901 AND 1800"))
                            ->when($data['value'] === '31-60', fn($q) => 
                                $q->whereRaw("TIME_TO_SEC(TIMEDIFF(start_time, schedule_start_time)) BETWEEN 1801 AND 3600"))
                            ->when($data['value'] === '60+', fn($q) => 
                                $q->whereRaw("TIME_TO_SEC(TIMEDIFF(start_time, schedule_start_time)) > 3600"))
                    ),
            ])
            ->headerActions([
                Tables\Actions\Action::make('export_pdf')
                    ->label('Download PDF')
                    ->color('danger')
                    ->icon('heroicon-o-document-arrow-down')
                    ->requiresConfirmation()
                    ->modalHeading('Export PDF Laporan Kedisiplinan')
                    ->action(function ($livewire) {
                        $data = $livewire->getFilteredTableQuery()->get();

                        $pdf = Pdf::loadView('exports.pdf.indisipliner', [
                            'data' => $data,
                            'title' => 'LAPORAN KEDISIPLINAN KARYAWAN'
                        ])->setPaper('a4', 'portrait');

                        $fileName = 'Laporan_Kedisiplinan_' . now()->format('d-m-Y_H-i-s') . '.pdf';

                        return response()->streamDownload(
                            fn() => print($pdf->output()),
                            $fileName
                        );
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageLaporanIndisipliners::route('/'),
        ];
    }
}