<?php

namespace App\Filament\Widgets;

use App\Models\User;
use App\Models\Leave;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Carbon\Carbon;

class LeaveOverview extends BaseWidget
{
    protected static ?int $sort = 1;
    protected int | string | array $columnSpan = 'full';

    public static function canView(): bool
    {
        return auth()->user()->hasRole(['super_admin', 'admin']);
    }

    protected function getStats(): array
    {
        // Hitung sedang cuti hari ini (unique user)
        $onLeaveToday = Leave::where('status', 'approved')
            ->whereDate('start_date', '<=', today())
            ->whereDate('end_date', '>=', today())
            ->distinct('user_id')
            ->count('user_id');
        
        // Rata-rata sisa cuti
        $avgQuota = round(User::avg('leave_quota') ?? 0, 1);
        
        // Total cuti terpakai tahun ini
        $usedThisYear = Leave::where('status', 'approved')
            ->whereYear('created_at', date('Y'))
            ->count();
        
        // Cuti yang akan datang
        $upcoming = Leave::where('status', 'approved')
            ->whereDate('start_date', '>', today())
            ->count();
        
        // Cuti ditolak tahun ini
        $rejected = Leave::where('status', 'rejected')
            ->whereYear('created_at', date('Y'))
            ->count();
        
        // Persentase penggunaan cuti
        $totalQuota = User::sum('leave_quota');
        $usedQuota = Leave::where('status', 'approved')
            ->whereYear('created_at', date('Y'))
            ->get()
            ->sum('duration');
        $percentage = $totalQuota > 0 ? round(($usedQuota / $totalQuota) * 100) : 0;
        
        return [
            Stat::make('Sedang Cuti', $onLeaveToday . ' Orang')
                ->description('Tidak di kantor hari ini')
                ->descriptionIcon('heroicon-m-arrow-right-on-rectangle')
                ->color('danger')
                ->icon('heroicon-m-user-minus')
                ->extraAttributes([
                    'class' => 'cursor-pointer hover:ring-2 hover:ring-primary-500 transition-all duration-300 rounded-xl',
                    'onclick' => "window.location.href='" . route('filament.admin.resources.leaves.index', ['tableFilters[status][value]' => 'approved']) . "'",
                ]),

            Stat::make('Rata-rata Sisa Cuti', $avgQuota . ' Hari')
                ->description('Kuota per karyawan')
                ->descriptionIcon('heroicon-m-chart-pie')
                ->color('info')
                ->icon('heroicon-m-calculator'),

            Stat::make('Cuti Terpakai', $usedThisYear . ' Pengajuan')
                ->description('Disetujui tahun ini')
                ->descriptionIcon('heroicon-m-check-badge')
                ->color('success')
                ->icon('heroicon-m-document-check'),

            Stat::make('Akan Datang', $upcoming . ' Pengajuan')
                ->description('Cuti yang sudah disetujui')
                ->descriptionIcon('heroicon-m-calendar')
                ->color('warning')
                ->icon('heroicon-m-clock'),

            Stat::make('Penggunaan Cuti', $percentage . '%')
                ->description("Dari total kuota {$totalQuota} hari")
                ->descriptionIcon('heroicon-m-chart-bar')
                ->color($percentage > 70 ? 'danger' : ($percentage > 40 ? 'warning' : 'success'))
                ->icon('heroicon-m-chart-pie')
                ->chart([$percentage, 100 - $percentage]),
        ];
    }
}