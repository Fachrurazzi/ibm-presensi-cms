<?php

namespace App\Filament\Widgets;

use App\Models\Attendance;
use Filament\Widgets\ChartWidget;
use Carbon\Carbon;

class AttendanceTrendChart extends ChartWidget
{
    protected static ?string $heading = 'Statistik Kehadiran (7 Hari Terakhir)';

    // Agar berjejer dengan Pie Chart (setengah layar)
    protected int | string | array $columnSpan = 1;

    protected static ?int $sort = 3;

    // Tambahkan ini di dalam class setiap widget
    public static function canView(): bool
    {
        // Grafik hanya muncul untuk Admin dan Super Admin
        return auth()->user()->hasRole(['super_admin', 'admin']);
    }

    protected function getData(): array
    {
        $startDate = now()->subDays(6)->startOfDay();
        $endDate = now()->endOfDay();

        // Ambil data dalam SATU kali query
        $attendances = Attendance::whereBetween('created_at', [$startDate, $endDate])->get();

        $data = collect(range(6, 0))->map(function ($daysAgo) use ($attendances) {
            $date = now()->subDays($daysAgo)->toDateString();

            // Filter dari koleksi yang sudah di-load, bukan nembak database lagi
            $dailyData = $attendances->filter(fn($at) => Carbon::parse($at->created_at)->toDateString() === $date);

            return [
                'label' => now()->subDays($daysAgo)->isoFormat('ddd'),
                'tepat' => $dailyData->filter(fn($at) => !$at->isLate())->count(),
                'lambat' => $dailyData->filter(fn($at) => $at->isLate())->count(),
            ];
        });

        return [
            'datasets' => [
                [
                    'label' => 'Tepat Waktu',
                    'data' => $data->pluck('tepat')->toArray(),
                    'borderColor' => '#10b981', // Hijau Emerald
                    'fill' => 'start',
                    'backgroundColor' => 'rgba(16, 185, 129, 0.1)', // Efek gradasi transparan
                    'tension' => 0.4, // Membuat garis melengkung/smooth
                ],
                [
                    'label' => 'Terlambat',
                    'data' => $data->pluck('lambat')->toArray(),
                    'borderColor' => '#ef4444', // Merah
                    'fill' => 'start',
                    'backgroundColor' => 'rgba(239, 68, 68, 0.1)',
                    'tension' => 0.4,
                ],
            ],
            'labels' => $data->pluck('label')->toArray(),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
