<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Office;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Validator, Log};
use Illuminate\Http\JsonResponse;

class OfficeController extends Controller
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
     * GET /offices - List semua office (public)
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = Office::active();

            // Optional: search by name
            if ($request->has('search')) {
                $query->where('name', 'like', '%' . $request->search . '%');
            }

            $offices = $query->get()->map(function ($office) {
                return [
                    'id'              => $office->id,
                    'name'            => $office->name,
                    'latitude'        => $office->latitude,
                    'longitude'       => $office->longitude,
                    'radius'          => $office->radius,
                    'radius_display'  => $office->radius_display,
                    'google_maps_url' => $office->google_maps_url,
                ];
            });

            return $this->jsonResponse(true, 'Data office berhasil dimuat', $offices);
        } catch (\Exception $e) {
            Log::error('Office index error: ' . $e->getMessage());
            return $this->jsonResponse(false, 'Gagal mengambil data office', null, 500);
        }
    }

    /**
     * GET /offices/{id} - Detail office
     */
    public function show($id): JsonResponse
    {
        try {
            $office = Office::find($id);

            if (!$office) {
                return $this->jsonResponse(false, 'Office tidak ditemukan', null, 404);
            }

            return $this->jsonResponse(true, 'Detail office', [
                'id'              => $office->id,
                'name'            => $office->name,
                'latitude'        => $office->latitude,
                'longitude'       => $office->longitude,
                'radius'          => $office->radius,
                'radius_display'  => $office->radius_display,
                'supervisor_name' => $office->supervisor_name,
                'google_maps_url' => $office->google_maps_url,
            ]);
        } catch (\Exception $e) {
            Log::error('Office show error: ' . $e->getMessage());
            return $this->jsonResponse(false, 'Gagal mengambil detail office', null, 500);
        }
    }

    /**
     * POST /offices - Tambah office (Admin only)
     */
    public function store(Request $request): JsonResponse
    {
        if (!auth()->user()->hasRole(['super_admin', 'admin'])) {
            return $this->jsonResponse(false, 'Unauthorized', null, 403);
        }

        $validator = Validator::make($request->all(), Office::rules());

        if ($validator->fails()) {
            return $this->jsonResponse(false, 'Validasi gagal', $validator->errors(), 422);
        }

        try {
            $office = Office::create($request->all());

            Log::info('Office created', [
                'office_id' => $office->id,
                'admin_id'  => auth()->id(),
            ]);

            return $this->jsonResponse(true, 'Office berhasil ditambahkan', [
                'id'       => $office->id,
                'name'     => $office->name,
                'latitude' => $office->latitude,
                'longitude' => $office->longitude,
                'radius'   => $office->radius,
            ], 201);
        } catch (\Exception $e) {
            Log::error('Office store error: ' . $e->getMessage());
            return $this->jsonResponse(false, 'Gagal menambah office', null, 500);
        }
    }

    /**
     * PUT /offices/{id} - Update office (Admin only)
     */
    public function update(Request $request, $id): JsonResponse
    {
        if (!auth()->user()->hasRole(['super_admin', 'admin'])) {
            return $this->jsonResponse(false, 'Unauthorized', null, 403);
        }

        $office = Office::find($id);
        if (!$office) {
            return $this->jsonResponse(false, 'Office tidak ditemukan', null, 404);
        }

        $validator = Validator::make($request->all(), Office::rules($id));

        if ($validator->fails()) {
            return $this->jsonResponse(false, 'Validasi gagal', $validator->errors(), 422);
        }

        try {
            $office->update($request->all());

            Log::info('Office updated', [
                'office_id' => $office->id,
                'admin_id'  => auth()->id(),
            ]);

            return $this->jsonResponse(true, 'Office berhasil diperbarui', [
                'id'       => $office->id,
                'name'     => $office->name,
                'latitude' => $office->latitude,
                'longitude' => $office->longitude,
                'radius'   => $office->radius,
            ]);
        } catch (\Exception $e) {
            Log::error('Office update error: ' . $e->getMessage());
            return $this->jsonResponse(false, 'Gagal memperbarui office', null, 500);
        }
    }

    /**
     * DELETE /offices/{id} - Hapus office (Admin only)
     */
    public function destroy($id): JsonResponse
    {
        if (!auth()->user()->hasRole(['super_admin', 'admin'])) {
            return $this->jsonResponse(false, 'Unauthorized', null, 403);
        }

        $office = Office::find($id);
        if (!$office) {
            return $this->jsonResponse(false, 'Office tidak ditemukan', null, 404);
        }

        // Cek apakah office masih digunakan di schedule
        if ($office->schedules()->exists()) {
            return $this->jsonResponse(false, 'Office tidak dapat dihapus karena masih memiliki jadwal', null, 422);
        }

        try {
            $office->delete();

            Log::info('Office deleted', [
                'office_id' => $id,
                'admin_id'  => auth()->id(),
            ]);

            return $this->jsonResponse(true, 'Office berhasil dihapus');
        } catch (\Exception $e) {
            Log::error('Office delete error: ' . $e->getMessage());
            return $this->jsonResponse(false, 'Gagal menghapus office', null, 500);
        }
    }

    /**
     * GET /offices/nearest - Cari office terdekat
     */
    public function nearest(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'latitude'  => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
        ]);

        if ($validator->fails()) {
            return $this->jsonResponse(false, 'Koordinat tidak valid', $validator->errors(), 422);
        }

        try {
            $offices = Office::nearest($request->latitude, $request->longitude, 5)->get();

            $data = $offices->map(function ($office) {
                return [
                    'id'               => $office->id,
                    'name'             => $office->name,
                    'latitude'         => $office->latitude,
                    'longitude'        => $office->longitude,
                    'radius'           => $office->radius,
                    'distance'         => round($office->distance ?? 0),
                    'is_within_radius' => ($office->distance ?? 0) <= $office->radius,
                ];
            });

            return $this->jsonResponse(true, 'Office terdekat', $data);
        } catch (\Exception $e) {
            Log::error('Nearest office error: ' . $e->getMessage());
            return $this->jsonResponse(false, 'Gagal mencari office terdekat', null, 500);
        }
    }
}
