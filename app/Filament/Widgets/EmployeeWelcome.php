<?php

namespace App\Filament\Widgets;

use App\Models\Attendance;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Carbon\Carbon;

class EmployeeWelcome extends BaseWidget
{
    protected static ?int $sort = 1;
    protected int | string | array $columnSpan = 'full';

    public static function canView(): bool
    {
        return auth()->user()->hasRole('karyawan');
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

    protected function getMotivationalQuote(): string
    {
        $quotes = [
            'Tetap semangat! ✨',
            'Jadilah yang terbaik! 🌟',
            'Produktif hari ini! 💪',
            'Keep spirit! 🔥',
            'Jangan lupa istirahat! ☕',
            'Kamu hebat! 🎉',
            'Terus belajar! 📚',
        ];
        return $quotes[array_rand($quotes)];
    }

    protected function getStats(): array
    {
        $user = auth()->user();
        $userId = $user->id;
        
        // Cache untuk performa
        $todayAttendance = cache()->remember(
            'user_attendance_today_' . $userId,
            300,
            fn() => Attendance::where('user_id', $userId)
                ->whereDate('created_at', Carbon::today())
                ->first()
        );
        
        $monthlyStats = cache()->remember(
            'user_monthly_stats_' . $userId . '_' . Carbon::now()->month,
            3600,
            fn() => [
                'total' => Attendance::where('user_id', $userId)
                    ->whereMonth('created_at', Carbon::now()->month)
                    ->count(),
                'late' => Attendance::where('user_id', $userId)
                    ->whereMonth('created_at', Carbon::now()->month)
                    ->get()
                    ->filter(fn($at) => $at->isLate())
                    ->count(),
            ]
        );
        
        $totalWorkDays = Carbon::now()->daysInMonth;
        $attendanceDays = $monthlyStats['total'];
        $percentage = $totalWorkDays > 0 ? round(($attendanceDays / $totalWorkDays) * 100) : 0;
        $remainingQuota = $user->leave_quota ?? 0;
        $cashableLeave = $user->cashable_leave ?? 0;
        
        $stats = [
            Stat::make($this->getGreeting() . ',', $user->name)
                ->description(Carbon::now()->isoFormat('dddd, D MMMM Y') . ' | ' . $this->getMotivationalQuote())
                ->descriptionIcon('heroicon-m-calendar-days')
                ->color('primary'),

            Stat::make('Status Presensi', $todayAttendance ? '✅ Sudah Absen' : '⏳ Belum Absen')
                ->description($todayAttendance
                    ? 'Masuk jam: ' . $todayAttendance->start_time?->format('H:i')
                    : 'Silakan lakukan absen di menu Maps')
                ->chart($todayAttendance ? [1, 2, 4, 3, 5, 4, 7] : [0, 0, 0, 0, 0, 0, 0])
                ->color($todayAttendance ? 'success' : 'danger'),

            Stat::make('Kehadiran Bulan Ini', $attendanceDays . ' / ' . $totalWorkDays . ' Hari')
                ->description($percentage . '% dari total hari kerja')
                ->descriptionIcon('heroicon-m-chart-bar')
                ->color($percentage >= 80 ? 'success' : ($percentage >= 60 ? 'warning' : 'danger'))
                ->chart([$percentage, 100 - $percentage]),
        ];
        
        // Sisa cuti
        $stats[] = Stat::make('Sisa Cuti', $remainingQuota . ' Hari')
            ->description('Saldo uang: ' . $cashableLeave . ' hari')
            ->descriptionIcon('heroicon-m-currency-dollar')
            ->color('info')
            ->icon('heroicon-m-calendar');
        
        // Jika ada keterlambatan
        if ($monthlyStats['late'] > 0) {
            $stats[] = Stat::make('Keterlambatan', $monthlyStats['late'] . ' Kali')
                ->description('Bulan ini')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color('danger')
                ->icon('heroicon-m-clock');
        }
        
        return $stats;
    }
}