<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Schedule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Storage, Validator, Log, Schema};
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;

class UserController extends Controller
{
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
     * GET /user/profile - Get user profile
     */
    public function profile(Request $request): JsonResponse
    {
        try {
            $user = $request->user()->load('position');

            return $this->jsonResponse(true, 'Profil user', [
                'id'                  => $user->id,
                'name'                => $user->name,
                'email'               => $user->email,
                'email_verified_at'   => $user->email_verified_at?->toISOString(),
                'position'            => [
                    'id'   => $user->position?->id,
                    'name' => $user->position?->name,
                ],
                'image_url'           => $user->avatar_url,
                'join_date'           => $user->join_date?->format('Y-m-d'),
                'leave_quota'         => (int) $user->leave_quota,
                'remaining_leave'     => $user->getRemainingLeaveQuota(),
                'cashable_leave'      => (int) $user->cashable_leave,
                'is_default_password' => (bool) $user->is_default_password,
                'is_face_registered'  => $user->hasFaceRegistered(),
                'roles'               => $user->getRoleNames(),
                'permissions'         => $user->getAllPermissions()->pluck('name'),
            ]);
        } catch (\Exception $e) {
            Log::error('User profile error: ' . $e->getMessage());
            return $this->jsonResponse(false, 'Gagal mengambil profil', null, 500);
        }
    }

