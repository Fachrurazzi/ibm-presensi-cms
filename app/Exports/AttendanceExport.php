<?php

namespace App\Exports;

use App\Models\User;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Carbon\Carbon;

class AttendanceExport implements FromView, ShouldAutoSize
{
    protected $startDate;
    protected $endDate;
    protected $supervisor;
    protected $userId;   // Tambahan
    protected $officeId; // Tambahan

    // Tambahkan variabel baru ke dalam Constructor
    public function __construct($startDate, $endDate, $supervisor = null, $userId = null, $officeId = null)
    {
        $this->startDate = Carbon::parse($startDate)->startOfDay();
        $this->endDate = Carbon::parse($endDate)->endOfDay();
        $this->supervisor = $supervisor;
        $this->userId = $userId;
        $this->officeId = $officeId;

        if (!auth()->user()->hasRole('super_admin')) {
            abort(403, 'Anda tidak memiliki akses untuk melakukan export.');
        }
    }

    public function view(): View
    {
        $dates = [];
        for ($date = $this->startDate->copy(); $date->lte($this->endDate); $date->addDay()) {
            $dates[] = $date->copy();
        }

        $query = User::with([
            'attendances' => fn($q) => $q->whereBetween('created_at', [$this->startDate, $this->endDate]),
            'position',
            'schedules.office'
        ]);

        // Filter 1: Supervisor
        if ($this->supervisor) {
            $query->whereHas('schedules.office', fn($q) => $q->where('supervisor_name', $this->supervisor));
        }

        // --- Filter 2: Karyawan Tertentu ---
        if ($this->userId) {
            $query->where('id', $this->userId);
        }

        // --- Filter 3: Cabang Tertentu ---
        if ($this->officeId) {
            $query->whereHas('schedules.office', fn($q) => $q->where('id', $this->officeId));
        }

        $users = $query->get()->groupBy(function ($user) {
            return $user->schedules->first()?->office?->supervisor_name ?? 'TANPA AREA';
        });

        return view('exports.attendance-weekly', [
            'users' => $users,
            'dates' => $dates,
            'startDate' => $this->startDate,
            'endDate' => $this->endDate
        ]);
    }
}
