<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Shift;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Validator, Log};
use Illuminate\Http\JsonResponse;

class ShiftController extends Controller
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
     * GET /shifts - List semua shift (public)
     */
    public function index(): JsonResponse
    {
        try {
            $shifts = Shift::active()
                ->orderBy('start_time')
                ->get()
                ->map(function ($shift) {
                    return [
                        'id'             => $shift->id,
                        'name'           => $shift->name,
                        'start_time'     => $shift->start_time_display,
                        'end_time'       => $shift->end_time_display,
                        'duration_hours' => $shift->duration_hours,
                        'is_overnight'   => $shift->is_overnight,
                        'description'    => $shift->description,
                    ];
                });

            return $this->jsonResponse(true, 'Data shift berhasil dimuat', $shifts);
        } catch (\Exception $e) {
            Log::error('Shift index error: ' . $e->getMessage());
            return $this->jsonResponse(false, 'Gagal mengambil data shift', null, 500);
        }
    }

    /**
     * GET /shifts/{id} - Detail shift
     */
    public function show($id): JsonResponse
    {
        try {
            $shift = Shift::find($id);

            if (!$shift) {
                return $this->jsonResponse(false, 'Shift tidak ditemukan', null, 404);
            }

            return $this->jsonResponse(true, 'Detail shift', [
                'id'                 => $shift->id,
                'name'               => $shift->name,
                'start_time'         => $shift->start_time,
                'end_time'           => $shift->end_time,
                'start_time_display' => $shift->start_time_display,
                'end_time_display'   => $shift->end_time_display,
                'duration_hours'     => $shift->duration_hours,
                'is_overnight'       => $shift->is_overnight,
                'description'        => $shift->description,
                'schedules_count'    => $shift->schedules()->count(),
            ]);
        } catch (\Exception $e) {
            Log::error('Shift show error: ' . $e->getMessage());
            return $this->jsonResponse(false, 'Gagal mengambil detail shift', null, 500);
        }
    }

    /**
     * POST /shifts - Tambah shift (Admin only)
     */
    public function store(Request $request): JsonResponse
    {
        if (!auth()->user()->hasRole(['super_admin', 'admin'])) {
            return $this->jsonResponse(false, 'Unauthorized', null, 403);
        }

        $validator = Validator::make($request->all(), Shift::rules());

        if ($validator->fails()) {
            return $this->jsonResponse(false, 'Validasi gagal', $validator->errors(), 422);
        }

        try {
            $shift = Shift::create([
                'name'        => trim($request->name),
                'start_time'  => $request->start_time,
                'end_time'    => $request->end_time,
                'description' => $request->description,
            ]);

            Log::info('Shift created', [
                'shift_id'   => $shift->id,
                'name'       => $shift->name,
                'start_time' => $shift->start_time,
                'end_time'   => $shift->end_time,
                'admin_id'   => auth()->id(),
            ]);

            return $this->jsonResponse(true, 'Shift berhasil ditambahkan', [
                'id'               => $shift->id,
                'name'             => $shift->name,
                'start_time'       => $shift->start_time_display,
                'end_time'         => $shift->end_time_display,
                'duration_hours'   => $shift->duration_hours,
                'is_overnight'     => $shift->is_overnight,
            ], 201);
        } catch (\Exception $e) {
            Log::error('Shift store error: ' . $e->getMessage());
            return $this->jsonResponse(false, 'Gagal menambah shift: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * PUT /shifts/{id} - Update shift (Admin only)
     */
    public function update(Request $request, $id): JsonResponse
    {
        if (!auth()->user()->hasRole(['super_admin', 'admin'])) {
            return $this->jsonResponse(false, 'Unauthorized', null, 403);
        }

        $shift = Shift::find($id);
        if (!$shift) {
            return $this->jsonResponse(false, 'Shift tidak ditemukan', null, 404);
        }

        $validator = Validator::make($request->all(), Shift::rules($id));

        if ($validator->fails()) {
            return $this->jsonResponse(false, 'Validasi gagal', $validator->errors(), 422);
        }

        try {
            $shift->update([
                'name'        => trim($request->name),
                'start_time'  => $request->start_time,
                'end_time'    => $request->end_time,
                'description' => $request->description,
            ]);

            Log::info('Shift updated', [
                'shift_id'   => $shift->id,
                'name'       => $shift->name,
                'start_time' => $shift->start_time,
                'end_time'   => $shift->end_time,
                'admin_id'   => auth()->id(),
            ]);

            return $this->jsonResponse(true, 'Shift berhasil diperbarui', [
                'id'               => $shift->id,
                'name'             => $shift->name,
                'start_time'       => $shift->start_time_display,
                'end_time'         => $shift->end_time_display,
                'duration_hours'   => $shift->duration_hours,
                'is_overnight'     => $shift->is_overnight,
            ]);
        } catch (\Exception $e) {
            Log::error('Shift update error: ' . $e->getMessage());
            return $this->jsonResponse(false, 'Gagal memperbarui shift: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * DELETE /shifts/{id} - Hapus shift (Admin only)
     */
    public function destroy($id): JsonResponse
    {
        if (!auth()->user()->hasRole(['super_admin', 'admin'])) {
            return $this->jsonResponse(false, 'Unauthorized', null, 403);
        }

        $shift = Shift::find($id);
        if (!$shift) {
            return $this->jsonResponse(false, 'Shift tidak ditemukan', null, 404);
        }

        // Cek apakah shift masih digunakan di schedule
        $schedulesCount = $shift->schedules()->count();
        if ($schedulesCount > 0) {
            return $this->jsonResponse(
                false,
                "Shift tidak dapat dihapus karena masih memiliki {$schedulesCount} jadwal",
                null,
                422
            );
        }

        try {
            $shiftName = $shift->name;
            $shift->delete();

            Log::info('Shift deleted', [
                'shift_id'   => $id,
                'shift_name' => $shiftName,
                'admin_id'   => auth()->id(),
            ]);

            return $this->jsonResponse(true, 'Shift berhasil dihapus');
        } catch (\Exception $e) {
            Log::error('Shift delete error: ' . $e->getMessage());
            return $this->jsonResponse(false, 'Gagal menghapus shift', null, 500);
        }
    }
}
