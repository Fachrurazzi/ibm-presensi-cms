<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Position;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Validator, Log};
use Illuminate\Http\JsonResponse;

class PositionController extends Controller
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
     * GET /positions - List semua position (public)
     */
    public function index(): JsonResponse
    {
        try {
            $positions = Position::active()
                ->orderBy('name')
                ->get()
                ->map(function ($position) {
                    return [
                        'id'         => $position->id,
                        'name'       => $position->name,
                        'user_count' => $position->user_count,
                    ];
                });

            return $this->jsonResponse(true, 'Data jabatan berhasil dimuat', $positions);
        } catch (\Exception $e) {
            Log::error('Position index error: ' . $e->getMessage());
            return $this->jsonResponse(false, 'Gagal mengambil data jabatan', null, 500);
        }
    }

    /**
     * POST /positions - Tambah position (Admin only)
     */
    public function store(Request $request): JsonResponse
    {
        if (!auth()->user()->hasRole(['super_admin', 'admin'])) {
            return $this->jsonResponse(false, 'Unauthorized', null, 403);
        }

        $validator = Validator::make($request->all(), Position::rules());

        if ($validator->fails()) {
            return $this->jsonResponse(false, 'Validasi gagal', $validator->errors(), 422);
        }

        try {
            $position = Position::create(['name' => trim($request->name)]);

            Log::info('Position created', [
                'position_id' => $position->id,
                'name'        => $position->name,
                'admin_id'    => auth()->id(),
            ]);

            return $this->jsonResponse(true, 'Jabatan berhasil ditambahkan', [
                'id'   => $position->id,
                'name' => $position->name,
            ], 201);
        } catch (\Exception $e) {
            Log::error('Position store error: ' . $e->getMessage());
            return $this->jsonResponse(false, 'Gagal menambah jabatan', null, 500);
        }
    }

    /**
     * PUT /positions/{id} - Update position (Admin only)
     */
    public function update(Request $request, $id): JsonResponse
    {
        if (!auth()->user()->hasRole(['super_admin', 'admin'])) {
            return $this->jsonResponse(false, 'Unauthorized', null, 403);
        }

        $position = Position::find($id);
        if (!$position) {
            return $this->jsonResponse(false, 'Jabatan tidak ditemukan', null, 404);
        }

        $validator = Validator::make($request->all(), Position::rules($id));

        if ($validator->fails()) {
            return $this->jsonResponse(false, 'Validasi gagal', $validator->errors(), 422);
        }

        try {
            $position->update(['name' => trim($request->name)]);

            Log::info('Position updated', [
                'position_id' => $position->id,
                'name'        => $position->name,
                'admin_id'    => auth()->id(),
            ]);

            return $this->jsonResponse(true, 'Jabatan berhasil diperbarui', [
                'id'   => $position->id,
                'name' => $position->name,
            ]);
        } catch (\Exception $e) {
            Log::error('Position update error: ' . $e->getMessage());
            return $this->jsonResponse(false, 'Gagal memperbarui jabatan', null, 500);
        }
    }

    /**
     * DELETE /positions/{id} - Hapus position (Admin only)
     */
    public function destroy($id): JsonResponse
    {
        if (!auth()->user()->hasRole(['super_admin', 'admin'])) {
            return $this->jsonResponse(false, 'Unauthorized', null, 403);
        }

        $position = Position::find($id);
        if (!$position) {
            return $this->jsonResponse(false, 'Jabatan tidak ditemukan', null, 404);
        }

        // Cek apakah masih ada karyawan dengan jabatan ini
        if ($position->users()->count() > 0) {
            return $this->jsonResponse(
                false,
                'Jabatan tidak dapat dihapus karena masih memiliki ' . $position->users()->count() . ' karyawan',
                null,
                422
            );
        }

        try {
            $positionName = $position->name;
            $position->delete();

            Log::info('Position deleted', [
                'position_id'   => $id,
                'position_name' => $positionName,
                'admin_id'      => auth()->id(),
            ]);

            return $this->jsonResponse(true, 'Jabatan berhasil dihapus');
        } catch (\Exception $e) {
            Log::error('Position delete error: ' . $e->getMessage());
            return $this->jsonResponse(false, 'Gagal menghapus jabatan', null, 500);
        }
    }
}
