<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Leave;
use App\Models\AttendancePermission;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class DashboardController extends Controller
{
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
     * GET /dashboard/stats - Get dashboard statistics
     */
    public function stats(): JsonResponse
    {
        try {
            $user = auth()->user();

            if (!$user) {
                return $this->jsonResponse(false, 'User tidak ditemukan', null, 401);
            }

            $today = Carbon::today();
            $startOfMonth = Carbon::now()->startOfMonth();

            // Total hadir bulan ini
            $hadirBulanIni = Attendance::where('user_id', $user->id)
                ->whereBetween('start_time', [
                    $startOfMonth->startOfDay()->toDateTimeString(),
                    $today->endOfDay()->toDateTimeString()
                ])
                ->count();

            // Total hari kerja dari awal bulan sampai hari ini
            $totalHariKerja = $this->countWorkingDays($startOfMonth, $today);

            // Persentase kehadiran
            $persentase = $totalHariKerja > 0
                ? round(($hadirBulanIni / $totalHariKerja) * 100, 1)
                : 0;

            // Total terlambat bulan ini
            $terlambat = Attendance::where('user_id', $user->id)
                ->whereBetween('start_time', [
                    $startOfMonth->startOfDay()->toDateTimeString(),
                    $today->endOfDay()->toDateTimeString()
                ])
                ->whereNotNull('start_time')
                ->whereNotNull('schedule_start_time')
                ->whereRaw('start_time > schedule_start_time')
                ->count();

            // Attendance hari ini
            $attendanceToday = Attendance::where('user_id', $user->id)
                ->whereDate('start_time', $today)
                ->first();

            // Pending permissions
            $pendingPermissions = AttendancePermission::where('user_id', $user->id)
                ->where('status', 'PENDING')
                ->count();

            // Pending leaves
            $pendingLeaves = Leave::where('user_id', $user->id)
                ->where('status', 'PENDING')
                ->count();

            // Sisa cuti
            $sisaCuti = $user->getRemainingLeaveQuota();

            // Data attendance hari ini
            $attendanceTodayData = null;
            if ($attendanceToday) {
                $attendanceTodayData = [
                    'start_time'      => $attendanceToday->start_time_format,
                    'end_time'        => $attendanceToday->end_time_format,
                    'is_late'         => $attendanceToday->is_late_bool,
                    'work_duration'   => $attendanceToday->work_duration_text,
                    'lunch_money'     => $attendanceToday->lunch_money_label,
                    'has_checked_out' => !is_null($attendanceToday->end_time),
                ];
            }

            // Schedule hari ini
            $scheduleToday = $attendanceToday?->schedule;
            $scheduleData = null;
            if ($scheduleToday) {
                $scheduleData = [
                    'start_time' => $scheduleToday->start_time?->format('H:i'),
                    'end_time'   => $scheduleToday->end_time?->format('H:i'),
                    'location'   => $scheduleToday->location_name ?? 'Kantor',
                ];
            }

            // Today info
            $todayInfo = [
                'date'           => $today->toDateString(),
                'day_name'       => $today->translatedFormat('l'),
                'is_working_day' => $today->isWeekday(),
                'has_schedule'   => !is_null($scheduleToday),
                'is_holiday'     => false,
            ];

            return $this->jsonResponse(true, 'Data dashboard berhasil diambil', [
                'user' => [
                    'id'            => $user->id,
                    'name'          => $user->name,
                    'email'         => $user->email,
                    'position'      => $user->position?->name,
                    'avatar_url'    => $user->avatar_url,
                    'join_date'     => $user->join_date?->format('Y-m-d'),
                ],
                'today_info' => $todayInfo,
                'stats' => [
                    'hadir_bulan_ini'      => $hadirBulanIni,
                    'total_hari_kerja'     => $totalHariKerja,
                    'persentase_kehadiran' => $persentase,
                    'sisa_cuti'            => $sisaCuti,
                    'total_cuti'           => $user->leave_quota ?? 12,
                    'terlambat_bulan_ini'  => $terlambat,
                    'total_izin_pending'   => $pendingPermissions,
                    'total_cuti_pending'   => $pendingLeaves,
                ],
                'attendance_today' => $attendanceTodayData,
                'schedule_today'   => $scheduleData,
                'can_check_in'     => is_null($attendanceToday),
                'can_check_out'    => $attendanceToday && is_null($attendanceToday->end_time),
            ]);
        } catch (\Exception $e) {
            Log::error('Dashboard error: ' . $e->getMessage(), [
                'user_id' => auth()->id(),
                'file'    => $e->getFile(),
                'line'    => $e->getLine(),
            ]);

            return $this->jsonResponse(false, 'Terjadi kesalahan sistem', null, 500);
        }
    }

    /**
     * GET /dashboard/monthly-summary - Get monthly attendance summary
     */
    public function monthlySummary(Request $request): JsonResponse
    {
        try {
            $user = auth()->user();

            if (!$user) {
                return $this->jsonResponse(false, 'User tidak ditemukan', null, 401);
            }

            // Validasi input month dan year
            $month = max(1, min(12, (int) $request->query('month', now()->month)));
            $year = max(2020, (int) $request->query('year', now()->year));

            $startDate = Carbon::createFromDate($year, $month, 1)->startOfMonth();
            $endDate = $startDate->copy()->endOfMonth();

            // Get attendances for the month
            $attendances = Attendance::where('user_id', $user->id)
                ->whereBetween('start_time', [
                    $startDate->startOfDay()->toDateTimeString(),
                    $endDate->endOfDay()->toDateTimeString()
                ])
                ->get();

            // Hitung total hari kerja dalam sebulan
            $totalWorkingDays = $this->countWorkingDaysInMonth($user->id, $year, $month);

            // Hitung statistik
            $presentDays = $attendances->count();
            $lateDays = $attendances->filter(fn($a) => $a->is_late_bool)->count();
            $absentDays = max(0, $totalWorkingDays - $presentDays);
            $attendanceRate = $totalWorkingDays > 0
                ? round(($presentDays / $totalWorkingDays) * 100, 1)
                : 0;

            // Generate calendar data
            $calendar = [];
            $current = $startDate->copy();

            while ($current->lte($endDate)) {
                if ($current->isWeekday()) {
                    $attendance = $attendances->firstWhere('date', $current->toDateString());
                    $calendar[] = [
                        'date'       => $current->toDateString(),
                        'day'        => $current->format('d'),
                        'day_name'   => $current->translatedFormat('D'),
                        'status'     => $attendance ? 'present' : 'absent',
                        'is_late'    => $attendance?->is_late_bool ?? false,
                        'check_in'   => $attendance?->start_time_format,
                        'check_out'  => $attendance?->end_time_format,
                    ];
                }
                $current->addDay();
            }

            return $this->jsonResponse(true, 'Ringkasan bulanan berhasil diambil', [
                'month'              => $month,
                'year'               => $year,
                'month_name'         => $startDate->translatedFormat('F Y'),
                'total_working_days' => $totalWorkingDays,
                'present_days'       => $presentDays,
                'late_days'          => $lateDays,
                'absent_days'        => $absentDays,
                'attendance_rate'    => $attendanceRate,
                'calendar'           => $calendar,
            ]);
        } catch (\Exception $e) {
            Log::error('Monthly summary error: ' . $e->getMessage());
            return $this->jsonResponse(false, 'Terjadi kesalahan sistem', null, 500);
        }
    }

    /**
     * GET /dashboard/recent-activities - Get recent activities
     */
    public function recentActivities(): JsonResponse
    {
        try {
            $user = auth()->user();

            if (!$user) {
                return $this->jsonResponse(false, 'User tidak ditemukan', null, 401);
            }

            // Recent attendances (5 terakhir)
            $attendances = Attendance::where('user_id', $user->id)
                ->orderBy('start_time', 'DESC')
                ->limit(5)
                ->get()
                ->map(function ($attendance) {
                    return [
                        'type'      => 'attendance',
                        'date'      => $attendance->start_time->toISOString(),
                        'time'      => $attendance->start_time_format,
                        'status'    => $attendance->is_late_bool ? 'late' : 'on_time',
                        'icon'      => $attendance->is_late_bool ? '⚠️' : '✅',
                        'message'   => $attendance->is_late_bool
                            ? 'Terlambat absen masuk'
                            : 'Absen masuk tepat waktu',
                    ];
                });

            // Recent leaves (5 terakhir)
            $leaves = Leave::where('user_id', $user->id)
                ->orderBy('created_at', 'DESC')
                ->limit(5)
                ->get()
                ->map(function ($leave) {
                    return [
                        'type'      => 'leave',
                        'date'      => $leave->created_at->toISOString(),
                        'duration'  => $leave->duration . ' hari',
                        'status'    => strtolower($leave->status),
                        'icon'      => $this->getStatusIcon($leave->status),
                        'message'   => "Cuti {$leave->category_label} - {$leave->status_label}",
                    ];
                });

            // Recent permissions (5 terakhir)
            $permissions = AttendancePermission::where('user_id', $user->id)
                ->orderBy('created_at', 'DESC')
                ->limit(5)
                ->get()
                ->map(function ($permission) {
                    return [
                        'type'      => 'permission',
                        'date'      => $permission->created_at->toISOString(),
                        'status'    => strtolower($permission->status),
                        'icon'      => $this->getStatusIcon($permission->status),
                        'message'   => "Izin {$permission->type_label} - {$permission->status_label}",
                    ];
                });

            // Merge dan sort by date
            $activities = $attendances->concat($leaves)->concat($permissions)
                ->sortByDesc('date')
                ->values()
                ->take(10);

            return $this->jsonResponse(true, 'Aktivitas terbaru berhasil diambil', [
                'activities' => $activities,
            ]);
        } catch (\Exception $e) {
            Log::error('Recent activities error: ' . $e->getMessage());
            return $this->jsonResponse(false, 'Terjadi kesalahan sistem', null, 500);
        }
    }

    /**
     * Hitung jumlah hari kerja (Senin-Jumat) antara dua tanggal.
     */
    private function countWorkingDays(Carbon $start, Carbon $end): int
    {
        $days = 0;
        $current = $start->copy();

        while ($current->lte($end)) {
            if ($current->isWeekday()) {
                $days++;
            }
            $current->addDay();
        }

        return $days;
    }

    /**
     * Hitung jumlah hari kerja dalam satu bulan berdasarkan schedule user.
     */
    private function countWorkingDaysInMonth(int $userId, int $year, int $month): int
    {
        $startDate = Carbon::createFromDate($year, $month, 1)->startOfMonth();
        $endDate = $startDate->copy()->endOfMonth();

        $schedules = \App\Models\Schedule::where('user_id', $userId)
            ->where('is_banned', false)
            ->where('start_date', '<=', $endDate)
            ->where(function ($q) use ($startDate) {
                $q->whereNull('end_date')->orWhere('end_date', '>=', $startDate);
            })
            ->get();

        if ($schedules->isEmpty()) {
            // Fallback: hitung hari Senin-Jumat
            return $this->countWorkingDays($startDate, $endDate);
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
    }

    /**
     * Get icon for status.
     */
    private function getStatusIcon(string $status): string
    {
        return match (strtoupper($status)) {
            'APPROVED'  => '✅',
            'REJECTED'  => '❌',
            'PENDING'   => '⏳',
            default     => '📋',
        };
    }
}
