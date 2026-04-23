<?php

namespace App\Filament\Widgets;

use App\Models\Attendance;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class AttendanceWidget extends BaseWidget
{
    protected static ?int $sort = -2;
    protected int | string | array $columnSpan = 'full';

    public static function canView(): bool
    {
        return !auth()->user()->hasRole('super_admin');
    }

    protected function getGreeting(): string
    {
        $hour = Carbon::now()->hour;

        if ($hour < 12) {
            return 'Selamat Pagi';
        } elseif ($hour < 15) {
            return 'Selamat Siang';
        } elseif ($hour < 18) {
            return 'Selamat Sore';
        }
        return 'Selamat Malam';
    }

    protected function getStats(): array
    {
        $userId = Auth::id();

        // Cache untuk performa
        $attendance = cache()->remember(
            'user_attendance_today_' . $userId,
            300,
            fn() => Attendance::where('user_id', $userId)
                ->whereDate('created_at', Carbon::today())
                ->first()
        );

        $user = Auth::user();
        $remainingQuota = $user->leave_quota ?? 0;
        $cashableLeave = $user->cashable_leave ?? 0;

        $greeting = $this->getGreeting();

        // Logika Status
        if (!$attendance) {
            $statusLabel = 'Belum Absen';
            $btnLabel = "{$greeting}, silakan check-in sekarang!";
            $color = 'danger';
            $icon = 'heroicon-m-x-circle';
        } elseif (!$attendance->end_time) {
            $statusLabel = 'Sudah Masuk';
            $btnLabel = 'Jangan lupa check-out ya!';
            $color = 'warning';
            $icon = 'heroicon-m-arrow-right-start-on-rectangle';
        } else {
            $statusLabel = 'Selesai Kerja';
            $btnLabel = 'Terima kasih, hari ini selesai!';
            $color = 'success';
            $icon = 'heroicon-m-check-badge';
        }

        // Info terlambat
        $isLateToday = $attendance && $attendance->isLate();
        $lateMinutes = $isLateToday ? $attendance->start_time->diffInMinutes($attendance->schedule_start_time) : 0;

        $stats = [
            Stat::make('Status Kehadiran', $statusLabel)
                ->description($btnLabel)
                ->descriptionIcon('heroicon-m-map-pin')
                ->color($color)
                ->icon($icon)
                ->extraAttributes([
                    'class' => 'cursor-pointer hover:ring-2 hover:ring-primary-500 transition-all duration-300 rounded-xl',
                    'onclick' => "window.location.href='" . route('presensi') . "'",
                ]),

            Stat::make('Jam Datang', $attendance?->start_time?->format('H:i') ?? '--:--')
                ->icon('heroicon-m-arrow-right-start-on-rectangle')
                ->color($attendance?->start_time ? 'success' : 'gray'),

            Stat::make('Jam Pulang', $attendance?->end_time?->format('H:i') ?? '--:--')
                ->icon('heroicon-m-arrow-right-end-on-rectangle')
                ->color($attendance?->end_time ? 'success' : 'gray'),
        ];

        // Tambahan stat untuk sisa cuti
        $stats[] = Stat::make('Sisa Cuti', $remainingQuota . ' Hari')
            ->description('Saldo uang: ' . $cashableLeave . ' hari')
            ->descriptionIcon('heroicon-m-currency-dollar')
            ->color('info')
            ->icon('heroicon-m-calendar');

        // Jika terlambat, tampilkan peringatan
        if ($isLateToday) {
            $stats[] = Stat::make('Perhatian', 'Terlambat ' . $lateMinutes . ' menit')
                ->description('Mohon lebih disiplin')
                ->color('danger')
                ->icon('heroicon-m-exclamation-triangle');
        }

        // Link ke riwayat
        $stats[] = Stat::make('Riwayat Absensi', 'Lihat Semua')
            ->description('Klik untuk melihat riwayat lengkap')
            ->icon('heroicon-m-document-text')
            ->color('gray')
            ->extraAttributes([
                'class' => 'cursor-pointer hover:ring-2 hover:ring-primary-500 transition-all duration-300 rounded-xl',
                'onclick' => "window.location.href='" . route('filament.admin.resources.attendances.index') . "'",
            ]);

        return $stats;
    }
}
