<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\AttendancePermission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth, Validator, Log, Storage};
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;

class PermissionController extends Controller
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
     * GET /permissions/types - Daftar tipe izin
     */
    public function types(): JsonResponse
    {
        try {
            $types = [
                ['value' => 'LATE', 'label' => 'Izin Terlambat', 'description' => 'Datang terlambat karena alasan tertentu'],
                ['value' => 'EARLY_LEAVE', 'label' => 'Izin Pulang Cepat', 'description' => 'Pulang lebih awal dari jadwal'],
                ['value' => 'BUSINESS_TRIP', 'label' => 'Dinas Luar Kota', 'description' => 'Bekerja di luar kantor (tetap dapat uang makan)'],
                ['value' => 'SICK_WITH_CERT', 'label' => 'Sakit dengan Surat Dokter', 'description' => 'Sakit dengan bukti surat dokter'],
            ];

            return $this->jsonResponse(true, 'Tipe izin berhasil dimuat', $types);
        } catch (\Exception $e) {
            Log::error('Permission types error: ' . $e->getMessage());
            return $this->jsonResponse(false, 'Gagal memuat tipe izin', null, 500);
        }
    }

    /**
     * GET /permissions - Riwayat izin user
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $perPage = min((int) $request->query('per_page', 10), 50);
            $status = $request->query('status');
            $month = $request->query('month');
            $year = $request->query('year');

            $query = AttendancePermission::where('user_id', Auth::id())->latest();

            if ($status && in_array(strtoupper($status), ['PENDING', 'APPROVED', 'REJECTED'])) {
                $query->where('status', strtoupper($status));
            }

            if ($month && $year) {
                $query->whereMonth('date', $month)->whereYear('date', $year);
            }

            $permissions = $query->paginate($perPage);

            $data = $permissions->through(function ($permission) {
                return [
                    'id'              => $permission->id,
                    'type'            => $permission->type,
                    'type_label'      => $permission->type_label,
                    'date'            => $permission->date->toDateString(),
                    'reason'          => $permission->reason,
                    'status'          => strtolower($permission->status),
                    'status_label'    => $permission->status_label,
                    'image_proof_url' => $permission->image_proof_url,
                    'note'            => $permission->note,
                    'created_at'      => $permission->created_at->toIso8601String(),
                    'can_cancel'      => $permission->status === 'PENDING',
                ];
            });

            return $this->jsonResponse(true, 'Riwayat izin berhasil dimuat', [
                'data' => $data,
                'meta' => [
                    'current_page' => $permissions->currentPage(),
                    'per_page'     => $permissions->perPage(),
                    'total'        => $permissions->total(),
                    'last_page'    => $permissions->lastPage(),
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Permission index error: ' . $e->getMessage());
            return $this->jsonResponse(false, 'Gagal mengambil data izin', null, 500);
        }
    }

    /**
     * GET /permissions/{id} - Detail izin
     */
    public function show($id): JsonResponse
    {
        try {
            $permission = AttendancePermission::where('user_id', Auth::id())->find($id);

            if (!$permission) {
                return $this->jsonResponse(false, 'Data izin tidak ditemukan', null, 404);
            }

            $data = [
                'id'              => $permission->id,
                'type'            => $permission->type,
                'type_label'      => $permission->type_label,
                'date'            => $permission->date->toDateString(),
                'reason'          => $permission->reason,
                'status'          => strtolower($permission->status),
                'status_label'    => $permission->status_label,
                'image_proof_url' => $permission->image_proof_url,
                'note'            => $permission->note,
                'created_at'      => $permission->created_at->toIso8601String(),
                'can_cancel'      => $permission->status === 'PENDING',
            ];

            return $this->jsonResponse(true, 'Detail izin', $data);
        } catch (\Exception $e) {
            Log::error('Permission show error: ' . $e->getMessage());
            return $this->jsonResponse(false, 'Gagal mengambil detail izin', null, 500);
        }
    }

    /**
     * GET /permissions/check - Cek apakah sudah ada izin di tanggal tertentu
     */
    public function check(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'date' => 'required|date',
        ]);

        if ($validator->fails()) {
            return $this->jsonResponse(false, 'Tanggal diperlukan', $validator->errors(), 422);
        }

        try {
            $hasPermission = AttendancePermission::where('user_id', Auth::id())
                ->whereDate('date', $request->date)
                ->whereIn('status', ['PENDING', 'APPROVED'])
                ->exists();

            $permission = AttendancePermission::where('user_id', Auth::id())
                ->whereDate('date', $request->date)
                ->first();

            return $this->jsonResponse(true, 'Status izin', [
                'date'           => $request->date,
                'has_permission' => $hasPermission,
                'permission'     => $permission ? [
                    'id'     => $permission->id,
                    'type'   => $permission->type_label,
                    'status' => strtolower($permission->status),
                ] : null,
            ]);
        } catch (\Exception $e) {
            Log::error('Permission check error: ' . $e->getMessage());
            return $this->jsonResponse(false, 'Gagal mengecek izin', null, 500);
        }
    }

    /**
     * POST /permissions - Ajukan izin baru
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'type'         => 'required|in:LATE,EARLY_LEAVE,BUSINESS_TRIP,SICK_WITH_CERT',
            'date'         => 'required|date|after_or_equal:today',
            'reason'       => 'required|string|min:10|max:500',
            'image_proof'  => 'nullable|image|mimes:jpeg,png,jpg|max:5120',
        ], [
            'type.required'       => 'Tipe izin harus dipilih',
            'type.in'             => 'Tipe izin tidak valid',
            'date.after_or_equal' => 'Tanggal izin minimal hari ini',
            'reason.min'          => 'Alasan minimal 10 karakter',
        ]);

        if ($validator->fails()) {
            return $this->jsonResponse(false, 'Validasi gagal', $validator->errors(), 422);
        }

        try {
            $userId = Auth::id();
            $date = Carbon::parse($request->date)->toDateString();

            // Cek apakah sudah ada izin PENDING/APPROVED di tanggal yang sama
            $exists = AttendancePermission::where('user_id', $userId)
                ->whereDate('date', $date)
                ->whereIn('status', ['PENDING', 'APPROVED'])
                ->exists();

            if ($exists) {
                return $this->jsonResponse(false, 'Sudah ada pengajuan izin untuk tanggal tersebut.', null, 422);
            }

            // Upload bukti gambar jika ada
            $imagePath = null;
            if ($request->hasFile('image_proof')) {
                $imagePath = $request->file('image_proof')->store(
                    'permissions/' . date('Y/m'),
                    'public'
                );
            }

            $permission = AttendancePermission::create([
                'user_id'     => $userId,
                'type'        => $request->type,
                'date'        => $date,
                'reason'      => $request->reason,
                'image_proof' => $imagePath,
                'status'      => 'PENDING',
            ]);

            Log::info('Pengajuan izin baru', [
                'user_id'       => $userId,
                'permission_id' => $permission->id,
                'type'          => $request->type,
                'date'          => $date,
            ]);

            return $this->jsonResponse(true, 'Pengajuan izin berhasil dikirim', [
                'id'              => $permission->id,
                'type'            => $permission->type,
                'type_label'      => $permission->type_label,
                'date'            => $permission->date->toDateString(),
                'reason'          => $permission->reason,
                'status'          => strtolower($permission->status),
                'image_proof_url' => $permission->image_proof_url,
            ], 201);
        } catch (\Exception $e) {
            Log::error('Store permission error: ' . $e->getMessage());

            // Hapus file jika upload sudah berhasil tapi create gagal
            if (isset($imagePath)) {
                Storage::disk('public')->delete($imagePath);
            }

            return $this->jsonResponse(false, 'Gagal mengajukan izin', null, 500);
        }
    }

    /**
     * DELETE /permissions/{id} - Batalkan pengajuan izin
     */
    public function destroy($id): JsonResponse
    {
        try {
            $permission = AttendancePermission::where('user_id', Auth::id())
                ->where('status', 'PENDING')
                ->find($id);

            if (!$permission) {
                return $this->jsonResponse(false, 'Pengajuan izin tidak ditemukan atau sudah diproses', null, 404);
            }

            // Hapus file bukti jika ada
            if ($permission->image_proof) {
                Storage::disk('public')->delete($permission->image_proof);
            }

            $permission->delete();

            Log::info('Pengajuan izin dibatalkan', [
                'user_id'       => Auth::id(),
                'permission_id' => $id,
            ]);

            return $this->jsonResponse(true, 'Pengajuan izin berhasil dibatalkan');
        } catch (\Exception $e) {
            Log::error('Cancel permission error: ' . $e->getMessage());
            return $this->jsonResponse(false, 'Gagal membatalkan izin', null, 500);
        }
    }

    /**
     * PATCH /permissions/{id}/status - Update status (Admin/Manager only)
     */
    public function updateStatus(Request $request, $id): JsonResponse
    {
        if (!auth()->user()->hasRole(['super_admin', 'admin', 'manager'])) {
            return $this->jsonResponse(false, 'Unauthorized', null, 403);
        }

        $validator = Validator::make($request->all(), [
            'status' => 'required|in:APPROVED,REJECTED',
            'note'   => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return $this->jsonResponse(false, 'Status tidak valid', $validator->errors(), 422);
        }

        try {
            $permission = AttendancePermission::find($id);

            if (!$permission) {
                return $this->jsonResponse(false, 'Data tidak ditemukan', null, 404);
            }

            if ($permission->status !== 'PENDING') {
                return $this->jsonResponse(false, 'Data sudah diproses', null, 422);
            }

            $permission->update([
                'status' => strtoupper($request->status),
                'note'   => $request->note,
            ]);

            Log::info('Status izin diupdate', [
                'permission_id' => $permission->id,
                'user_id'       => $permission->user_id,
                'new_status'    => $request->status,
                'admin_id'      => auth()->id(),
            ]);

            $message = $request->status === 'APPROVED'
                ? 'Izin berhasil disetujui'
                : 'Izin berhasil ditolak';

            return $this->jsonResponse(true, $message);
        } catch (\Exception $e) {
            Log::error('Update permission status error: ' . $e->getMessage());
            return $this->jsonResponse(false, 'Gagal mengupdate status', null, 500);
        }
    }
}