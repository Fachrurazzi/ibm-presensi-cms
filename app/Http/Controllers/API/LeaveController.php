<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Leave;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

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
     * List Cuti:
     * - Admin: Melihat semua pengajuan cuti karyawan.
     * - Karyawan: Hanya melihat riwayat cuti miliknya sendiri.
     */
    public function index()
    {
        try {
            $user = Auth::user();

            // Cek apakah user memiliki role admin/super_admin
            // Sesuaikan dengan logic is_admin kamu
            if ($user->position_id == 1 || $user->hasRole('super_admin')) {
                $leaves = Leave::with('user.position')->latest()->get();
            } else {
                $leaves = Leave::where('user_id', $user->id)->latest()->get();
            }

            return $this->jsonResponse(true, 'Data cuti berhasil dimuat', $leaves);
        } catch (\Exception $e) {
            return $this->jsonResponse(false, 'Gagal mengambil data cuti', null, 500);
        }
    }

    /**
     * Pengajuan Cuti Baru:
     * - Mencegah tanggal bentrok (double input).
     * - Memastikan alasan cukup jelas.
     */
    public function store(Request $request)
    {
        // 1. Validasi tetap sama, tapi kita biarkan format date mentah
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
            
            // FIX UTAMA: Paksa format ke Y-m-d agar jam 00:00:00 tidak dikonversi ke UTC
            // Ini akan membuang offset timezone dari Flutter
            $startDate = \Carbon\Carbon::parse($request->start_date)->format('Y-m-d');
            $endDate = \Carbon\Carbon::parse($request->end_date)->format('Y-m-d');

            // Cek apakah ada tanggal yang bentrok
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

            // Simpan data murni Y-m-d
            $leave = Leave::create([
                'user_id'    => $userId,
                'start_date' => $startDate,
                'end_date'   => $endDate,
                'reason'     => $request->reason,
                'status'     => 'pending',
            ]);

            return $this->jsonResponse(true, 'Pengajuan cuti berhasil dikirim', $leave->load('user'), 201);
        } catch (\Exception $e) {
            // Log error untuk debug jika masih gagal
            \Log::error("Error Cuti: " . $e->getMessage());
            return $this->jsonResponse(false, 'Terjadi kesalahan sistem', null, 500);
        }
    }

    /**
     * Approval Cuti (Hanya Admin):
     * Mengubah status menjadi 'approved' atau 'rejected'.
     */
    public function updateStatus(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|in:approved,rejected'
        ]);

        if ($validator->fails()) {
            return $this->jsonResponse(false, 'Status tidak valid (Gunakan approved/rejected)', $validator->errors(), 422);
        }

        $leave = Leave::find($id);
        if (!$leave) {
            return $this->jsonResponse(false, 'Data cuti tidak ditemukan', null, 404);
        }

        // Opsional: Jika sudah disetujui, cegah untuk diubah kembali kecuali oleh Super Admin
        if ($leave->status !== 'pending') {
            return $this->jsonResponse(false, 'Data cuti yang sudah diproses tidak dapat diubah kembali.', null, 422);
        }

        $leave->update(['status' => $request->status]);

        return $this->jsonResponse(true, "Pengajuan cuti berhasil " . ($request->status == 'approved' ? 'disetujui' : 'ditolak'));
    }
}
