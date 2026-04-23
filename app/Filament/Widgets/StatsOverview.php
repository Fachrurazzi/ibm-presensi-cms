<?php

namespace App\Filament\Widgets;

use App\Models\User;
use App\Models\Attendance;
use App\Models\Leave;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Carbon\Carbon;

class StatsOverview extends BaseWidget
{
    protected static ?int $sort = 1;
    protected int | string | array $columnSpan = 'full';

    public static function canView(): bool
    {
        return auth()->user()->hasRole(['super_admin', 'admin']);
    }

    protected function getStats(): array
    {
        // Cache untuk performa
        $totalEmployees = cache()->remember('total_employees', 3600, fn() => User::count());

        $presentToday = cache()->remember(
            'present_today_' . today()->format('Y-m-d'),
            300,
            fn() =>
            Attendance::whereDate('created_at', today())->count()
        );

        $pendingLeaves = cache()->remember(
            'pending_leaves',
            300,
            fn() =>
            Leave::where('status', 'pending')->count()
        );

        $lateToday = cache()->remember(
            'late_today_' . today()->format('Y-m-d'),
            300,
            fn() =>
            Attendance::whereDate('created_at', today())
                ->get()
                ->filter(fn($at) => $at->isLate())
                ->count()
        );

        // Persentase kehadiran
        $attendancePercentage = $totalEmployees > 0 ? round(($presentToday / $totalEmployees) * 100) : 0;

        // Cuti pending yang butuh perhatian
        $criticalPending = $pendingLeaves > 5 ? 'danger' : ($pendingLeaves > 0 ? 'warning' : 'success');

        return [
            Stat::make('Total Karyawan', number_format($totalEmployees))
                ->description('Karyawan aktif terdaftar')
                ->descriptionIcon('heroicon-m-users')
                ->chart([7, 2, 10, 3, 15, 4, 17])
                ->color('info')
                ->icon('heroicon-m-user-group')
                ->extraAttributes([
                    'class' => 'cursor-pointer hover:ring-2 hover:ring-primary-500 transition-all duration-300 rounded-xl',
                    'onclick' => "window.location.href='" . route('filament.admin.resources.users.index') . "'",
                ]),

            Stat::make('Hadir Hari Ini', number_format($presentToday))
                ->description(number_format($attendancePercentage) . '% dari total karyawan')
                ->descriptionIcon('heroicon-m-check-badge')
                ->chart([$presentToday, max(0, $totalEmployees - $presentToday)])
                ->color($attendancePercentage >= 80 ? 'success' : ($attendancePercentage >= 60 ? 'warning' : 'danger'))
                ->icon('heroicon-m-user-circle')

                ->extraAttributes([
                    'class' => 'cursor-pointer hover:ring-2 hover:ring-primary-500 transition-all duration-300 rounded-xl',
                    'onclick' => "window.location.href='" . route('filament.admin.resources.attendances.index', ['tableFilters[created_at][from]' => today()->format('Y-m-d')]) . "'",
                ]),

            Stat::make('Terlambat', number_format($lateToday) . ' Orang')
                ->description('Datang melebihi jam kerja')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color('danger')
                ->icon('heroicon-m-clock')
                ->extraAttributes([
                    'class' => 'cursor-pointer hover:ring-2 hover:ring-primary-500 transition-all duration-300 rounded-xl',
                    'onclick' => "window.location.href='" . route('filament.admin.resources.attendances.index', [
                        'tableFilters[created_at][from]' => today()->format('Y-m-d'),
                        'tableFilters[is_late_filter][value]' => '1'
                    ]) . "'",
                ]),

            Stat::make('Cuti Pending', number_format($pendingLeaves))
                ->description('Perlu respon segera')
                ->descriptionIcon('heroicon-m-clock')
                ->color($criticalPending)
                ->icon('heroicon-m-document-text')
                ->extraAttributes([
                    'class' => 'cursor-pointer hover:ring-2 hover:ring-primary-500 transition-all duration-300 rounded-xl',
                    'onclick' => "window.location.href='" . route('filament.admin.resources.leaves.index', ['tableFilters[status][value]' => 'pending']) . "'",
                ]),
        ];
    }
}
