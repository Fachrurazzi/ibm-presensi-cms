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

    public function __construct($startDate, $endDate, $supervisor = null)
    {
        $this->startDate = Carbon::parse($startDate)->startOfDay();
        $this->endDate = Carbon::parse($endDate)->endOfDay();
        $this->supervisor = $supervisor;

        if (!auth()->user()->hasRole('super_admin')) {
            abort(403, 'Anda tidak memiliki akses untuk melakukan export.');
        }
    }

    // app/Exports/AttendanceExport.php

    public function view(): View
    {
        $dates = [];
        for ($date = $this->startDate->copy(); $date->lte($this->endDate); $date->addDay()) {
            $dates[] = $date->copy();
        }

        $query = User::with([
            // Eager load absensi hanya di rentang tanggal tersebut
            'attendances' => fn($q) => $q->whereBetween('created_at', [$this->startDate, $this->endDate]),
            'position',
            'schedules.office'
        ]);

        if ($this->supervisor) {
            $query->whereHas('schedules.office', fn($q) => $q->where('supervisor_name', $this->supervisor));
        }

        $users = $query->get()->groupBy(function ($user) {
            // Menggunakan optional agar tidak error jika user tidak punya jadwal
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
