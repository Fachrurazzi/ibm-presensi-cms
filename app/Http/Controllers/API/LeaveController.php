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
     * Helper: Standar Response
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
     * List Cuti (Untuk Karyawan: Lihat miliknya sendiri | Untuk Admin: Lihat semua)
     */
    public function index()
    {
        try {
            $user = Auth::user();

            // Jika super_admin, lihat semua. Jika bukan, lihat punya sendiri.
            if ($user->hasRole('super_admin')) {
                $leaves = Leave::with('user')->latest()->get();
            } else {
                $leaves = Leave::where('user_id', $user->id)->latest()->get();
            }

            return $this->jsonResponse(true, 'Data cuti berhasil dimuat', $leaves);
        } catch (\Exception $e) {
            return $this->jsonResponse(false, 'Gagal mengambil data', null, 500);
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
            'reason'     => 'required|string|min:10'
        ]);

        if ($validator->fails()) {
            return $this->jsonResponse(false, 'Validasi gagal', $validator->errors(), 422);
        }

        try {
            $exists = Leave::where('user_id', Auth::id())
                ->where(function ($query) use ($request) {
                    $query->whereBetween('start_date', [$request->start_date, $request->end_date])
                        ->orWhereBetween('end_date', [$request->start_date, $request->end_date]);
                })->exists();

            if ($exists) {
                return $this->jsonResponse(false, 'Anda sudah memiliki pengajuan cuti di tanggal tersebut.', null, 422);
            }

            $leave = Leave::create([
                'user_id'    => Auth::id(),
                'start_date' => $request->start_date,
                'end_date'   => $request->end_date,
                'reason'     => $request->reason,
                'status'     => 'pending', // Default selalu pending
            ]);

            return $this->jsonResponse(true, 'Pengajuan cuti berhasil dikirim', $leave->load('user'), 201);
        } catch (\Exception $e) {
            return $this->jsonResponse(false, 'Terjadi kesalahan server', null, 500);
        }
    }

    /**
     * Approval Cuti (Hanya Admin)
     */
    public function updateStatus(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|in:approved,rejected'
        ]);

        if ($validator->fails()) {
            return $this->jsonResponse(false, 'Status tidak valid', $validator->errors(), 422);
        }

        $leave = Leave::find($id);
        if (!$leave) return $this->jsonResponse(false, 'Data cuti tidak ditemukan', null, 404);

        $leave->update(['status' => $request->status]);

        return $this->jsonResponse(true, "Status cuti berhasil diubah menjadi $request->status");
    }
}
