<?php

namespace App\Livewire;

use App\Models\Attendance;
use App\Models\Office;
use App\Models\User;
use App\Models\Schedule;
use Livewire\Component;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class Maps extends Component
{
    public $selectedOffice = null;
    public $filterType = 'today';
    public $startDate = null;
    public $endDate = null;

    protected $listeners = ['refreshMap' => 'refreshData'];

    public function mount()
    {
        if (!auth()->user()->can('page_Maps')) {
            abort(403, 'Unauthorized access.');
        }

        if (!auth()->user()->hasRole(['super_admin', 'admin'])) {
            $userSchedule = Schedule::getActiveSchedule(auth()->id(), Carbon::today());
            if ($userSchedule && $userSchedule->office_id) {
                $this->selectedOffice = $userSchedule->office_id;
            }
        }

        $this->startDate = Carbon::today()->toDateString();
        $this->endDate = Carbon::today()->toDateString();
    }

    public function refreshData()
    {
        $this->dispatch('refreshMap');
    }

    public function updatedSelectedOffice()
    {
        $this->dispatch('refreshMap');
    }

    public function updatedFilterType()
    {
        $now = Carbon::now();

        switch ($this->filterType) {
            case 'today':
                $this->startDate = $now->toDateString();
                $this->endDate = $now->toDateString();
                break;
            case 'yesterday':
                $this->startDate = $now->copy()->subDay()->toDateString();
                $this->endDate = $now->copy()->subDay()->toDateString();
                break;
            case 'week':
                $this->startDate = $now->copy()->startOfWeek()->toDateString();
                $this->endDate = $now->copy()->endOfWeek()->toDateString();
                break;
            case 'month':
                $this->startDate = $now->copy()->startOfMonth()->toDateString();
                $this->endDate = $now->copy()->endOfMonth()->toDateString();
                break;
            case 'custom':
                break;
        }

        $this->dispatch('refreshMap');
    }

    public function render()
    {
        try {
            $offices = Cache::remember('offices_list', 3600, function () {
                return Office::orderBy('name', 'asc')->get();
            });

            // ========== QUERY ATTENDANCE (SUDAH ABSEN) ==========
            $query = Attendance::with(['user', 'schedule.office'])
                ->whereNotNull('start_latitude')
                ->whereNotNull('start_longitude')
                ->whereNotNull('schedule_id');

            // Filter tanggal
            if ($this->filterType === 'today') {
                $query->whereDate('created_at', Carbon::today());
            } elseif ($this->filterType === 'yesterday') {
                $query->whereDate('created_at', Carbon::yesterday());
            } elseif ($this->filterType === 'week') {
                $start = Carbon::now()->startOfWeek()->startOfDay();
                $end = Carbon::now()->endOfWeek()->endOfDay();
                $query->whereBetween('created_at', [$start, $end]);
            } elseif ($this->filterType === 'month') {
                $start = Carbon::now()->startOfMonth()->startOfDay();
                $end = Carbon::now()->endOfMonth()->endOfDay();
                $query->whereBetween('created_at', [$start, $end]);
            } elseif ($this->filterType === 'custom' && $this->startDate && $this->endDate) {
                $start = Carbon::parse($this->startDate)->startOfDay();
                $end = Carbon::parse($this->endDate)->endOfDay();
                $query->whereBetween('created_at', [$start, $end]);
            }

            // Filter office
            if ($this->selectedOffice) {
                $query->whereHas('schedule', fn($q) => $q->where('office_id', $this->selectedOffice));
            }

            $attendances = $query->orderBy('start_time', 'desc')
                ->limit(500)
                ->get()
                ->map(function ($item) {
                    return (object) [
                        'id' => $item->id,
                        'user_id' => $item->user_id,
                        'user' => $item->user,
                        'schedule' => $item->schedule,
                        'is_late' => $item->isLate(),
                        'jam_menit' => $item->start_time?->format('H:i') ?? '--:--',
                        'is_wfa' => $item->schedule?->is_wfa ?? false,
                        'office_name' => $item->schedule?->office?->name ?? 'Mobile / WFA',
                        'status_label' => $item->is_wfa ? 'WFA' : ($item->isLate() ? 'Terlambat' : 'Hadir'),
                        'display_location' => $item->is_wfa ? '🏠 Work From Anywhere' : '📍 ' . ($item->schedule?->office?->name ?? 'Mobile / WFA'),
                        'has_attended' => true,
                        'end_time_formatted' => $item->end_time?->format('H:i') ?? null,
                        'start_latitude' => $item->start_latitude,
                        'start_longitude' => $item->start_longitude,
                        'end_time' => $item->end_time,
                        'start_time' => $item->start_time,
                    ];
                });

            // ========== KARYAWAN YANG BELUM ABSEN ==========
            $allEmployees = User::role('karyawan')->get();
            $attendanceUserIds = $attendances->pluck('user_id')->toArray();

            $notYetAttended = $allEmployees->filter(function ($employee) use ($attendanceUserIds) {
                return !in_array($employee->id, $attendanceUserIds);
            })->map(function ($employee) {
                $activeSchedule = Schedule::getActiveSchedule($employee->id, Carbon::today());
                $office = $activeSchedule?->office;

                return (object) [
                    'id' => $employee->id,
                    'name' => $employee->name,
                    'user' => $employee,
                    'office_name' => $office?->name ?? 'Belum ada jadwal',
                    'office_id' => $office?->id ?? null,
                    'display_location' => '⏰ Belum absen hari ini',
                    'status_label' => 'Belum Absen',
                    'has_attended' => false,
                    'jam_menit' => '--:--',
                    'is_late' => false,
                    'is_wfa' => false,
                    'end_time_formatted' => null,
                    'start_latitude' => null,
                    'start_longitude' => null,
                ];
            });

            // Filter karyawan belum absen berdasarkan office
            if ($this->selectedOffice) {
                $notYetAttended = $notYetAttended->filter(function ($employee) {
                    return $employee->office_id == $this->selectedOffice;
                });
            }

            // ========== STATISTIK ==========
            $stats = [
                'total' => $attendances->count(),
                'late' => $attendances->where('is_late', true)->where('is_wfa', false)->count(),
                'on_time' => $attendances->where('is_late', false)->where('is_wfa', false)->count(),
                'wfa' => $attendances->where('is_wfa', true)->count(),
                'not_yet' => $notYetAttended->count(),
                'period' => $this->getPeriodLabel(),
            ];

            // ========== GROUPING ==========
            $groupedAttendances = $attendances->groupBy('office_name');
            $groupedNotYet = $notYetAttended->groupBy('office_name');

            $this->dispatch('updateMarkers', [
                'attendances' => $attendances->map(function ($a) {
                    return [
                        'id' => $a->id,
                        'user_id' => $a->user_id,
                        'user' => ['name' => $a->user->name],
                        'start_latitude' => $a->start_latitude,
                        'start_longitude' => $a->start_longitude,
                        'is_late' => $a->is_late,
                        'is_wfa' => $a->is_wfa,
                        'jam_menit' => $a->jam_menit,
                        'display_location' => $a->display_location,
                    ];
                })->toArray(),
                'offices' => $offices->toArray(),
                'selectedId' => $this->selectedOffice
            ]);

            return view('livewire.maps', [
                'attendances' => $attendances,
                'offices' => $offices,
                'stats' => $stats,
                'groupedAttendances' => $groupedAttendances,
                'groupedNotYet' => $groupedNotYet,
                'filterType' => $this->filterType,
                'startDate' => $this->startDate,
                'endDate' => $this->endDate,
            ]);
        } catch (\Exception $e) {
            return view('livewire.maps', [
                'attendances' => collect([]),
                'offices' => collect([]),
                'stats' => ['total' => 0, 'late' => 0, 'on_time' => 0, 'wfa' => 0, 'not_yet' => 0],
                'groupedAttendances' => collect([]),
                'groupedNotYet' => collect([]),
                'filterType' => $this->filterType,
                'startDate' => $this->startDate,
                'endDate' => $this->endDate,
            ]);
        }
    }

    private function getPeriodLabel(): string
    {
        switch ($this->filterType) {
            case 'today':
                return 'Hari Ini';
            case 'yesterday':
                return 'Kemarin';
            case 'week':
                return 'Minggu Ini';
            case 'month':
                return 'Bulan Ini';
            case 'custom':
                return Carbon::parse($this->startDate)->format('d/m/Y') . ' - ' . Carbon::parse($this->endDate)->format('d/m/Y');
            default:
                return 'Semua Data';
        }
    }
}
