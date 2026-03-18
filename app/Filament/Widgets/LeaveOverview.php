<?php

namespace App\Filament\Widgets;

use App\Models\User;
use App\Models\Leave;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Carbon\Carbon;

class LeaveOverview extends BaseWidget
{
    // Atur urutannya agar tampil tepat di bawah StatsOverview
    protected static ?int $sort = 1;

    // Pastikan hanya Admin yang bisa melihat ini
    public static function canView(): bool
    {
        return auth()->user()->hasRole(['super_admin', 'admin']);
    }

    protected function getStats(): array
    {
        return [
            // 1. PERUBAHAN: Ganti "Total Karyawan" menjadi metrik yang lebih relevan
            Stat::make('Sedang Cuti Hari Ini', Leave::where('status', 'approved')
                ->whereDate('start_date', '<=', today())
                ->whereDate('end_date', '>=', today())
                ->count() . ' Orang')
                ->description('Karyawan tidak ada di kantor')
                ->descriptionIcon('heroicon-m-arrow-right-on-rectangle')
                ->color('danger'),

            // 2. PERBAIKAN: Ditambah visual ikon agar rapi
            Stat::make('Rata-rata Sisa Cuti', round(User::avg('leave_quota'), 1) . ' Hari')
                ->description('Kuota rata-rata per orang')
                ->descriptionIcon('heroicon-m-chart-pie')
                ->color('info'),

            // 3. PERBAIKAN: Filter berdasarkan tahun ini saja & tambah visual
            Stat::make('Cuti Terpakai Tahun Ini', Leave::where('status', 'approved')
                ->whereYear('created_at', date('Y'))
                ->count() . ' Pengajuan')
                ->description('Total disetujui tahun ini')
                ->descriptionIcon('heroicon-m-check-badge')
                ->color('success'),
        ];
    }
}
