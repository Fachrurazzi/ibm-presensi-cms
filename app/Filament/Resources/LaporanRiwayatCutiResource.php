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
            ->modifyQueryUsing(
                fn(Builder $query) =>
                $query->latest()->with(['user', 'user.position', 'user.schedules.office'])
            )
            ->columns([
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Nama Karyawan')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('user.position.name')
                    ->label('Jabatan')
                    ->badge()
                    ->color('gray')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('category_label')
                    ->label('Jenis Cuti')
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('reason')
                    ->label('Keterangan')
                    ->wrap()
                    ->limit(50),

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

                Tables\Columns\TextColumn::make('duration')
                    ->label('Durasi')
                    ->getStateUsing(fn($record) => $record->duration . ' hari')
                    ->badge()
                    ->color('gray')
                    ->icon('heroicon-m-calendar'),

                Tables\Columns\TextColumn::make('status_label')
                    ->label('Status')
                    ->badge()
                    ->color(fn($record) => match ($record->status) {
                        'APPROVED' => 'success',
                        'REJECTED' => 'danger',
                        default => 'warning',
                    }),

                Tables\Columns\TextColumn::make('note')
                    ->label('Catatan HRD')
                    ->color('gray')
                    ->wrap()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Filter::make('periode')
                    ->form([
                        DatePicker::make('dari')
                            ->label('Mulai Dari')
                            ->default(now()->startOfYear()),
                        DatePicker::make('sampai')
                            ->label('Sampai Dengan')
                            ->default(now()->endOfYear()),
                    ])
                    ->columns(2)
                    ->query(
                        fn(Builder $query, array $data) =>
                        $query->when($data['dari'], fn($q, $date) => $q->whereDate('start_date', '>=', $date))
                            ->when($data['sampai'], fn($q, $date) => $q->whereDate('start_date', '<=', $date))
                    ),

                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'PENDING' => 'Menunggu',
                        'APPROVED' => 'Disetujui',
                        'REJECTED' => 'Ditolak',
                    ])
                    ->query(
                        fn(Builder $query, array $data) =>
                        $query->when($data['value'], fn($q, $status) => $q->where('status', $status))
                    ),

                SelectFilter::make('category')
                    ->label('Jenis Cuti')
                    ->options([
                        'annual' => 'Cuti Tahunan',
                        'sick' => 'Cuti Sakit',
                        'emergency' => 'Cuti Darurat',
                        'maternity' => 'Cuti Melahirkan',
                        'important' => 'Cuti Penting',
                    ])
                    ->query(
                        fn(Builder $query, array $data) =>
                        $query->when($data['value'], fn($q, $category) => $q->where('category', $category))
                    ),

                SelectFilter::make('office_id')
                    ->label('Cabang')
                    ->options(fn() => Office::pluck('name', 'id'))
                    ->query(
                        fn(Builder $query, array $data) =>
                        $query->when(
                            $data['value'],
                            fn($q, $id) =>
                            $q->whereHas('user.schedules', fn($sq) => $sq->where('office_id', $id))
                        )
                    ),

                SelectFilter::make('position_id')
                    ->label('Jabatan')
                    ->options(fn() => Position::pluck('name', 'id'))
                    ->query(
                        fn(Builder $query, array $data) =>
                        $query->when(
                            $data['value'],
                            fn($q, $id) =>
                            $q->whereHas('user', fn($uq) => $uq->where('position_id', $id))
                        )
                    ),
            ])
            ->headerActions([
                Tables\Actions\Action::make('export_pdf')
                    ->label('Download PDF')
                    ->color('danger')
                    ->icon('heroicon-o-document-arrow-down')
                    ->requiresConfirmation()
                    ->modalHeading('Export PDF Riwayat Cuti')
                    ->action(function ($livewire) {
                        $data = $livewire->getFilteredTableQuery()->get();

                        $pdf = Pdf::loadView('exports.pdf.riwayat_cuti', [
                            'data' => $data,
                            'title' => 'LAPORAN RIWAYAT CUTI & IZIN KARYAWAN'
                        ])->setPaper('a4', 'portrait');

                        $fileName = 'Laporan_Riwayat_Cuti_' . now()->format('d-m-Y_H-i-s') . '.pdf';

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
            'index' => Pages\ManageLaporanRiwayatCutis::route('/'),
        ];
    }
}
