<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Leave;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth, Validator, Log};
use Carbon\Carbon;

class LeaveController extends Controller
{
    /**
     * Helper: Standar Response JSON.
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
     * List Cuti (Riwayat):
     * - USER: Hanya melihat miliknya sendiri (PENTING!).
     * - ADMIN: Jika butuh menu khusus approval, biasanya menggunakan endpoint berbeda.
     */
    public function index()
    {
        try {
            $user = Auth::user();

            // REVISI: Pastikan hanya mengambil data milik user yang login
            // Kita hapus pengecekan position_id == 1 agar data tidak campur di Dashboard HP
            $leaves = Leave::with(['user.position'])
                ->where('user_id', $user->id) 
                ->latest()
                ->get();

            // REFORMAT: Menyamakan struktur JSON dengan yang diharapkan oleh Entity di Flutter
            $formattedLeaves = $leaves->map(function ($leave) {
                return [
                    'id'         => $leave->id,
                    'user_id'    => $leave->user_id,
                    // Carbon parse untuk memastikan format ISO8601 yang disukai Flutter
                    'start_date' => Carbon::parse($leave->start_date)->toIso8601String(),
                    'end_date'   => Carbon::parse($leave->end_date)->toIso8601String(),
                    'reason'     => $leave->reason,
                    'status'     => $leave->status,
                    'user'       => $leave->user ? [
                        'name'     => $leave->user->name,
                        'position' => [
                            'name' => $leave->user->position?->name ?? 'Karyawan IBM'
                        ]
                    ] : null,
                ];
            });

            return $this->jsonResponse(true, 'Data cuti berhasil dimuat', $formattedLeaves);
        } catch (\Exception $e) {
            Log::error("🚨 Error Load Cuti: " . $e->getMessage());
            return $this->jsonResponse(false, 'Gagal mengambil data cuti', null, 500);
        }
    }

    /**
     * Pengajuan Cuti Baru
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'start_date' => 'required|date',
            'end_date'   => 'required|date|after_or_equal:start_date',
            'reason'     => 'required|string|min:10|max:500'
        ]);

        if ($validator->fails()) {
            return $this->jsonResponse(false, 'Validasi gagal', $validator->errors(), 422);
        }

        try {
            $userId = Auth::id();

            // Konversi ke format DB Y-m-d
            $startDate = Carbon::parse($request->start_date)->format('Y-m-d');
            $endDate = Carbon::parse($request->end_date)->format('Y-m-d');

            // LOGIKA ANTI-BENTROK
            $overlap = Leave::where('user_id', $userId)
                ->where('status', '!=', 'rejected')
                ->where(function ($query) use ($startDate, $endDate) {
                    $query->whereBetween('start_date', [$startDate, $endDate])
                        ->orWhereBetween('end_date', [$startDate, $endDate])
                        ->orWhere(function ($q) use ($startDate, $endDate) {
                            $q->where('start_date', '<=', $startDate)
                                ->where('end_date', '>=', $endDate);
                        });
                })
                ->exists();

            if ($overlap) {
                return $this->jsonResponse(false, 'Anda sudah memiliki pengajuan cuti pada rentang tanggal tersebut.', null, 422);
            }

            $leave = Leave::create([
                'user_id'    => $userId,
                'start_date' => $startDate,
                'end_date'   => $endDate,
                'reason'     => $request->reason,
                'status'     => 'pending',
            ]);

            return $this->jsonResponse(true, 'Pengajuan cuti berhasil dikirim', $leave->load('user.position'), 201);
        } catch (\Exception $e) {
            Log::error("🚨 Error Submit Cuti: " . $e->getMessage());
            return $this->jsonResponse(false, 'Terjadi kesalahan sistem saat mengirim cuti', null, 500);
        }
    }

    /**
     * Update Status (Khusus Admin melalui Dashboard Web/Admin)
     */
    public function updateStatus(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|in:approved,rejected'
        ]);

        if ($validator->fails()) {
            return $this->jsonResponse(false, 'Gunakan status approved atau rejected', $validator->errors(), 422);
        }

        $leave = Leave::find($id);
        if (!$leave) {
            return $this->jsonResponse(false, 'Data cuti tidak ditemukan', null, 404);
        }

        if ($leave->status !== 'pending') {
            return $this->jsonResponse(false, 'Data cuti ini sudah diproses sebelumnya.', null, 422);
        }

        $leave->update(['status' => $request->status]);

        return $this->jsonResponse(true, "Cuti berhasil " . ($request->status == 'approved' ? 'disetujui' : 'ditolak'));
    }
}