<?php

namespace App\Filament\Widgets;

use App\Models\Attendance;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

class LatestAttendances extends BaseWidget
{
    // Menampilkan judul widget
    protected static ?string $heading = 'Presensi Terbaru (Hari Ini)';

    // Mengatur urutan tampilan agar di bawah grafik
    protected static ?int $sort = 4;

    // Mengatur agar tabel memanjang penuh
    protected int | string | array $columnSpan = 'full'; // Tambahkan ini di dalam class setiap widget
    public static function canView(): bool
    {
        // Grafik hanya muncul untuk Admin dan Super Admin
        return auth()->user()->hasRole(['super_admin', 'admin']);
    }



    public function table(Table $table): Table
    {
        return $table
            ->query(
                // Mengambil data presensi hari ini yang terbaru
                Attendance::query()
                    ->with(['user.position', 'schedule.office'])
                    ->whereDate('created_at', today())
                    ->latest()
                    ->limit(10)
            )
            ->poll('30s')
            ->columns([
                Tables\Columns\ImageColumn::make('user.image') // Menampilkan foto profil
                    ->label('')
                    ->circular()
                    ->defaultImageUrl(fn($record) => 'https://ui-avatars.com/api/?name=' . urlencode($record->user->name)),

                Tables\Columns\TextColumn::make('user.name')
                    ->label('Karyawan')
                    ->description(fn($record) => $record->user->position?->name ?? 'Staff')
                    ->weight('bold'),

                // Gunakan icon yang berbeda untuk Masuk dan Keluar agar mata mudah membedakan
                Tables\Columns\TextColumn::make('start_time')
                    ->label('Check In')
                    ->dateTime('H:i')
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('end_time')
                    ->label('Check Out')
                    ->dateTime('H:i')
                    ->placeholder('Masih Bekerja')
                    ->badge()
                    ->color('warning'),

                Tables\Columns\TextColumn::make('status')
                    ->label('Keterangan')
                    ->badge()
                    ->getStateUsing(fn($record) => $record->isLate() ? 'Terlambat' : 'Tepat Waktu')
                    ->color(fn($record) => $record->isLate() ? 'danger' : 'success'),
            ]);
    }
}
