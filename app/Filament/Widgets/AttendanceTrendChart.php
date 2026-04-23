<?php

namespace App\Filament\Widgets;

use App\Models\Attendance;
use Filament\Widgets\ChartWidget;
use Carbon\Carbon;

class AttendanceTrendChart extends ChartWidget
{
    protected static ?string $heading = 'Statistik Kehadiran (7 Hari Terakhir)';
    protected int | string | array $columnSpan = 1;
    protected static ?int $sort = 3;
    
    public ?string $officeId = null;
    public ?string $period = '7days';

    public static function canView(): bool
    {
        return auth()->user()->hasRole(['super_admin', 'admin']);
    }

    protected function getFormSchema(): array
    {
        return [
            \Filament\Forms\Components\Select::make('officeId')
                ->label('Cabang')
                ->options(fn() => \App\Models\Office::pluck('name', 'id'))
                ->placeholder('Semua Cabang')
                ->reactive()
                ->afterStateUpdated(fn() => $this->updateChart()),
                
            \Filament\Forms\Components\Select::make('period')
                ->label('Periode')
                ->options([
                    '7days' => '7 Hari Terakhir',
                    '14days' => '14 Hari Terakhir',
                    '30days' => '30 Hari Terakhir',
                ])
                ->default('7days')
                ->reactive()
                ->afterStateUpdated(fn() => $this->updateChart()),
        ];
    }

    protected function getDays(): int
    {
        return match ($this->period) {
            '14days' => 13,
            '30days' => 29,
            default => 6,
        };
    }

    protected function getTotalStats(): array
    {
        $days = $this->getDays();
        $startDate = now()->subDays($days)->startOfDay();
        $endDate = now()->endOfDay();
        
        $query = Attendance::whereBetween('created_at', [$startDate, $endDate]);
        
        if ($this->officeId) {
            $query->whereHas('user.schedules', fn($q) => $q->where('office_id', $this->officeId));
        }
        
        $attendances = $query->get();
        
        $totalTepat = $attendances->filter(fn($at) => !$at->isLate())->count();
        $totalLambat = $attendances->filter(fn($at) => $at->isLate())->count();
        $total = $totalTepat + $totalLambat;
        $percentage = $total > 0 ? round(($totalTepat / $total) * 100, 1) : 0;
        
        return [
            'total_tepat' => $totalTepat,
            'total_lambat' => $totalLambat,
            'percentage' => $percentage,
        ];
    }

    // PERBAIKAN: Ubah dari protected menjadi public
    public function getHeading(): string
    {
        $stats = $this->getTotalStats();
        $periodText = match ($this->period) {
            '14days' => '14 Hari',
            '30days' => '30 Hari',
            default => '7 Hari',
        };
        return "Statistik Kehadiran ({$periodText} Terakhir) - {$stats['percentage']}% Tepat Waktu";
    }

    protected function getData(): array
    {
        $days = $this->getDays();
        $startDate = now()->subDays($days)->startOfDay();
        $endDate = now()->endOfDay();
        
        $query = Attendance::with(['user.schedules.office'])
            ->whereBetween('created_at', [$startDate, $endDate]);
        
        if ($this->officeId) {
            $query->whereHas('user.schedules', fn($q) => $q->where('office_id', $this->officeId));
        }
        
        $attendances = $query->get();

        if ($attendances->isEmpty()) {
            $emptyData = collect(range($days, 0))->map(fn() => 0);
            return [
                'datasets' => [
                    [
                        'label' => 'Tepat Waktu',
                        'data' => $emptyData->toArray(),
                        'borderColor' => '#10b981',
                        'fill' => 'start',
                        'backgroundColor' => 'rgba(16, 185, 129, 0.1)',
                        'tension' => 0.4,
                        'pointBackgroundColor' => '#10b981',
                        'pointBorderColor' => '#fff',
                        'pointRadius' => 4,
                        'pointHoverRadius' => 6,
                    ],
                    [
                        'label' => 'Terlambat',
                        'data' => $emptyData->toArray(),
                        'borderColor' => '#ef4444',
                        'fill' => 'start',
                        'backgroundColor' => 'rgba(239, 68, 68, 0.1)',
                        'tension' => 0.4,
                        'pointBackgroundColor' => '#ef4444',
                        'pointBorderColor' => '#fff',
                        'pointRadius' => 4,
                        'pointHoverRadius' => 6,
                    ],
                ],
                'labels' => collect(range($days, 0))->map(fn($i) => now()->subDays($i)->isoFormat('ddd'))->toArray(),
            ];
        }

        $data = collect(range($days, 0))->map(function ($daysAgo) use ($attendances) {
            $date = now()->subDays($daysAgo)->toDateString();
            
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
                    'borderColor' => '#10b981',
                    'fill' => 'start',
                    'backgroundColor' => 'rgba(16, 185, 129, 0.1)',
                    'tension' => 0.4,
                    'pointBackgroundColor' => '#10b981',
                    'pointBorderColor' => '#fff',
                    'pointRadius' => 4,
                    'pointHoverRadius' => 6,
                ],
                [
                    'label' => 'Terlambat',
                    'data' => $data->pluck('lambat')->toArray(),
                    'borderColor' => '#ef4444',
                    'fill' => 'start',
                    'backgroundColor' => 'rgba(239, 68, 68, 0.1)',
                    'tension' => 0.4,
                    'pointBackgroundColor' => '#ef4444',
                    'pointBorderColor' => '#fff',
                    'pointRadius' => 4,
                    'pointHoverRadius' => 6,
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