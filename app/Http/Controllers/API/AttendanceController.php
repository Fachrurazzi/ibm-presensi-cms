<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\{Attendance, Schedule, Leave};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth, Validator, Log};
use Carbon\Carbon;

class AttendanceController extends Controller
{
    private function jsonResponse($success, $message, $data = null, $code = 200)
    {
        return response()->json([
            'success' => $success,
            'message' => $message,
            'data'    => $data,
        ], $code);
    }

    private function transformAttendance($attendance)
    {
        if (!$attendance) return null;

        $isLate = $attendance->isLate();

        return [
            'id'                => $attendance->id,
            'date'              => $attendance->created_at->toIso8601String(),
            'is_late'           => $isLate,
            'start_time'        => $attendance->start_time?->format('H:i') ?? '--:--',
            'end_time'          => $attendance->end_time?->format('H:i') ?? '--:--',
            'schedule_start'    => Carbon::parse($attendance->schedule_start_time)->format('H:i'),
            'schedule_end'      => Carbon::parse($attendance->schedule_end_time)->format('H:i'),
            'lunch_money'       => $isLate ? 0 : 15000,
            'lunch_money_label' => $isLate ? 'Rp 0 (Terlambat)' : 'Rp 15.000',
        ];
    }

    public function getAttendanceToday()
    {
        $userId = Auth::id();
        $attendanceToday = Attendance::where('user_id', $userId)
            ->whereDate('created_at', Carbon::today())
            ->first();

        return $this->jsonResponse(true, 'Data hari ini berhasil dimuat', $this->transformAttendance($attendanceToday));
    }

    public function history($month, $year)
    {
        $attendanceList = Attendance::where('user_id', Auth::id())
            ->whereYear('created_at', $year)
            ->whereMonth('created_at', $month)
            ->orderBy('created_at', 'desc')
            ->get();

        $data = $attendanceList->map(fn($item) => $this->transformAttendance($item));
        return $this->jsonResponse(true, "Riwayat berhasil dimuat", $data);
    }

    /**
     * REVISI UTAMA: Load User & Position di sini
     */
    public function getSchedule()
    {
        $userId = Auth::id();
        $today  = Carbon::today()->toDateString();

        // 1. Ambil data Schedule terlebih dahulu untuk mendapatkan data User-nya
        $schedule = Schedule::with(['office', 'shift', 'user.position'])
            ->where('user_id', $userId)
            ->first();

        if (!$schedule) {
            return $this->jsonResponse(false, 'Jadwal belum diatur.', null, 404);
        }

        // 2. Cek status cuti
        $isOnLeave = Leave::where('user_id', $userId)
            ->where('status', 'approved')
            ->whereDate('start_date', '<=', $today)
            ->whereDate('end_date', '>=', $today)
            ->exists();

        if ($isOnLeave) {
            // REVISI KUNCI: Gunakan Status 200 (Success) agar Flutter bisa memparsing data
            // Dan sertakan object $schedule agar data user (9 hari cuti) ikut terkirim
            return $this->jsonResponse(true, 'Anda sedang dalam masa cuti.', $schedule);
        }

        // 3. Normal logic untuk merapikan string jam
        if ($schedule->shift) {
            $schedule->shift->start_time = trim($schedule->shift->getRawOriginal('start_time'));
            $schedule->shift->end_time = trim($schedule->shift->getRawOriginal('end_time'));
        }

        return $this->jsonResponse(true, 'Berhasil mendapatkan jadwal', $schedule);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric'
        ]);

        if ($validator->fails()) {
            return $this->jsonResponse(false, 'Koordinat GPS diperlukan', null, 422);
        }

        $userId = Auth::id();
        // Pastikan relasi juga diload saat store untuk validasi lokasi
        $schedule = Schedule::with(['office', 'shift'])->where('user_id', $userId)->first();

        if (!$schedule || $schedule->is_banned) {
            return $this->jsonResponse(false, 'Jadwal tidak aktif.', null, 403);
        }

        $attendance = Attendance::where('user_id', $userId)
            ->whereDate('created_at', Carbon::today())
            ->first();

        $distance = $this->getDistance($request->latitude, $request->longitude, $schedule->office->latitude, $schedule->office->longitude);

        if (!$schedule->is_wfa && $distance > $schedule->office->radius) {
            return $this->jsonResponse(false, "Di luar radius kantor (" . round($distance) . "m)", null, 403);
        }

        if (!$attendance) {
            $attendance = Attendance::create([
                'user_id'             => $userId,
                'schedule_id'         => $schedule->id,
                'schedule_latitude'   => $schedule->office->latitude,
                'schedule_longitude'  => $schedule->office->longitude,
                'schedule_start_time' => trim($schedule->shift->getRawOriginal('start_time')),
                'schedule_end_time'   => trim($schedule->shift->getRawOriginal('end_time')),
                'start_latitude'      => $request->latitude,
                'start_longitude'     => $request->longitude,
                'start_time'          => Carbon::now(),
            ]);
            return $this->jsonResponse(true, 'Berhasil Check-in', $this->transformAttendance($attendance));
        }

        if ($attendance->end_time) {
            return $this->jsonResponse(false, 'Anda sudah absen pulang.', null, 422);
        }

        $attendance->update([
            'end_latitude'  => $request->latitude,
            'end_longitude' => $request->longitude,
            'end_time'      => Carbon::now()
        ]);

        return $this->jsonResponse(true, 'Berhasil Check-out', $this->transformAttendance($attendance));
    }

    private function getDistance($lat1, $lon1, $lat2, $lon2)
    {
        $earthRadius = 6371000;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) * sin($dLat / 2) + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) * sin($dLon / 2);
        return $earthRadius * (2 * atan2(sqrt($a), sqrt(1 - $a)));
    }

    public function banned(Request $request)
    {
        $user = Auth::user();
        $schedule = Schedule::where('user_id', $user->id)->first();
        if ($schedule) {
            $schedule->update(['is_banned' => true]);
            $user->tokens()->delete();
            return $this->jsonResponse(true, "Akun ditangguhkan otomatis.");
        }
        return $this->jsonResponse(false, 'Gagal update', null, 404);
    }
}
