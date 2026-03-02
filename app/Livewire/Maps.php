<?php

namespace App\Livewire;

use App\Models\Attendance;
use App\Models\Office;
use Livewire\Component;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class Maps extends Component
{
    public $selectedOffice = null;

    public function mount()
    {
        if (!Auth::user()->can('page_Maps')) {
            abort(403, 'Unauthorized access.');
        }
    }

    public function render()
    {
        $offices = Office::orderBy('name', 'asc')->get();

        $query = Attendance::with(['user', 'schedule.office'])
            ->whereDate('created_at', Carbon::today())
            ->whereNotNull('schedule_id');

        if ($this->selectedOffice) {
            $query->whereHas('schedule', fn($q) => $q->where('office_id', $this->selectedOffice));
        }

        $attendances = $query->orderBy('start_time', 'desc')->get()->map(function ($item) {
            // FIX: Menggunakan fungsi isLate() dari Model Attendance
            $item->is_late = $item->isLate();

            $item->jam_menit = $item->start_time->format('H:i');

            // Logika Label
            if ($item->is_wfa) {
                $item->status_label = 'WFA';
            } else {
                $item->status_label = $item->is_late ? 'Terlambat' : 'Hadir';
            }

            $item->office_name = $item->schedule->office->name ?? 'Mobile / WFA';
            $item->display_location = $item->is_wfa ? '🏠 Work From Anywhere' : '📍 ' . $item->office_name;

            return $item;
        });

        $stats = [
            'total' => $attendances->count(),
            'late' => $attendances->where('is_late', true)->where('is_wfa', false)->count(),
            'on_time' => $attendances->where('is_late', false)->count(),
            'wfa' => $attendances->where('is_wfa', true)->count(),
        ];

        $groupedAttendances = $attendances->groupBy('office_name');

        $this->dispatch('updateMarkers', [
            'attendances' => $attendances->toArray(),
            'offices' => $offices->toArray(),
            'selectedId' => $this->selectedOffice
        ]);

        return view('livewire.maps', compact('attendances', 'offices', 'stats', 'groupedAttendances'));
    }
}
