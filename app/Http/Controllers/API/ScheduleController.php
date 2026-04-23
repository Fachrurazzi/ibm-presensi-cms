<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Schedule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Validator, Log};
use Illuminate\Http\JsonResponse;

class ScheduleController extends Controller
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
     * GET /schedules - List semua schedule (Admin only)
     */
    public function index(Request $request): JsonResponse
    {
        if (!auth()->user()->hasRole(['super_admin', 'admin'])) {
            return $this->jsonResponse(false, 'Unauthorized', null, 403);
        }

        try {
            $perPage = min((int) $request->query('per_page', 10), 50);

            $query = Schedule::with(['user.position', 'shift', 'office'])
                ->latest('start_date');

            // Filter by user
            if ($request->has('user_id')) {
                $query->where('user_id', $request->user_id);
            }

            // Filter by office
            if ($request->has('office_id')) {
                $query->where('office_id', $request->office_id);
            }

            // Filter by status
            if ($request->has('status')) {
                match ($request->status) {
                    'active' => $query->active(),
                    'expired' => $query->expired(),
                    'banned' => $query->where('is_banned', true),
                    default => null,
                };
            }

            // Filter by date range
            if ($request->has('start_date') && $request->has('end_date')) {
                $query->where('start_date', '>=', $request->start_date)
                    ->where('start_date', '<=', $request->end_date);
            }

            $schedules = $query->paginate($perPage);

            $data = $schedules->through(function ($schedule) {
                return [
                    'id'          => $schedule->id,
                    'user'        => [
                        'id'       => $schedule->user->id,
                        'name'     => $schedule->user->name,
                        'position' => $schedule->user->position?->name,
                    ],
                    'shift'       => [
                        'id'         => $schedule->shift->id,
                        'name'       => $schedule->shift->name,
                        'start_time' => $schedule->shift->start_time_display,
                        'end_time'   => $schedule->shift->end_time_display,
                    ],
                    'office'      => [
                        'id'   => $schedule->office->id,
                        'name' => $schedule->office->name,
                    ],
                    'start_date'  => $schedule->start_date->toDateString(),
                    'end_date'    => $schedule->end_date?->toDateString(),
                    'is_wfa'      => (bool) $schedule->is_wfa,
                    'is_banned'   => (bool) $schedule->is_banned,
                    'is_active'   => $schedule->is_active,
                    'date_range'  => $schedule->date_range_display,
                ];
            });

            return $this->jsonResponse(true, 'Data schedule berhasil dimuat', [
                'data' => $data,
                'meta' => [
                    'current_page' => $schedules->currentPage(),
                    'per_page'     => $schedules->perPage(),
                    'total'        => $schedules->total(),
                    'last_page'    => $schedules->lastPage(),
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Schedule index error: ' . $e->getMessage());
            return $this->jsonResponse(false, 'Gagal mengambil data schedule', null, 500);
        }
    }

    /**
     * POST /schedules - Buat schedule (Admin only)
     */
    public function store(Request $request): JsonResponse
    {
        if (!auth()->user()->hasRole(['super_admin', 'admin'])) {
            return $this->jsonResponse(false, 'Unauthorized', null, 403);
        }

        $validator = Validator::make($request->all(), Schedule::rules());

        if ($validator->fails()) {
            return $this->jsonResponse(false, 'Validasi gagal', $validator->errors(), 422);
        }

        try {
            $schedule = Schedule::create($request->all());

            Log::info('Schedule created', [
                'schedule_id' => $schedule->id,
                'user_id'     => $schedule->user_id,
                'shift_id'    => $schedule->shift_id,
                'office_id'   => $schedule->office_id,
                'admin_id'    => auth()->id(),
            ]);

            return $this->jsonResponse(true, 'Schedule berhasil dibuat', [
                'id'         => $schedule->id,
                'user_id'    => $schedule->user_id,
                'shift_id'   => $schedule->shift_id,
                'office_id'  => $schedule->office_id,
                'start_date' => $schedule->start_date->toDateString(),
                'end_date'   => $schedule->end_date?->toDateString(),
                'is_wfa'     => (bool) $schedule->is_wfa,
            ], 201);
        } catch (\Exception $e) {
            Log::error('Schedule store error: ' . $e->getMessage());
            return $this->jsonResponse(false, 'Gagal membuat schedule: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * GET /schedules/{id} - Detail schedule
     */
    public function show($id): JsonResponse
    {
        try {
            $schedule = Schedule::with(['user.position', 'shift', 'office'])->find($id);

            if (!$schedule) {
                return $this->jsonResponse(false, 'Schedule tidak ditemukan', null, 404);
            }

            // Cek akses: admin atau user yang bersangkutan
            $user = auth()->user();
            if (!$user->hasRole(['super_admin', 'admin']) && $schedule->user_id !== $user->id) {
                return $this->jsonResponse(false, 'Unauthorized', null, 403);
            }

            return $this->jsonResponse(true, 'Detail schedule', [
                'id'          => $schedule->id,
                'user'        => [
                    'id'       => $schedule->user->id,
                    'name'     => $schedule->user->name,
                    'position' => $schedule->user->position?->name,
                ],
                'shift'       => [
                    'id'         => $schedule->shift->id,
                    'name'       => $schedule->shift->name,
                    'start_time' => $schedule->shift->start_time_display,
                    'end_time'   => $schedule->shift->end_time_display,
                ],
                'office'      => [
                    'id'        => $schedule->office->id,
                    'name'      => $schedule->office->name,
                    'latitude'  => $schedule->office->latitude,
                    'longitude' => $schedule->office->longitude,
                    'radius'    => $schedule->office->radius,
                ],
                'start_date'  => $schedule->start_date->toDateString(),
                'end_date'    => $schedule->end_date?->toDateString(),
                'is_wfa'      => (bool) $schedule->is_wfa,
                'is_banned'   => (bool) $schedule->is_banned,
                'banned_reason' => $schedule->banned_reason,
                'is_active'   => $schedule->is_active,
            ]);
        } catch (\Exception $e) {
            Log::error('Schedule show error: ' . $e->getMessage());
            return $this->jsonResponse(false, 'Gagal mengambil detail schedule', null, 500);
        }
    }

    /**
     * PUT /schedules/{id} - Update schedule (Admin only)
     */
    public function update(Request $request, $id): JsonResponse
    {
        if (!auth()->user()->hasRole(['super_admin', 'admin'])) {
            return $this->jsonResponse(false, 'Unauthorized', null, 403);
        }

        $schedule = Schedule::find($id);
        if (!$schedule) {
            return $this->jsonResponse(false, 'Schedule tidak ditemukan', null, 404);
        }

        $validator = Validator::make($request->all(), Schedule::rules($id));

        if ($validator->fails()) {
            return $this->jsonResponse(false, 'Validasi gagal', $validator->errors(), 422);
        }

        try {
            $schedule->update($request->all());

            Log::info('Schedule updated', [
                'schedule_id' => $schedule->id,
                'user_id'     => $schedule->user_id,
                'admin_id'    => auth()->id(),
            ]);

            return $this->jsonResponse(true, 'Schedule berhasil diperbarui', [
                'id'         => $schedule->id,
                'user_id'    => $schedule->user_id,
                'shift_id'   => $schedule->shift_id,
                'office_id'  => $schedule->office_id,
                'start_date' => $schedule->start_date->toDateString(),
                'end_date'   => $schedule->end_date?->toDateString(),
                'is_wfa'     => (bool) $schedule->is_wfa,
            ]);
        } catch (\Exception $e) {
            Log::error('Schedule update error: ' . $e->getMessage());
            return $this->jsonResponse(false, 'Gagal memperbarui schedule', null, 500);
        }
    }

    /**
     * DELETE /schedules/{id} - Hapus schedule (Admin only)
     */
    public function destroy($id): JsonResponse
    {
        if (!auth()->user()->hasRole(['super_admin', 'admin'])) {
            return $this->jsonResponse(false, 'Unauthorized', null, 403);
        }

        $schedule = Schedule::find($id);
        if (!$schedule) {
            return $this->jsonResponse(false, 'Schedule tidak ditemukan', null, 404);
        }

        // Cek apakah schedule memiliki attendance
        if ($schedule->attendances()->exists()) {
            return $this->jsonResponse(false, 'Schedule tidak dapat dihapus karena sudah memiliki data absensi', null, 422);
        }

        try {
            $schedule->delete();

            Log::info('Schedule deleted', [
                'schedule_id' => $id,
                'user_id'     => $schedule->user_id,
                'admin_id'    => auth()->id(),
            ]);

            return $this->jsonResponse(true, 'Schedule berhasil dihapus');
        } catch (\Exception $e) {
            Log::error('Schedule delete error: ' . $e->getMessage());
            return $this->jsonResponse(false, 'Gagal menghapus schedule', null, 500);
        }
    }

    /**
     * PATCH /schedules/{id}/ban - Ban schedule (Admin only)
     */
    public function ban(Request $request, $id): JsonResponse
    {
        if (!auth()->user()->hasRole(['super_admin', 'admin'])) {
            return $this->jsonResponse(false, 'Unauthorized', null, 403);
        }

        $validator = Validator::make($request->all(), [
            'banned_reason' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return $this->jsonResponse(false, 'Alasan banned diperlukan', $validator->errors(), 422);
        }

        $schedule = Schedule::find($id);
        if (!$schedule) {
            return $this->jsonResponse(false, 'Schedule tidak ditemukan', null, 404);
        }

        if ($schedule->is_banned) {
            return $this->jsonResponse(false, 'Schedule sudah dalam status banned', null, 422);
        }

        try {
            $schedule->update([
                'is_banned'     => true,
                'banned_reason' => $request->banned_reason,
            ]);

            // Revoke semua token user
            $schedule->user->tokens()->delete();

            Log::alert('Schedule banned', [
                'schedule_id' => $schedule->id,
                'user_id'     => $schedule->user_id,
                'reason'      => $request->banned_reason,
                'admin_id'    => auth()->id(),
            ]);

            return $this->jsonResponse(true, 'Schedule berhasil dibanned', [
                'id'            => $schedule->id,
                'is_banned'     => true,
                'banned_reason' => $request->banned_reason,
            ]);
        } catch (\Exception $e) {
            Log::error('Schedule ban error: ' . $e->getMessage());
            return $this->jsonResponse(false, 'Gagal mem-banned schedule', null, 500);
        }
    }

    /**
     * PATCH /schedules/{id}/unban - Unban schedule (Admin only)
     */
    public function unban($id): JsonResponse
    {
        if (!auth()->user()->hasRole(['super_admin', 'admin'])) {
            return $this->jsonResponse(false, 'Unauthorized', null, 403);
        }

        $schedule = Schedule::find($id);
        if (!$schedule) {
            return $this->jsonResponse(false, 'Schedule tidak ditemukan', null, 404);
        }

        if (!$schedule->is_banned) {
            return $this->jsonResponse(false, 'Schedule tidak dalam status banned', null, 422);
        }

        try {
            $schedule->update([
                'is_banned'     => false,
                'banned_reason' => null,
            ]);

            Log::info('Schedule unbanned', [
                'schedule_id' => $schedule->id,
                'user_id'     => $schedule->user_id,
                'admin_id'    => auth()->id(),
            ]);

            return $this->jsonResponse(true, 'Schedule berhasil di-unban', [
                'id'        => $schedule->id,
                'is_banned' => false,
            ]);
        } catch (\Exception $e) {
            Log::error('Schedule unban error: ' . $e->getMessage());
            return $this->jsonResponse(false, 'Gagal meng-unban schedule', null, 500);
        }
    }

    /**
     * GET /schedules/user/{userId} - Schedule by user (Admin/User sendiri)
     */
    public function byUser(Request $request, $userId): JsonResponse
    {
        $user = auth()->user();

        // Hanya admin atau user yang bersangkutan
        if (!$user->hasRole(['super_admin', 'admin']) && $user->id != $userId) {
            return $this->jsonResponse(false, 'Unauthorized', null, 403);
        }

        try {
            $schedules = Schedule::forUser($userId)
                ->orderBy('start_date', 'desc')
                ->get()
                ->map(function ($schedule) {
                    return [
                        'id'          => $schedule->id,
                        'shift'       => [
                            'id'         => $schedule->shift->id,
                            'name'       => $schedule->shift->name,
                            'start_time' => $schedule->shift->start_time_display,
                            'end_time'   => $schedule->shift->end_time_display,
                        ],
                        'office'      => [
                            'id'   => $schedule->office->id,
                            'name' => $schedule->office->name,
                        ],
                        'start_date'  => $schedule->start_date->toDateString(),
                        'end_date'    => $schedule->end_date?->toDateString(),
                        'is_wfa'      => (bool) $schedule->is_wfa,
                        'is_banned'   => (bool) $schedule->is_banned,
                        'is_active'   => $schedule->is_active,
                        'date_range'  => $schedule->date_range_display,
                    ];
                });

            return $this->jsonResponse(true, 'Schedule user berhasil dimuat', $schedules);
        } catch (\Exception $e) {
            Log::error('Schedule by user error: ' . $e->getMessage());
            return $this->jsonResponse(false, 'Gagal mengambil schedule', null, 500);
        }
    }
}