    /**
     * PUT /user - Update user (nama & foto)
     */
    public function update(Request $request): JsonResponse
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'name'  => 'required|string|max:255',
            'image' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
        ]);

        if ($validator->fails()) {
            return $this->jsonResponse(false, 'Validasi gagal', $validator->errors(), 422);
        }

        try {
            $user->name = trim($request->name);

            if ($request->hasFile('image')) {
                // Hapus foto lama jika ada
                if ($user->image && Storage::disk('public')->exists($user->image)) {
                    Storage::disk('public')->delete($user->image);
                }

                // Upload foto baru
                $path = $request->file('image')->store('users-avatar', 'public');
                $user->image = $path;
            }

            $user->save();
            $user->refresh()->load('position');

            Log::info('User updated', [
                'user_id' => $user->id,
                'name'    => $user->name,
            ]);

            return $this->jsonResponse(true, 'User berhasil diperbarui', [
                'id'                 => $user->id,
                'name'               => $user->name,
                'email'              => $user->email,
                'image_url'          => $user->avatar_url,
                'position'           => [
                    'id'   => $user->position?->id,
                    'name' => $user->position?->name ?? 'Karyawan',
                ],
                'join_date'          => $user->join_date?->format('Y-m-d'),
                'is_face_registered' => $user->hasFaceRegistered(),
            ]);
        } catch (\Exception $e) {
            Log::error('User update error: ' . $e->getMessage());
            return $this->jsonResponse(false, 'Terjadi kesalahan', null, 500);
        }
    }

    /**
     * DELETE /user/photo - Hapus foto user
     */
    public function deletePhoto(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->image) {
            return $this->jsonResponse(false, 'Tidak ada foto user', null, 404);
        }

        try {
            Storage::disk('public')->delete($user->image);
            $user->image = null;
            $user->save();

            Log::info('User photo deleted', ['user_id' => $user->id]);

            return $this->jsonResponse(true, 'Foto user berhasil dihapus', [
                'image_url' => $user->avatar_url,
            ]);
        } catch (\Exception $e) {
            Log::error('Delete photo error: ' . $e->getMessage());
            return $this->jsonResponse(false, 'Gagal menghapus foto', null, 500);
        }
    }

    /**
     * GET /user/photo - Ambil data foto user
     */
    public function showPhoto(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user || !$user->image) {
            return $this->jsonResponse(false, 'Foto user belum diatur', null, 404);
        }

        return $this->jsonResponse(true, 'Foto user berhasil diambil', [
            'full_url' => $user->avatar_url
        ]);
    }

    /**
     * GET /user/schedule - Jadwal user per bulan
     */
    public function schedule(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $month = max(1, min(12, (int) $request->query('month', now()->month)));
            $year = max(2020, (int) $request->query('year', now()->year));

            $startDate = Carbon::createFromDate($year, $month, 1)->startOfMonth();
            $endDate = $startDate->copy()->endOfMonth();

            $schedules = Schedule::with(['shift', 'office'])
                ->where('user_id', $user->id)
                ->where('is_banned', false)
                ->where('start_date', '<=', $endDate)
                ->where(function ($q) use ($startDate) {
                    $q->whereNull('end_date')->orWhere('end_date', '>=', $startDate);
                })
                ->orderBy('start_date')
                ->get();

            $data = $schedules->map(function ($schedule) {
                return [
                    'id'          => $schedule->id,
                    'start_date'  => $schedule->start_date->toDateString(),
                    'end_date'    => $schedule->end_date?->toDateString(),
                    'shift'       => [
                        'id'           => $schedule->shift->id,
                        'name'         => $schedule->shift->name,
                        'start_time'   => $schedule->shift->start_time_display,
                        'end_time'     => $schedule->shift->end_time_display,
                        'is_overnight' => $schedule->shift->is_overnight,
                    ],
                    'office'      => [
                        'id'   => $schedule->office->id,
                        'name' => $schedule->office->name,
                    ],
                    'is_wfa'      => (bool) $schedule->is_wfa,
                    'is_active'   => $schedule->is_active,
                    'date_range'  => $schedule->date_range_display,
                ];
            });

            return $this->jsonResponse(true, 'Jadwal user', [
                'month'      => $month,
                'year'       => $year,
                'month_name' => $startDate->translatedFormat('F Y'),
                'schedules'  => $data,
                'total'      => $schedules->count(),
            ]);
        } catch (\Exception $e) {
            Log::error('User schedule error: ' . $e->getMessage());
            return $this->jsonResponse(false, 'Gagal mengambil jadwal', null, 500);
        }
    }

    /**
     * GET /user/schedule/today - Jadwal hari ini
     */
    public function todaySchedule(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $today = Carbon::today();

            $schedule = Schedule::getActiveSchedule($user->id, $today);

            if (!$schedule) {
                return $this->jsonResponse(false, 'Tidak ada jadwal hari ini', null, 404);
            }

            return $this->jsonResponse(true, 'Jadwal hari ini', [
                'id'            => $schedule->id,
                'date'          => $today->toDateString(),
                'day_name'      => $today->translatedFormat('l'),
                'shift'         => [
                    'id'         => $schedule->shift->id,
                    'name'       => $schedule->shift->name,
                    'start_time' => $schedule->shift->start_time_display,
                    'end_time'   => $schedule->shift->end_time_display,
                    'duration'   => $schedule->shift->duration_hours . ' jam',
                ],
                'office'        => [
                    'id'        => $schedule->office->id,
                    'name'      => $schedule->office->name,
                    'latitude'  => $schedule->office->latitude,
                    'longitude' => $schedule->office->longitude,
                    'radius'    => $schedule->office->radius,
                ],
                'is_wfa'        => (bool) $schedule->is_wfa,
                'is_banned'     => (bool) $schedule->is_banned,
                'banned_reason' => $schedule->banned_reason,
            ]);
        } catch (\Exception $e) {
            Log::error('Today schedule error: ' . $e->getMessage());
            return $this->jsonResponse(false, 'Gagal mengambil jadwal', null, 500);
        }
    }

    /**
     * PUT /user/fcm-token - Update FCM token untuk notifikasi
     */
    public function updateFCMToken(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'fcm_token' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return $this->jsonResponse(false, 'FCM token diperlukan', $validator->errors(), 422);
        }

        try {
            $user = $request->user();

            // Cek apakah kolom fcm_token ada di tabel users
            if (!Schema::hasColumn('users', 'fcm_token')) {
                return $this->jsonResponse(false, 'Fitur FCM belum tersedia', null, 500);
            }

            $user->fcm_token = $request->fcm_token;
            $user->save();

            Log::info('FCM token updated', ['user_id' => $user->id]);

            return $this->jsonResponse(true, 'FCM token berhasil diperbarui');
        } catch (\Exception $e) {
            Log::error('FCM token update error: ' . $e->getMessage());
            return $this->jsonResponse(false, 'Gagal memperbarui FCM token', null, 500);
        }
    }

    /**
     * GET /user/leave-summary - Ringkasan cuti user
     */
    public function leaveSummary(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $year = max(2020, (int) $request->query('year', now()->year));

            $totalQuota = (int) $user->leave_quota;
            $usedLeaves = $user->leaves()
                ->where('status', 'APPROVED')
                ->whereYear('start_date', $year)
                ->selectRaw('COALESCE(SUM(DATEDIFF(end_date, start_date) + 1), 0) as total_days')
                ->value('total_days') ?? 0;

            $pendingLeaves = $user->leaves()
                ->where('status', 'PENDING')
                ->whereYear('start_date', $year)
                ->count();

            $remaining = max(0, $totalQuota - $usedLeaves);

            return $this->jsonResponse(true, 'Ringkasan cuti', [
                'year'            => $year,
                'total_quota'     => $totalQuota,
                'used_leave'      => (int) $usedLeaves,
                'remaining_leave' => $remaining,
                'pending_count'   => $pendingLeaves,
                'cashable_leave'  => (int) $user->cashable_leave,
                'usage_percentage' => $totalQuota > 0 ? round(($usedLeaves / $totalQuota) * 100, 1) : 0,
            ]);
        } catch (\Exception $e) {
            Log::error('Leave summary error: ' . $e->getMessage());
            return $this->jsonResponse(false, 'Gagal mengambil ringkasan cuti', null, 500);
        }
    }

    /**
     * POST /user/location - Update user location
     */
    public function updateLocation(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'latitude'  => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
        ]);

        if ($validator->fails()) {
            return $this->jsonResponse(false, 'Koordinat tidak valid', $validator->errors(), 422);
        }

        try {
            $user = $request->user();

            $user->update([
                'last_latitude'    => $request->latitude,
                'last_longitude'   => $request->longitude,
                'last_location_at' => now(),
            ]);

            Log::info('Location updated', [
                'user_id'   => $user->id,
                'latitude'  => $request->latitude,
                'longitude' => $request->longitude,
            ]);

            return $this->jsonResponse(true, 'Lokasi berhasil diperbarui', [
                'latitude'  => $request->latitude,
                'longitude' => $request->longitude,
                'timestamp' => now()->toISOString(),
            ]);
        } catch (\Exception $e) {
            Log::error('Update location error: ' . $e->getMessage());
            return $this->jsonResponse(false, 'Gagal memperbarui lokasi', null, 500);
        }
    }
}
