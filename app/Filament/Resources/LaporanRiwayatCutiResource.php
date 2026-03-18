<?php

namespace App\Filament\Resources;

use App\Filament\Resources\LaporanRiwayatCutiResource\Pages;
use App\Models\Leave;
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

class LaporanRiwayatCutiResource extends Resource
{
    protected static ?string $model = Leave::class;
    protected static ?string $navigationGroup = 'Laporan & Analitik';
    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static ?string $navigationLabel = 'Riwayat Cuti & Izin';
    protected static ?string $pluralModelLabel = 'Laporan Riwayat Cuti';
    protected static ?int $navigationSort = 3;

    /**
     * Mencegah duplikasi entitas di Filament Shield.
     * Resource laporan ini hanya butuh akses melihat (view).
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
            ->modifyQueryUsing(fn(Builder $query) => $query->where('status', 'approved')->latest())
            ->columns([
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Nama Karyawan')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('reason')
                    ->label('Keterangan')
                    ->wrap(),

                Tables\Columns\TextColumn::make('start_date')
                    ->label('Mulai')
                    ->date('d M Y')
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('end_date')
                    ->label('Selesai')
                    ->date('d M Y')
                    ->badge()
                    ->color('warning'),

                Tables\Columns\TextColumn::make('note')
                    ->label('Catatan HRD')
                    ->color('gray')
                    ->wrap(),
            ])
            ->filters([
                Filter::make('periode')
                    ->form([
                        DatePicker::make('dari')->label('Mulai Dari'),
                        DatePicker::make('sampai')->label('Sampai Dengan'),
                    ])
                    ->query(
                        fn(Builder $query, array $data) => $query
                            ->when($data['dari'], fn($q, $date) => $q->whereDate('start_date', '>=', $date))
                            ->when($data['sampai'], fn($q, $date) => $q->whereDate('start_date', '<=', $date))
                    ),

                SelectFilter::make('office_id')
                    ->label('Cabang')
                    ->options(fn() => Office::pluck('name', 'id'))
                    ->query(
                        fn(Builder $query, array $data) => $query
                            ->when($data['value'], fn($q, $id) => $q->whereHas('user.schedules', fn($sq) => $sq->where('office_id', $id)))
                    ),
            ])
            ->headerActions([
                Tables\Actions\Action::make('export_pdf_cuti')
                    ->label('Download PDF')
                    ->color('danger')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->action(function ($livewire) {
                        // Ambil data berdasarkan filter yang sedang aktif di tabel
                        $data = $livewire->getFilteredTableQuery()->get();

                        $pdf = Pdf::loadView('exports.pdf.riwayat_cuti', [
                            'data' => $data,
                            'title' => 'LAPORAN RIWAYAT CUTI & IZIN KARYAWAN'
                        ])->setPaper('a4', 'portrait');

                        return response()->streamDownload(
                            fn() => print($pdf->output()),
                            'Laporan_Cuti_' . now()->format('d-m-Y') . '.pdf'
                        );
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageLaporanRiwayatCutis::route('/')];
    }
}
