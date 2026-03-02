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
    protected static ?int $sort = 1; // Agar muncul di paling atas sebelum grafik

    // Tambahkan ini di dalam class setiap widget
    public static function canView(): bool
    {
        // Grafik hanya muncul untuk Admin dan Super Admin
        return auth()->user()->hasRole(['super_admin', 'admin']);
    }
    protected function getStats(): array
    {
        return [
            Stat::make('Total Karyawan', User::count())
                ->description('Karyawan aktif terdaftar')
                ->descriptionIcon('heroicon-m-users')
                ->chart([7, 2, 10, 3, 15, 4, 17]) // Dekorasi grafik
                ->color('info'),

            Stat::make('Hadir Hari Ini', Attendance::whereDate('created_at', today())->count())
                ->description('Karyawan sudah check-in')
                ->descriptionIcon('heroicon-m-check-badge')
                ->chart([1, 5, 2, 10, 5, 12, 15])
                ->color('success'),

            Stat::make('Cuti Pending', Leave::where('status', 'pending')->count())
                ->description('Perlu respon segera')
                ->descriptionIcon('heroicon-m-clock')
                ->color('warning'),
        ];
    }
}
