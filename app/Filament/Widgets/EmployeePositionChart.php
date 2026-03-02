<?php

namespace App\Filament\Widgets;

use App\Models\Position;
use Filament\Widgets\ChartWidget;

class EmployeePositionChart extends ChartWidget
{
    protected static ?string $heading = 'Distribusi Karyawan per Jabatan';
    protected static ?int $sort = 2; // Agar muncul setelah Stats Overview
    // Tambahkan baris ini di dalam class EmployeePositionChart
    protected int | string | array $columnSpan = 1;


    // Tambahkan ini di dalam class setiap widget
    public static function canView(): bool
    {
        // Grafik hanya muncul untuk Admin dan Super Admin
        return auth()->user()->hasRole(['super_admin', 'admin']);
    }

    protected function getData(): array
    {
        // Ambil data jabatan beserta jumlah usernya
        $positions = Position::withCount('users')->get();

        return [
            'datasets' => [
                [
                    'label' => 'Jumlah Karyawan',
                    'data' => $positions->pluck('users_count')->toArray(),
                    // Memberikan warna gradasi orange-blue yang profesional
                    'backgroundColor' => [
                        '#f97316',
                        '#3b82f6',
                        '#10b981',
                        '#f59e0b',
                        '#8b5cf6'
                    ],
                ],
            ],
            'labels' => $positions->pluck('name')->toArray(),
        ];
    }

    protected function getType(): string
    {
        return 'pie';
    }
}
