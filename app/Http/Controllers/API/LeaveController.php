<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Leave;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth, Validator, Log};
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;

class LeaveController extends Controller
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
     * GET /leaves - Daftar cuti user dengan filter status
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $perPage = min((int) $request->query('per_page', 10), 50);
            $status = $request->query('status');

            $query = Leave::where('user_id', Auth::id())->latest();

            // Filter by status
            if ($status && in_array(strtoupper($status), ['PENDING', 'APPROVED', 'REJECTED'])) {
                $query->where('status', strtoupper($status));
            }

            $leaves = $query->paginate($perPage);

            $data = $leaves->through(function ($leave) {
                return [
                    'id'             => $leave->id,
                    'start_date'     => $leave->start_date->toDateString(),
                    'end_date'       => $leave->end_date->toDateString(),
                    'duration'       => $leave->duration,
                    'reason'         => $leave->reason,
                    'category'       => $leave->category,
                    'category_label' => $leave->category_label,
                    'status'         => strtolower($leave->status),
                    'status_label'   => $leave->status_label,
                    'created_at'     => $leave->created_at->toIso8601String(),
                    'can_cancel'     => $leave->status === 'PENDING',
                ];
            });

            return $this->jsonResponse(true, 'Data cuti berhasil dimuat', [
                'data' => $data,
                'meta' => [
                    'current_page' => $leaves->currentPage(),
                    'per_page'     => $leaves->perPage(),
                    'total'        => $leaves->total(),
                    'last_page'    => $leaves->lastPage(),
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Leave index error: ' . $e->getMessage());
            return $this->jsonResponse(false, 'Gagal mengambil data cuti', null, 500);
        }
    }

    /**
     * GET /leaves/types - Tipe cuti
     */
    public function types(): JsonResponse
    {
        try {
            $types = [
                ['value' => 'annual', 'label' => 'Cuti Tahunan', 'description' => 'Cuti tahunan yang diberikan setiap tahun'],
                ['value' => 'sick', 'label' => 'Cuti Sakit', 'description' => 'Cuti karena sakit dengan surat dokter'],
                ['value' => 'emergency', 'label' => 'Cuti Darurat', 'description' => 'Cuti untuk keperluan mendesak'],
                ['value' => 'maternity', 'label' => 'Cuti Melahirkan', 'description' => 'Cuti untuk persalinan'],
                ['value' => 'important', 'label' => 'Cuti Penting', 'description' => 'Cuti untuk keperluan penting lainnya'],
            ];

            return $this->jsonResponse(true, 'Tipe cuti berhasil dimuat', $types);
        } catch (\Exception $e) {
            Log::error('Leave types error: ' . $e->getMessage());
            return $this->jsonResponse(false, 'Gagal memuat tipe cuti', null, 500);
        }
    }

    /**
     * GET /leaves/quota - Info sisa cuti
     */
    public function quota(): JsonResponse
    {
        try {
            $user = Auth::user();

            $totalQuota = (int) $user->leave_quota;
            $remainingQuota = $user->getRemainingLeaveQuota();
            $usedQuota = $totalQuota - $remainingQuota;

            return $this->jsonResponse(true, 'Info kuota cuti', [
                'total_quota'     => $totalQuota,
                'used_this_year'  => $usedQuota,
                'remaining_quota' => $remainingQuota,
                'cashable_leave'  => (int) $user->cashable_leave,
                'usage_percentage' => $totalQuota > 0 ? round(($usedQuota / $totalQuota) * 100, 1) : 0,
            ]);
        } catch (\Exception $e) {
            Log::error('Leave quota error: ' . $e->getMessage());
            return $this->jsonResponse(false, 'Gagal memuat kuota cuti', null, 500);
        }
    }

    /**
     * GET /leaves/{id} - Detail cuti
     */
    public function show($id): JsonResponse
    {
        try {
            $leave = Leave::where('user_id', Auth::id())->find($id);

            if (!$leave) {
                return $this->jsonResponse(false, 'Data cuti tidak ditemukan', null, 404);
            }

            $data = [
                'id'             => $leave->id,
                'start_date'     => $leave->start_date->toDateString(),
                'end_date'       => $leave->end_date->toDateString(),
                'duration'       => $leave->duration,
                'reason'         => $leave->reason,
                'category'       => $leave->category,
                'category_label' => $leave->category_label,
                'status'         => strtolower($leave->status),
                'status_label'   => $leave->status_label,
                'note'           => $leave->note,
                'created_at'     => $leave->created_at->toIso8601String(),
                'can_cancel'     => $leave->status === 'PENDING',
            ];

            return $this->jsonResponse(true, 'Detail cuti', $data);
        } catch (\Exception $e) {
            Log::error('Leave show error: ' . $e->getMessage());
            return $this->jsonResponse(false, 'Gagal mengambil detail cuti', null, 500);
        }
    }

    /**
     * POST /leaves - Buat pengajuan cuti
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'start_date' => 'required|date|after_or_equal:today',
            'end_date'   => 'required|date|after_or_equal:start_date',
            'reason'     => 'required|string|min:10|max:500',
            'category'   => 'required|in:annual,sick,emergency,maternity,important'
        ], [
            'start_date.after_or_equal' => 'Tanggal mulai minimal hari ini',
            'end_date.after_or_equal'   => 'Tanggal selesai harus setelah atau sama dengan tanggal mulai',
            'reason.min'                => 'Alasan minimal 10 karakter',
            'reason.max'                => 'Alasan maksimal 500 karakter',
        ]);

        if ($validator->fails()) {
            return $this->jsonResponse(false, 'Validasi gagal', $validator->errors(), 422);
        }

        try {
            $user = Auth::user();
            $startDate = Carbon::parse($request->start_date);
            $endDate = Carbon::parse($request->end_date);
            $duration = $startDate->diffInDays($endDate) + 1;

            // Batas maksimal cuti
            if ($duration > 30) {
                return $this->jsonResponse(false, 'Maksimal cuti 30 hari berturut-turut.', null, 422);
            }

            // Cek sisa cuti
            if (!$user->canTakeLeave($duration)) {
                return $this->jsonResponse(false, "Sisa cuti tidak cukup (Sisa: {$user->getRemainingLeaveQuota()} hari).", null, 422);
            }

            // Cek overlap dengan pengajuan lain
            $overlap = Leave::where('user_id', $user->id)
                ->whereIn('status', ['PENDING', 'APPROVED'])
                ->where(function ($query) use ($startDate, $endDate) {
                    $query->whereBetween('start_date', [$startDate, $endDate])
                        ->orWhereBetween('end_date', [$startDate, $endDate])
                        ->orWhere(function ($q) use ($startDate, $endDate) {
                            $q->where('start_date', '<=', $startDate)
                                ->where('end_date', '>=', $endDate);
                        });
                })->exists();

            if ($overlap) {
                return $this->jsonResponse(false, 'Sudah ada pengajuan cuti pada tanggal tersebut.', null, 422);
            }

            $leave = Leave::create([
                'user_id'    => $user->id,
                'start_date' => $startDate->format('Y-m-d'),
                'end_date'   => $endDate->format('Y-m-d'),
                'category'   => $request->category,
                'reason'     => $request->reason,
                'status'     => 'PENDING',
            ]);

            Log::info('Pengajuan cuti baru', [
                'user_id'  => $user->id,
                'leave_id' => $leave->id,
                'duration' => $duration,
                'category' => $request->category,
            ]);

            return $this->jsonResponse(true, 'Pengajuan cuti berhasil dikirim', [
                'id'         => $leave->id,
                'start_date' => $leave->start_date->toDateString(),
                'end_date'   => $leave->end_date->toDateString(),
                'duration'   => $leave->duration,
                'category'   => $leave->category,
                'status'     => strtolower($leave->status),
            ], 201);
        } catch (\Exception $e) {
            Log::error('Store leave error: ' . $e->getMessage(), [
                'user_id' => Auth::id(),
                'trace'   => $e->getTraceAsString(),
            ]);
            return $this->jsonResponse(false, 'Kesalahan sistem', null, 500);
        }
    }

    /**
     * DELETE /leaves/{id} - Batalkan cuti
     */
    public function destroy($id): JsonResponse
    {
        try {
            $leave = Leave::where('user_id', Auth::id())
                ->where('status', 'PENDING')
                ->find($id);

            if (!$leave) {
                return $this->jsonResponse(false, 'Pengajuan cuti tidak ditemukan atau sudah diproses', null, 404);
            }

            $leave->delete();

            Log::info('Pengajuan cuti dibatalkan', [
                'user_id'  => Auth::id(),
                'leave_id' => $id
            ]);

            return $this->jsonResponse(true, 'Pengajuan cuti berhasil dibatalkan');
        } catch (\Exception $e) {
            Log::error('Cancel leave error: ' . $e->getMessage());
            return $this->jsonResponse(false, 'Gagal membatalkan cuti', null, 500);
        }
    }

    /**
     * PATCH /leaves/{id}/status - Update status (Admin/Manager only)
     */
    public function updateStatus(Request $request, $id): JsonResponse
    {
        // Role check: Admin, Super Admin, atau Manager
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
            $leave = Leave::find($id);

            if (!$leave) {
                return $this->jsonResponse(false, 'Data tidak ditemukan', null, 404);
            }

            if ($leave->status !== 'PENDING') {
                return $this->jsonResponse(false, 'Data sudah diproses', null, 422);
            }

            $leave->update([
                'status' => strtoupper($request->status),
                'note'   => $request->note,
            ]);

            Log::info('Status cuti diupdate', [
                'leave_id'   => $leave->id,
                'user_id'    => $leave->user_id,
                'new_status' => $request->status,
                'admin_id'   => auth()->id(),
            ]);

            $message = $request->status === 'APPROVED'
                ? 'Cuti berhasil disetujui'
                : 'Cuti berhasil ditolak';

            return $this->jsonResponse(true, $message);
        } catch (\Exception $e) {
            Log::error('Update leave status error: ' . $e->getMessage());
            return $this->jsonResponse(false, 'Gagal mengupdate status', null, 500);
        }
    }
}
