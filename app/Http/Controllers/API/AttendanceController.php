<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\{Attendance, Schedule, Leave, AttendancePermission};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth, Validator, Log};
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;

class AttendanceController extends Controller
{
    /**
     * Maksimal jam check-in setelah jam mulai (configurable)
     */
    private const MAX_CHECKIN_HOURS = 2;

    /**
     * Response formatter yang konsisten.
     */
    private function jsonResponse(
        bool $success,
        string $message,
        mixed $data = null,
        int $code = 200
    ): JsonResponse {
        return response()->json([
            'success' => $success,
            'message' => $message,
            'data'    => $data,
        ], $code);
    }

    /**
     * Transform attendance data untuk response.
     */
    private function transformAttendance($attendance): ?array
    {
        if (!$attendance) return null;

        return [
            'id'                => $attendance->id,
            'date'              => $attendance->start_time->toIso8601String(),
            'is_late'           => $attendance->is_late_bool,
            'has_permission'    => !is_null($attendance->attendance_permission_id),
            'start_time'        => $attendance->start_time_format,
            'end_time'          => $attendance->end_time_format,
            'schedule_start'    => $attendance->schedule_start_time?->format('H:i'),
            'schedule_end'      => $attendance->schedule_end_time?->format('H:i'),
            'lunch_money'       => str_contains($attendance->lunch_money_label, '15.000') ? 15000 : 0,
            'lunch_money_label' => $attendance->lunch_money_label,
            'work_duration'     => $attendance->work_duration_text,
        ];
    }

    /**
     * GET /attendance/today
     */
    public function getAttendanceToday(): JsonResponse
    {
        try {
            $userId = Auth::id();
            $attendanceToday = Attendance::with('permission')
                ->where('user_id', $userId)
                ->whereDate('start_time', Carbon::today())
                ->first();

            return $this->jsonResponse(
                true,
                'Data hari ini berhasil dimuat',
                $this->transformAttendance($attendanceToday)
            );
        } catch (\Exception $e) {
            Log::error('Get attendance today error: ' . $e->getMessage());
            return $this->jsonResponse(false, 'Gagal memuat data hari ini', null, 500);
        }
    }

