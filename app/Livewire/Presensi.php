<?php

namespace App\Livewire;

use App\Models\{Attendance, Leave, Schedule};
use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class Presensi extends Component
{
    public $latitude, $longitude, $accuracy;
    public $insideRadius = false;
    public $schedule, $attendance;

    public function mount()
    {
        $this->loadData();
    }

    public function loadData()
    {
        $userId = Auth::id();
        // Memastikan schedule memuat relasi yang diperlukan
        $this->schedule = Schedule::with(['office', 'shift'])
            ->where('user_id', $userId)
            ->first();

        $this->attendance = Attendance::where('user_id', $userId)
            ->whereDate('created_at', Carbon::today())
            ->first();
    }

    // Haversine Formula untuk kalkulasi jarak di server side
    private function getDistance($lat1, $lon1, $lat2, $lon2)
    {
        $earthRadius = 6371000; // Meter
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) * sin($dLat / 2) +
            cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) * sin($dLon / 2);
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        return $earthRadius * $c;
    }

    public function store()
    {
        $this->validate([
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
        ]);

        $this->loadData();
        $userId = Auth::id();
        $now = now();

        if (!$this->schedule) {
            $this->dispatch('alert', ['type' => 'error', 'message' => 'Jadwal kerja tidak ditemukan!']);
            return;
        }

        // 1. Double Check Radius di Server
        $distance = $this->getDistance(
            $this->latitude,
            $this->longitude,
            $this->schedule->office->latitude,
            $this->schedule->office->longitude
        );

        if (!$this->schedule->is_wfa && $distance > $this->schedule->office->radius) {
            $this->dispatch('alert', ['type' => 'error', 'message' => 'Gagal: Anda berada diluar radius kantor.']);
            $this->insideRadius = false;
            return;
        }

        // 2. Eksekusi Presensi
        try {
            if (!$this->attendance) {
                // Proses Masuk
                Attendance::create([
                    'user_id' => $userId,
                    'schedule_id' => $this->schedule->id,
                    'schedule_latitude' => $this->schedule->office->latitude,
                    'schedule_longitude' => $this->schedule->office->longitude,
                    'schedule_start_time' => $this->schedule->shift->start_time,
                    'schedule_end_time' => $this->schedule->shift->end_time,
                    'start_latitude' => $this->latitude,
                    'start_longitude' => $this->longitude,
                    'start_time' => $now,
                ]);
                $msg = 'Presensi masuk berhasil dicatat.';
            } else {
                // Proses Keluar (Jika belum absen keluar)
                if ($this->attendance->end_time) {
                    $this->dispatch('alert', ['type' => 'error', 'message' => 'Anda sudah melakukan absen keluar hari ini.']);
                    return;
                }

                $this->attendance->update([
                    'end_latitude' => $this->latitude,
                    'end_longitude' => $this->longitude,
                    'end_time' => $now,
                ]);
                $msg = 'Presensi keluar berhasil dicatat.';
            }

            $this->dispatch('alert', ['type' => 'success', 'message' => $msg]);

            // Redirect setelah sukses agar data ter-refresh
            return redirect('admin/attendances');
        } catch (\Exception $e) {
            $this->dispatch('alert', ['type' => 'error', 'message' => 'Terjadi kesalahan: ' . $e->getMessage()]);
        }
    }

    public function render()
    {
        return view('livewire.presensi');
    }
}
