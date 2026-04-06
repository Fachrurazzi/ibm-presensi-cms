<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Proses Login Karyawan
     */
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        // 1. Cari user & muat relasi position (Penting untuk label Admin/Operator di Flutter)
        $user = User::with('position')->where('email', $request->email)->first();

        // 2. Validasi User & Password
        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Email atau password yang Anda masukkan salah.',
                'data'    => null,
            ], 422);
        }

        // 3. Hapus token lama (opsional, agar login hanya bisa di satu perangkat)
        $user->tokens()->delete();

        // 4. Generate Token Baru
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Login berhasil. Selamat datang, ' . $user->name,
            'data'    => [
                'access_token' => $token,
                'token_type'   => 'Bearer',
                'user'         => $user,
            ],
        ]);
    }

    /**
     * Proses Logout (Hapus Token)
     */
    public function logout(Request $request)
    {
        // Menghapus token yang sedang digunakan saat ini
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Berhasil keluar dari aplikasi.',
            'data'    => null
        ]);
    }
}