    /**
     * GET /attendance/history?month=4&year=2026&per_page=10
     */
    public function history(Request $request): JsonResponse
    {
        try {
            // Validasi input
            $month = max(1, min(12, (int) $request->query('month', now()->month)));
            $year = max(2020, (int) $request->query('year', now()->year));
            $perPage = max(1, min(50, (int) $request->query('per_page', 10)));

            $query = Attendance::with('permission')
                ->where('user_id', Auth::id())
                ->whereYear('start_time', $year)
                ->whereMonth('start_time', $month)
                ->orderBy('start_time', 'desc');

            $attendances = $query->paginate($perPage);

            $data = $attendances->map(fn($item) => $this->transformAttendance($item));

            return $this->jsonResponse(true, "Riwayat berhasil dimuat", [
                'data' => $data,
                'meta' => [
                    'current_page' => $attendances->currentPage(),
                    'per_page'     => $attendances->perPage(),
                    'total'        => $attendances->total(),
                    'last_page'    => $attendances->lastPage(),
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Attendance history error: ' . $e->getMessage());
            return $this->jsonResponse(false, 'Gagal memuat riwayat', null, 500);
        }
    }

    /**
     * GET /attendance/schedule
     */
    public function getSchedule(): JsonResponse
    {
        try {
            $userId = Auth::id();
            $today = Carbon::today();

            $schedule = Schedule::getActiveSchedule($userId, $today);

            if (!$schedule) {
                return $this->jsonResponse(false, 'Jadwal kerja tidak ditemukan untuk hari ini.', null, 404);
            }

            $isOnLeave = Leave::where('user_id', $userId)
                ->where('status', 'APPROVED')
                ->whereDate('start_date', '<=', $today)
                ->whereDate('end_date', '>=', $today)
                ->exists();

            $data = [
                'id' => $schedule->id,
                'office' => [
                    'id'        => $schedule->office->id,
                    'name'      => $schedule->office->name,
                    'latitude'  => $schedule->office->latitude,
                    'longitude' => $schedule->office->longitude,
                    'radius'    => $schedule->office->radius,
                ],
                'shift' => [
                    'id'         => $schedule->shift->id,
                    'name'       => $schedule->shift->name,
                    'start_time' => $schedule->shift->start_time_display,
                    'end_time'   => $schedule->shift->end_time_display,
                ],
                'is_wfa'      => $schedule->is_wfa,
                'is_banned'   => $schedule->is_banned,
                'is_on_leave' => $isOnLeave,
            ];

            return $this->jsonResponse(true, 'Berhasil mendapatkan jadwal', $data);
        } catch (\Exception $e) {
            Log::error('Get schedule error: ' . $e->getMessage());
            return $this->jsonResponse(false, 'Gagal mendapatkan jadwal', null, 500);
        }
    }

    /**
     * POST /attendance - Check-in / Check-out
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'latitude'  => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
        ]);

        if ($validator->fails()) {
            return $this->jsonResponse(false, 'Koordinat GPS diperlukan', $validator->errors(), 422);
        }

        try {
            $userId = Auth::id();
            $today = Carbon::today();
            $now = Carbon::now();

            // 1. Dapatkan jadwal aktif hari ini
            $schedule = Schedule::getActiveSchedule($userId, $today);
            if (!$schedule || $schedule->is_banned) {
                return $this->jsonResponse(false, 'Jadwal tidak ditemukan atau akun ditangguhkan.', null, 403);
            }

            // 2. Cek apakah sedang cuti
            $isOnLeave = Leave::where('user_id', $userId)
                ->where('status', 'APPROVED')
                ->whereDate('start_date', '<=', $today)
                ->whereDate('end_date', '>=', $today)
                ->exists();

            if ($isOnLeave) {
                return $this->jsonResponse(false, 'Anda sedang dalam masa cuti, tidak dapat melakukan absen.', null, 403);
            }

            // 3. Cek apakah sudah absen hari ini
            $attendance = Attendance::where('user_id', $userId)
                ->whereDate('start_time', $today)
                ->first();

            // 4. Validasi radius (kecuali WFA)
            $distance = $schedule->office->calculateDistance($request->latitude, $request->longitude);

            if (!$schedule->is_wfa && $distance > $schedule->office->radius) {
                return $this->jsonResponse(false, "Di luar radius kantor (" . round($distance) . "m)", null, 403);
            }

            // 5. Jika belum check-in
            if (!$attendance) {
                $startTime = Carbon::parse($schedule->shift->start_time);

                // Batas check-in: maksimal MAX_CHECKIN_HOURS jam setelah jam mulai
                if ($now->gt($startTime->copy()->addHours(self::MAX_CHECKIN_HOURS))) {
                    return $this->jsonResponse(
                        false,
                        "Di luar jam kerja. Absen masuk hanya bisa dilakukan hingga " . self::MAX_CHECKIN_HOURS . " jam setelah jam mulai.",
                        null,
                        422
                    );
                }

                $permission = AttendancePermission::where('user_id', $userId)
                    ->whereDate('date', $today)
                    ->where('status', 'APPROVED')
                    ->first();

                $attendance = Attendance::create([
                    'user_id'                  => $userId,
                    'schedule_id'              => $schedule->id,
                    'attendance_permission_id' => $permission?->id,
                    'schedule_latitude'        => $schedule->office->latitude,
                    'schedule_longitude'       => $schedule->office->longitude,
                    'schedule_start_time'      => $schedule->shift->start_time,
                    'schedule_end_time'        => $schedule->shift->end_time,
                    'start_latitude'           => $request->latitude,
                    'start_longitude'          => $request->longitude,
                    'start_time'               => $now,
                ]);

                Log::info('Check-in', [
                    'user_id'       => $userId,
                    'attendance_id' => $attendance->id,
                    'latitude'      => $request->latitude,
                    'longitude'     => $request->longitude,
                ]);

                return $this->jsonResponse(true, 'Berhasil Check-in', $this->transformAttendance($attendance));
            }

            // 6. Jika sudah check-in, lakukan check-out
            if ($attendance->end_time) {
                return $this->jsonResponse(false, 'Anda sudah absen pulang hari ini.', null, 422);
            }

            // Validasi jam pulang (kecuali ada izin EARLY_LEAVE)
            $endTime = Carbon::parse($schedule->shift->end_time);
            $hasEarlyLeavePermission = AttendancePermission::where('user_id', $userId)
                ->whereDate('date', $today)
                ->where('status', 'APPROVED')
                ->where('type', 'EARLY_LEAVE')
                ->exists();

            if (!$hasEarlyLeavePermission && $now->lt($endTime)) {
                return $this->jsonResponse(
                    false,
                    "Belum waktunya pulang. Jam pulang: {$endTime->format('H:i')}",
                    null,
                    422
                );
            }

            $attendance->update([
                'end_latitude'  => $request->latitude,
                'end_longitude' => $request->longitude,
                'end_time'      => $now,
            ]);

            Log::info('Check-out', [
                'user_id'       => $userId,
                'attendance_id' => $attendance->id,
                'latitude'      => $request->latitude,
                'longitude'     => $request->longitude,
            ]);

            return $this->jsonResponse(true, 'Berhasil Check-out', $this->transformAttendance($attendance));
        } catch (\Exception $e) {
            Log::error('Attendance store error: ' . $e->getMessage(), [
                'user_id' => Auth::id(),
                'trace'   => $e->getTraceAsString(),
            ]);
            return $this->jsonResponse(false, 'Terjadi kesalahan sistem', null, 500);
        }
    }

    /**
     * GET /attendance/summary?month=4&year=2026
     */
    public function summary(Request $request): JsonResponse
    {
        try {
            $month = max(1, min(12, (int) $request->query('month', now()->month)));
            $year = max(2020, (int) $request->query('year', now()->year));
            $userId = Auth::id();

            $startDate = Carbon::createFromDate($year, $month, 1)->startOfMonth();
            $endDate = $startDate->copy()->endOfMonth();

            $attendances = Attendance::where('user_id', $userId)
                ->whereBetween('start_time', [$startDate, $endDate])
                ->get();

            $totalWorkingDays = $this->countWorkingDaysInMonth($userId, $year, $month);
            $presentDays = $attendances->count();
            $lateDays = $attendances->filter(fn($a) => $a->is_late_bool)->count();

            return $this->jsonResponse(true, 'Ringkasan bulanan berhasil diambil', [
                'month'              => $month,
                'year'               => $year,
                'month_name'         => $startDate->translatedFormat('F Y'),
                'total_working_days' => $totalWorkingDays,
                'present_days'       => $presentDays,
                'late_days'          => $lateDays,
                'absent_days'        => max(0, $totalWorkingDays - $presentDays),
                'attendance_rate'    => $totalWorkingDays > 0
                    ? round(($presentDays / $totalWorkingDays) * 100, 1)
                    : 0,
            ]);
        } catch (\Exception $e) {
            Log::error('Attendance summary error: ' . $e->getMessage());
            return $this->jsonResponse(false, 'Gagal memuat ringkasan', null, 500);
        }
    }

    /**
     * POST /attendance/report-suspicious
     */
    public function reportSuspiciousActivity(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $today = Carbon::today();

            $schedule = Schedule::getActiveSchedule($user->id, $today);

            if (!$schedule) {
                return $this->jsonResponse(false, 'Jadwal tidak ditemukan.', null, 404);
            }

            if ($schedule->is_banned) {
                return $this->jsonResponse(false, 'Akun sudah dalam status ditangguhkan.', null, 422);
            }

            $reason = $request->input('reason', 'Terdeteksi menggunakan fake GPS/emulator');

            $schedule->update([
                'is_banned'     => true,
                'banned_reason' => $reason,
            ]);

            // Revoke semua token user
            $user->tokens()->delete();

            Log::alert("SUSPICIOUS: User {$user->name} (ID: {$user->id}) reported for fake GPS", [
                'user_id' => $user->id,
                'reason'  => $reason,
                'ip'      => $request->ip(),
            ]);

            return $this->jsonResponse(true, 'Laporan diterima. Akun telah ditangguhkan.', [
                'banned' => true,
            ]);
        } catch (\Exception $e) {
            Log::error('Report suspicious error: ' . $e->getMessage());
            return $this->jsonResponse(false, 'Gagal memproses laporan', null, 500);
        }
    }

    /**
     * Hitung jumlah hari kerja dalam satu bulan berdasarkan schedule user.
     */
    private function countWorkingDaysInMonth(int $userId, int $year, int $month): int
    {
        try {
            $startDate = Carbon::createFromDate($year, $month, 1)->startOfMonth();
            $endDate = $startDate->copy()->endOfMonth();

            $schedules = Schedule::where('user_id', $userId)
                ->where('is_banned', false)
                ->where('start_date', '<=', $endDate)
                ->where(function ($q) use ($startDate) {
                    $q->whereNull('end_date')->orWhere('end_date', '>=', $startDate);
                })
                ->get();

            if ($schedules->isEmpty()) {
                // Fallback: hitung hari Senin-Jumat
                return $this->countWeekdaysInMonth($year, $month);
            }

            $workingDays = 0;
            $current = $startDate->copy();

            while ($current->lte($endDate)) {
                $dateStr = $current->toDateString();

                $hasSchedule = $schedules->contains(function ($schedule) use ($dateStr) {
                    return $schedule->start_date->toDateString() <= $dateStr
                        && (is_null($schedule->end_date) || $schedule->end_date->toDateString() >= $dateStr);
                });

                if ($hasSchedule) {
                    $workingDays++;
                }

                $current->addDay();
            }

            return $workingDays;
        } catch (\Exception $e) {
            Log::error('Count working days error: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Fallback: Hitung hari kerja (Senin-Jumat) dalam sebulan.
     */
    private function countWeekdaysInMonth(int $year, int $month): int
    {
        $startDate = Carbon::createFromDate($year, $month, 1)->startOfMonth();
        $endDate = $startDate->copy()->endOfMonth();

        $weekdays = 0;
        $current = $startDate->copy();

        while ($current->lte($endDate)) {
            if ($current->isWeekday()) {
                $weekdays++;
            }
            $current->addDay();
        }

        return $weekdays;
    }
}
