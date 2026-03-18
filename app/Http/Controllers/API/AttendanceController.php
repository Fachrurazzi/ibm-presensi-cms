<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\{Attendance, Schedule, Leave};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth, Validator};
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
        return [
            'id'                => $attendance->id,
            'date'              => $attendance->created_at->toIso8601String(),
            'is_late'           => $attendance->isLate(),
            'start_time'        => $attendance->start_time?->format('H:i') ?? '--:--',
            'end_time'          => $attendance->end_time?->format('H:i') ?? '--:--',
            'schedule_start'    => date('H:i', strtotime($attendance->schedule_start_time)),
            'schedule_end'      => date('H:i', strtotime($attendance->schedule_end_time)),
            'lunch_money'       => $attendance->isLate() ? 0 : 15000,
            'lunch_money_label' => $attendance->isLate() ? 'Rp 0 (Terlambat)' : 'Rp 15.000',
        ];
    }

    private function getDistance($lat1, $lon1, $lat2, $lon2)
    {
        $earthRadius = 6371000;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) * sin($dLat / 2) + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) * sin($dLon / 2);
        return $earthRadius * (2 * atan2(sqrt($a), sqrt(1 - $a)));
    }

    // --- FUNGSI RIWAYAT BULANAN (YANG SEMPAT HILANG) ---
    public function getAttendanceByMonthAndYear($month, $year)
    {
        $validator = Validator::make(['month' => $month, 'year' => $year], [
            'month' => 'required|integer|between:1,12',
            'year'  => 'required|integer|min:2020',
        ]);

        if ($validator->fails()) {
            return $this->jsonResponse(false, 'Parameter tidak valid', $validator->errors(), 422);
        }

        $userId = Auth::id();
        $attendanceList = Attendance::where('user_id', $userId)
            ->whereMonth('created_at', $month)
            ->whereYear('created_at', $year)
            ->latest()
            ->get()
            ->map(fn($item) => $this->transformAttendance($item));

        return $this->jsonResponse(true, 'Riwayat kehadiran berhasil dimuat', $attendanceList);
    }

    public function getAttendanceToday()
    {
        $userId = Auth::id();
        $today  = Carbon::today()->toDateString();

        $attendanceToday = Attendance::where('user_id', $userId)->whereDate('created_at', $today)->first();
        $attendanceThisMonth = Attendance::where('user_id', $userId)
            ->whereMonth('created_at', Carbon::now()->month)
            ->whereYear('created_at', Carbon::now()->year)
            ->latest()->get()->map(fn($item) => $this->transformAttendance($item));

        return $this->jsonResponse(true, 'Data kehadiran berhasil dimuat', [
            'today'      => $this->transformAttendance($attendanceToday),
            'this_month' => $attendanceThisMonth,
        ]);
    }

    public function getSchedule()
    {
        $userId = Auth::id();
        $today  = Carbon::today()->toDateString();

        $isOnLeave = Leave::where('user_id', $userId)->where('status', 'approved')
            ->whereDate('start_date', '<=', $today)->whereDate('end_date', '>=', $today)->exists();

        if ($isOnLeave) {
            return $this->jsonResponse(false, 'Anda sedang dalam masa cuti.', ['status' => 'on_leave'], 403);
        }

        $schedule = Schedule::with(['office', 'shift'])->where('user_id', $userId)->first();
        if (!$schedule) return $this->jsonResponse(false, 'Jadwal tidak ditemukan.', null, 404);
        if ($schedule->is_banned) return $this->jsonResponse(false, 'Akun ditangguhkan.', ['status' => 'banned'], 403);

        if ($schedule->shift) {
            $schedule->shift->makeHidden(['created_at', 'updated_at', 'deleted_at']);
        }
        if ($schedule->office) {
            $schedule->office->makeHidden(['created_at', 'updated_at', 'deleted_at']);
        }
        $schedule->makeHidden(['created_at', 'updated_at']);

        return $this->jsonResponse(true, 'Berhasil mendapatkan jadwal', $schedule);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), ['latitude' => 'required|numeric', 'longitude' => 'required|numeric']);
        if ($validator->fails()) return $this->jsonResponse(false, 'Koordinat GPS diperlukan', $validator->errors(), 422);

        $userId = Auth::id();
        $today  = Carbon::today()->toDateString();

        $schedule = Schedule::with(['office', 'shift'])->where('user_id', $userId)->first();
        if (!$schedule || $schedule->is_banned) return $this->jsonResponse(false, 'Akses ditolak.', null, 403);

        $distance = $this->getDistance($request->latitude, $request->longitude, $schedule->office->latitude, $schedule->office->longitude);
        if (!$schedule->is_wfa && $distance > $schedule->office->radius) {
            return $this->jsonResponse(false, "Di luar radius kantor (" . round($distance) . "m)", null, 403);
        }

        $attendance = Attendance::where('user_id', $userId)->whereDate('created_at', $today)->first();

        if (!$attendance) {
            $attendance = Attendance::create([
                'user_id' => $userId,
                'schedule_id' => $schedule->id,
                'schedule_latitude' => $schedule->office->latitude,
                'schedule_longitude' => $schedule->office->longitude,
                'schedule_start_time' => $schedule->shift->getRawOriginal('start_time'),
                'schedule_end_time' => $schedule->shift->getRawOriginal('end_time'),
                'start_latitude' => $request->latitude,
                'start_longitude' => $request->longitude,
                'start_time' => Carbon::now(),
            ]);
            return $this->jsonResponse(true, 'Berhasil Check-in', $this->transformAttendance($attendance));
        }

        if ($attendance->end_time) return $this->jsonResponse(false, 'Sudah absen pulang.', null, 422);

        $attendance->update([
            'end_latitude' => $request->latitude,
            'end_longitude' => $request->longitude,
            'end_time' => Carbon::now()
        ]);
        return $this->jsonResponse(true, 'Berhasil Check-out', $this->transformAttendance($attendance));
    }

    public function banned(Request $request)
    {
        // 1. Ambil ID user yang sedang aktif melakukan aksi
        $userId = Auth::id();

        // 2. Cari jadwal user tersebut
        $schedule = Schedule::where('user_id', $userId)->first();

        // 3. Cek apakah jadwal ada
        if (!$schedule) {
            return $this->jsonResponse(false, 'Data jadwal tidak ditemukan', null, 404);
        }

        // 4. Eksekusi Banned Otomatis
        $schedule->update([
            'is_banned' => true
        ]);

        // 5. Catat alasan (Optional: bisa ditambahkan kolom banned_reason di DB nanti)
        return $this->jsonResponse(true, "Akun Anda telah ditangguhkan otomatis karena terdeteksi penyalahgunaan aplikasi.");
    }

    public function getImage()
    {
        return $this->jsonResponse(true, 'Berhasil', ['image_url' => Auth::user()->image_url]);
    }
}
