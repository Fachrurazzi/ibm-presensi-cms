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

    /**
     * Mencegah duplikasi di Shield. 
     * Resource ini hanya untuk melihat laporan, tidak perlu CRUD penuh.
     */
    public static function getPermissionPrefixes(): array
    {
        return [];
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
            ->modifyQueryUsing(fn(Builder $query) => $query->latest())
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

                Tables\Columns\TextColumn::make('start_time')
                    ->label('Datang')
                    ->time('H:i')
                    ->color('success'),

                Tables\Columns\TextColumn::make('end_time')
                    ->label('Pulang')
                    ->time('H:i')
                    ->placeholder('Belum Pulang'),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->getStateUsing(fn($record) => $record->isLate() ? 'Terlambat' : 'Tepat Waktu')
                    ->color(fn($record) => $record->isLate() ? 'danger' : 'success'),
            ])
            ->filters([
                Filter::make('tanggal')
                    ->form([
                        DatePicker::make('dari')->label('Dari Tanggal'),
                        DatePicker::make('sampai')->label('Sampai Tanggal'),
                    ])
                    ->query(
                        fn(Builder $query, array $data) => $query
                            ->when($data['dari'], fn($q, $date) => $q->whereDate('created_at', '>=', $date))
                            ->when($data['sampai'], fn($q, $date) => $q->whereDate('created_at', '<=', $date))
                    ),

                SelectFilter::make('office_id')
                    ->label('Cabang')
                    ->options(fn() => Office::pluck('name', 'id'))
                    ->query(
                        fn(Builder $query, array $data) => $query
                            ->when($data['value'], fn($q, $id) => $q->whereHas('user.schedules', fn($sq) => $sq->where('office_id', $id)))
                    ),

                SelectFilter::make('position_id')
                    ->label('Jabatan')
                    ->options(fn() => Position::pluck('name', 'id'))
                    ->query(
                        fn(Builder $query, array $data) => $query
                            ->when($data['value'], fn($q, $id) => $q->whereHas('user', fn($uq) => $uq->where('position_id', $id)))
                    ),
            ])
            ->headerActions([
                Tables\Actions\Action::make('export_pdf')
                    ->label('Download PDF')
                    ->color('danger')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->action(function ($livewire) {
                        // Mengambil data yang sudah terfilter di table
                        $data = $livewire->getFilteredTableQuery()->get();

                        $pdf = Pdf::loadView('exports.pdf.indisipliner', [
                            'data' => $data,
                            'title' => 'LAPORAN INDISIPLINER KARYAWAN'
                        ])->setPaper('a4', 'portrait');

                        return response()->streamDownload(
                            fn() => print($pdf->output()),
                            'Laporan_Kedisiplinan_' . now()->format('d-m-Y') . '.pdf'
                        );
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageLaporanIndisipliners::route('/')];
    }
}
