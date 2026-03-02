<?php

namespace App\Filament\Widgets;

use App\Models\Attendance;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Carbon\Carbon;

class EmployeeWelcome extends BaseWidget
{
    protected static ?int $sort = 1;

    // PENTING: Widget ini HANYA muncul untuk karyawan
    public static function canView(): bool
    {
        return auth()->user()->hasRole('karyawan');
    }

    protected function getStats(): array
    {
        $user = auth()->user();
        $todayAttendance = Attendance::where('user_id', $user->id)
            ->whereDate('created_at', Carbon::today())
            ->first();

        return [
            Stat::make('Selamat Datang,', $user->name)
                ->description(Carbon::now()->isoFormat('dddd, D MMMM Y'))
                ->descriptionIcon('heroicon-m-calendar-days')
                ->color('primary'),

            Stat::make('Status Presensi Hari Ini', $todayAttendance ? 'Sudah Absen' : 'Belum Absen')
                ->description($todayAttendance
                    ? 'Masuk jam: ' . $todayAttendance->start_time?->format('H:i')
                    : 'Silakan lakukan absen di menu Maps')
                // Tambahkan Chart kecil agar UI lebih cantik dan konsisten
                ->chart($todayAttendance ? [1, 2, 4, 3, 5, 4, 7] : [0, 0, 0, 0, 0, 0])
                ->color($todayAttendance ? 'success' : 'danger'),

            Stat::make('Total Kehadiran Bulan Ini', Attendance::where('user_id', $user->id)
                ->whereMonth('created_at', Carbon::now()->month)
                ->count() . ' Hari')
                ->description('Tetap semangat bekerja!')
                ->descriptionIcon('heroicon-m-sparkles')
                ->color('warning'),
        ];
    }
}
