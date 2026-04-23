<?php

namespace App\Livewire;

use App\Models\{Attendance, Leave, Schedule};
use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class Presensi extends Component
{
    public $latitude, $longitude, $accuracy;
    public $insideRadius = false;
    public $schedule, $attendance, $isLeave = false;
    public $isLoading = false;

    public function mount()
    {
        $this->loadData();
    }

    public function loadData()
    {
        $userId = Auth::id();
        $today = Carbon::today();

        // PERBAIKAN: Gunakan method getActiveSchedule untuk sistem start_date - end_date
        $this->schedule = Schedule::getActiveSchedule($userId, $today);

        $this->attendance = Attendance::where('user_id', $userId)
            ->whereDate('created_at', $today)
            ->first();

        $this->isLeave = Leave::where('user_id', $userId)
            ->where('status', 'approved')
            ->whereDate('start_date', '<=', $today)
            ->whereDate('end_date', '>=', $today)
            ->exists();
    }

    private function getDistance($lat1, $lon1, $lat2, $lon2)
    {
        $earthRadius = 6371000;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) * sin($dLat / 2) +
            cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
            sin($dLon / 2) * sin($dLon / 2);
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        return $earthRadius * $c;
    }

    private function isWithinWorkingHours(): bool
    {
        if (!$this->schedule || !$this->schedule->shift) {
            return false;
        }

        $now = Carbon::now();
        $start = Carbon::parse($this->schedule->shift->start_time);
        return $now->between($start, $start->copy()->addHours(2));
    }

    public function store()
    {
        $this->validate([
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
        ]);

        $this->isLoading = true;
        $this->loadData();

        $userId = Auth::id();
        $now = now();

        if (!$this->schedule) {
            $this->dispatch('alert', ['type' => 'error', 'message' => 'Jadwal kerja tidak ditemukan untuk hari ini!']);
            $this->isLoading = false;
            return;
        }

        if ($this->schedule->is_banned) {
            $this->dispatch('alert', ['type' => 'error', 'message' => 'Akun Anda ditangguhkan. Hubungi admin.']);
            $this->isLoading = false;
            return;
        }

        if ($this->isLeave) {
            $this->dispatch('alert', ['type' => 'error', 'message' => 'Anda sedang dalam masa cuti.']);
            $this->isLoading = false;
            return;
        }

        $distance = $this->getDistance(
            $this->latitude,
            $this->longitude,
            $this->schedule->office->latitude,
            $this->schedule->office->longitude
        );

        if (!$this->schedule->is_wfa && $distance > $this->schedule->office->radius) {
            $this->dispatch('alert', [
                'type' => 'error',
                'message' => 'Di luar radius kantor (' . round($distance) . 'm dari kantor)'
            ]);
            $this->isLoading = false;
            return;
        }

        if (!$this->attendance && !$this->isWithinWorkingHours()) {
            $this->dispatch('alert', [
                'type' => 'error',
                'message' => 'Di luar jam kerja. Absen masuk hanya bisa dilakukan hingga 2 jam setelah jam mulai.'
            ]);
            $this->isLoading = false;
            return;
        }

        try {
            if (!$this->attendance) {
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

                $msg = '✅ Presensi masuk berhasil!';
                $type = 'check_in';
            } else {
                if ($this->attendance->end_time) {
                    $this->dispatch('alert', ['type' => 'error', 'message' => 'Anda sudah melakukan absen keluar hari ini.']);
                    $this->isLoading = false;
                    return;
                }

                if (!$this->attendance->start_time) {
                    $this->dispatch('alert', ['type' => 'error', 'message' => 'Anda belum melakukan absen masuk hari ini.']);
                    $this->isLoading = false;
                    return;
                }

                $this->attendance->update([
                    'end_latitude' => $this->latitude,
                    'end_longitude' => $this->longitude,
                    'end_time' => $now,
                ]);

                $msg = '✅ Presensi keluar berhasil!';
                $type = 'check_out';
            }

            Log::info('Presensi recorded', [
                'user_id' => $userId,
                'type' => $type,
                'latitude' => $this->latitude,
                'longitude' => $this->longitude,
                'distance' => $distance ?? null,
                'is_wfa' => $this->schedule->is_wfa,
                'ip' => request()->ip(),
            ]);

            $this->loadData();
            $this->dispatch('alert', ['type' => 'success', 'message' => $msg]);
            $this->dispatch('presensi-success', ['type' => $type]);

            $this->latitude = null;
            $this->longitude = null;
            $this->accuracy = null;
            $this->insideRadius = false;
        } catch (\Exception $e) {
            Log::error('Presensi error: ' . $e->getMessage(), [
                'user_id' => $userId,
                'trace' => $e->getTraceAsString()
            ]);

            $this->dispatch('alert', ['type' => 'error', 'message' => 'Terjadi kesalahan: ' . $e->getMessage()]);
        } finally {
            $this->isLoading = false;
        }
    }

    public function render()
    {
        return view('livewire.presensi');
    }
}