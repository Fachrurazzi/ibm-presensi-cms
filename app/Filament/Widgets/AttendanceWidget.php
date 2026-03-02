<?php

namespace App\Filament\Widgets;

use App\Models\Attendance;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class AttendanceWidget extends BaseWidget
{
    // Agar widget tampil paling atas dan lebar (full width)
    protected static ?int $sort = -2;
    protected int | string | array $columnSpan = 'full';

    protected function getStats(): array
    {
        $attendance = Attendance::where('user_id', Auth::id())
            ->whereDate('created_at', Carbon::today())
            ->first();

        // Logika Status
        if (!$attendance) {
            $statusLabel = 'Belum Absen';
            $btnLabel = 'Klik untuk Masuk (Check-In)';
            $color = 'danger';
        } elseif (!$attendance->end_time) {
            $statusLabel = 'Sudah Masuk';
            $btnLabel = 'Klik untuk Pulang (Check-Out)';
            $color = 'warning';
        } else {
            $statusLabel = 'Selesai Kerja';
            $btnLabel = 'Presensi Hari Ini Lengkap';
            $color = 'success';
        }

        return [
            Stat::make('Status Kehadiran', $statusLabel)
                ->description($btnLabel)
                ->descriptionIcon('heroicon-m-map-pin')
                ->color($color)
                ->extraAttributes([
                    'class' => 'cursor-pointer hover:ring-2 hover:ring-primary-500 transition-all duration-300 rounded-xl',
                    'onclick' => "window.location.href='" . route('presensi') . "'",
                ]),

            Stat::make('Jam Datang', $attendance?->start_time?->format('H:i') ?? '--:--'),
            Stat::make('Jam Pulang', $attendance?->end_time?->format('H:i') ?? '--:--'),
        ];
    }

    // Hanya tampilkan widget ini untuk Karyawan (Admin tidak perlu tombol absen di dashboardnya)
    public static function canView(): bool
    {
        return !auth()->user()->hasRole('super_admin');
    }
}
