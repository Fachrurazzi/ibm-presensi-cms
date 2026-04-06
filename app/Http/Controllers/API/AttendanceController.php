<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\{Attendance, Schedule, Leave};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth, Validator};
use Carbon\Carbon;

class AttendanceController extends Controller
{
    /**
     * Standarisasi response JSON agar sinkron dengan DataState di Flutter.
     */
    private function jsonResponse($success, $message, $data = null, $code = 200)
    {
        return response()->json([
            'success' => $success,
            'message' => $message,
            'data'    => $data,
        ], $code);
    }

    /**
     * Transformasi data model ke format yang dibutuhkan Flutter.
     */
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

    /**
     * Hitung jarak Haversine antara user dan kantor.
     */
    private function getDistance($lat1, $lon1, $lat2, $lon2)
    {
        $earthRadius = 6371000; // dalam meter
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) * sin($dLat / 2) +
            cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) * sin($dLon / 2);
        return $earthRadius * (2 * atan2(sqrt($a), sqrt(1 - $a)));
    }

    /**
     * Mendapatkan riwayat bulanan berdasarkan filter.
     */
    public function getAttendanceByMonthAndYear($month, $year)
    {
        // 1. Pastikan input adalah integer untuk menghindari error string "03" vs integer 3
        $month = (int) $month;
        $year  = (int) $year;

        $validator = Validator::make(['month' => $month, 'year' => $year], [
            'month' => 'required|integer|between:1,12',
            'year'  => 'required|integer|min:2020',
        ]);

        if ($validator->fails()) {
            return $this->jsonResponse(false, 'Parameter tidak valid', $validator->errors(), 422);
        }

        // 2. Gunakan query yang lebih stabil dengan format tanggal eksplisit jika perlu
        // Kita gunakan whereRaw atau Carbon untuk memastikan pencarian bulan Maret (03) tepat sasaran
        $attendanceList = Attendance::where('user_id', Auth::id())
            ->whereYear('created_at', $year)
            ->whereMonth('created_at', $month)
            ->orderBy('created_at', 'desc')
            ->get();

        // 3. Mapping data menggunakan transformAttendance yang sudah kamu buat
        $data = $attendanceList->map(function ($item) {
            return $this->transformAttendance($item);
        });

        return $this->jsonResponse(true, "Riwayat kehadiran bulan $month tahun $year berhasil dimuat", $data);
    }

    /**
     * Mendapatkan data absen hari ini dan rekap bulan berjalan untuk HomeScreen.
     */
    public function getAttendanceToday()
    {
        $userId = Auth::id();
        $now = Carbon::now();

        $attendanceToday = Attendance::where('user_id', $userId)
            ->whereDate('created_at', Carbon::today())
            ->first();

        // Lebih singkat dan bersih
        $attendanceThisMonth = Attendance::where('user_id', $userId)
            ->whereBetween('created_at', [
                Carbon::now()->startOfMonth(),
                Carbon::now()->endOfMonth()
            ])
            ->latest()
            ->get()
            ->map(fn($item) => $this->transformAttendance($item));

        return $this->jsonResponse(true, 'Data kehadiran berhasil dimuat', [
            'today'      => $this->transformAttendance($attendanceToday),
            'this_month' => $attendanceThisMonth,
        ]);
    }

    /**
     * Mendapatkan jadwal kerja user (Termasuk pengecekan Cuti & Banned).
     */
    public function getSchedule()
    {
        $userId = Auth::id();
        $today  = Carbon::today()->toDateString();

        // Cek apakah sedang cuti
        $isOnLeave = Leave::where('user_id', $userId)
            ->where('status', 'approved')
            ->whereDate('start_date', '<=', $today)
            ->whereDate('end_date', '>=', $today)
            ->exists();

        if ($isOnLeave) {
            return $this->jsonResponse(false, 'Anda sedang dalam masa cuti.', ['status' => 'on_leave'], 403);
        }

        $schedule = Schedule::with(['office', 'shift'])->where('user_id', $userId)->first();

        if (!$schedule) {
            return $this->jsonResponse(false, 'Jadwal tidak ditemukan.', null, 404);
        }

        if ($schedule->is_banned) {
            return $this->jsonResponse(false, 'Akun ditangguhkan.', ['status' => 'banned'], 403);
        }

        // Sembunyikan field yang tidak perlu dikirim ke Flutter
        $schedule->makeHidden(['created_at', 'updated_at']);
        $schedule->shift?->makeHidden(['created_at', 'updated_at', 'deleted_at']);
        $schedule->office?->makeHidden(['created_at', 'updated_at', 'deleted_at']);

        return $this->jsonResponse(true, 'Berhasil mendapatkan jadwal', $schedule);
    }

    /**
     * Proses Check-in dan Check-out.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric'
        ]);

        if ($validator->fails()) {
            return $this->jsonResponse(false, 'Koordinat GPS diperlukan', $validator->errors(), 422);
        }

        $userId = Auth::id();
        $schedule = Schedule::with(['office', 'shift'])->where('user_id', $userId)->first();

        if (!$schedule || $schedule->is_banned) {
            return $this->jsonResponse(false, 'Akses ditolak atau jadwal tidak ditemukan.', null, 403);
        }

        // Cek Jarak Radius (Jika bukan WFA)
        $distance = $this->getDistance(
            $request->latitude,
            $request->longitude,
            $schedule->office->latitude,
            $schedule->office->longitude
        );

        if (!$schedule->is_wfa && $distance > $schedule->office->radius) {
            return $this->jsonResponse(false, "Di luar radius kantor (" . round($distance) . "m)", null, 403);
        }

        $attendance = Attendance::where('user_id', $userId)
            ->whereDate('created_at', Carbon::today())
            ->first();

        // Logika Check-in
        if (!$attendance) {
            $attendance = Attendance::create([
                'user_id'             => $userId,
                'schedule_id'         => $schedule->id,
                'schedule_latitude'   => $schedule->office->latitude,
                'schedule_longitude'  => $schedule->office->longitude,
                'schedule_start_time' => $schedule->shift->getRawOriginal('start_time'),
                'schedule_end_time'   => $schedule->shift->getRawOriginal('end_time'),
                'start_latitude'      => $request->latitude,
                'start_longitude'     => $request->longitude,
                'start_time'          => Carbon::now(),
            ]);
            return $this->jsonResponse(true, 'Berhasil Check-in', $this->transformAttendance($attendance));
        }

        // Logika Check-out
        if ($attendance->end_time) {
            return $this->jsonResponse(false, 'Anda sudah melakukan absen pulang hari ini.', null, 422);
        }

        $attendance->update([
            'end_latitude'  => $request->latitude,
            'end_longitude' => $request->longitude,
            'end_time'      => Carbon::now()
        ]);

        return $this->jsonResponse(true, 'Berhasil Check-out', $this->transformAttendance($attendance));
    }

    /**
     * Fitur Auto-Banned jika terdeteksi fraud.
     */
    public function banned(Request $request)
    {
        $user = Auth::user();
        $schedule = Schedule::where('user_id', $user->id)->first();

        if (!$schedule) {
            return $this->jsonResponse(false, 'Data jadwal tidak ditemukan', null, 404);
        }

        // Simpan alasan ban jika dikirim dari Flutter
        $reason = $request->input('reason', 'Terdeteksi Emulator/Fraud');

        $schedule->update([
            'is_banned' => true,
            // 'banned_reason' => $reason // Opsional: jika kamu punya kolomnya
        ]);

        // Opsional: Cabut semua token login agar dia langsung logout paksa di semua device
        $user->tokens()->delete();

        return $this->jsonResponse(true, "Akun Anda telah ditangguhkan otomatis karena terdeteksi penyalahgunaan aplikasi.");
    }
}
