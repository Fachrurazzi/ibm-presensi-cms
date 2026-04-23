<?php

namespace App\Filament\Widgets;

use App\Models\Position;
use Filament\Widgets\ChartWidget;

class EmployeePositionChart extends ChartWidget
{
    protected static ?string $heading = 'Distribusi Karyawan per Jabatan';
    protected static ?int $sort = 2;
    protected int | string | array $columnSpan = 1;

    public ?string $positionId = null;

    public static function canView(): bool
    {
        return auth()->user()->hasRole(['super_admin', 'admin']);
    }

    // PERBAIKAN: Ubah dari protected menjadi public
    public function getHeading(): string
    {
        $total = Position::withCount('users')->get()->sum('users_count');
        return "Distribusi Karyawan per Jabatan (Total: {$total} karyawan)";
    }

    protected function getFormSchema(): array
    {
        return [
            \Filament\Forms\Components\Select::make('positionId')
                ->label('Filter Jabatan')
                ->options(fn() => Position::pluck('name', 'id'))
                ->placeholder('Semua Jabatan')
                ->reactive()
                ->afterStateUpdated(fn() => $this->updateChart()),
        ];
    }

    protected function getData(): array
    {
        $query = Position::withCount('users');

        if ($this->positionId) {
            $query->where('id', $this->positionId);
        }

        $positions = $query->get();

        // Empty state
        if ($positions->isEmpty()) {
            return [
                'datasets' => [
                    [
                        'label' => 'Jumlah Karyawan',
                        'data' => [1],
                        'backgroundColor' => ['#94a3b8'],
                        'borderWidth' => 0,
                    ],
                ],
                'labels' => ['Belum ada data'],
            ];
        }

        // Warna dinamis
        $colorPalette = [
            '#f97316',
            '#3b82f6',
            '#10b981',
            '#f59e0b',
            '#8b5cf6',
            '#ef4444',
            '#06b6d4',
            '#ec4899',
            '#6366f1',
            '#84cc16',
        ];

        $colors = [];
        for ($i = 0; $i < $positions->count(); $i++) {
            $colors[] = $colorPalette[$i % count($colorPalette)];
        }

        return [
            'datasets' => [
                [
                    'label' => 'Jumlah Karyawan',
                    'data' => $positions->pluck('users_count')->toArray(),
                    'backgroundColor' => $colors,
                    'borderWidth' => 0,
                ],
            ],
            'labels' => $positions->pluck('name')->toArray(),
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => [
                    'position' => 'bottom',
                    'labels' => [
                        'font' => ['size' => 10],
                        'boxWidth' => 12,
                    ],
                ],
                'tooltip' => [
                    'callbacks' => [
                        'label' => 'function(context) {
                            let label = context.label || "";
                            let value = context.raw || 0;
                            let total = context.dataset.data.reduce((a, b) => a + b, 0);
                            let percentage = total > 0 ? Math.round((value / total) * 100) : 0;
                            return label + ": " + value + " (" + percentage + "%)";
                        }',
                    ],
                ],
            ],
            'cutout' => '60%',
        ];
    }
}
