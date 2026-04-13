<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    /**
     * Helper: Standar Response JSON
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
     * Proses Login
     */
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email'    => 'required|email',
            'password' => 'required',
        ]);

        if ($validator->fails()) {
            return $this->jsonResponse(false, 'Validasi gagal', $validator->errors(), 422);
        }

        try {
            // Eager load relasi position agar data jabatan terbawa
            $user = User::with('position')->where('email', $request->email)->first();

            if (!$user || !Hash::check($request->password, $user->password)) {
                return $this->jsonResponse(false, 'Email atau password salah.', null, 422);
            }

            // Hapus token lama agar tidak menumpuk
            $user->tokens()->delete();
            $token = $user->createToken('auth_token')->plainTextToken;

            // STRUKTUR DATA: Disamakan dengan ProfileEntity di Flutter
            $userData = [
                'access_token' => $token,
                'token_type'   => 'Bearer',
                'user'         => [
                    'id'    => $user->id,
                    'name'  => $user->name,
                    'email' => $user->email,
                    // PENTING: Kirim sebagai Object agar Flutter tidak null
                    'position' => [
                        'id'   => $user->position?->id,
                        'name' => $user->position?->name ?? 'Karyawan IBM',
                    ],
                    'image' => $user->image, // Key disamakan dengan model Flutter
                    'image_url' => $user->image_url,
                    'is_default_password' => (bool) $user->is_default_password,
                    'is_face_registered'  => !empty($user->face_model_path),
                ],
            ];

            return $this->jsonResponse(true, 'Login berhasil. Selamat datang, ' . $user->name, $userData);
        } catch (\Exception $e) {
            return $this->jsonResponse(false, 'Terjadi kesalahan sistem: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * Update Password (Onboarding)
     */
    public function updatePassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'password' => 'required|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return $this->jsonResponse(false, 'Validasi gagal', $validator->errors(), 422);
        }

        try {
            $user = Auth::user();

            $user->update([
                'password' => Hash::make($request->password),
                'is_default_password' => false,
            ]);

            return $this->jsonResponse(true, 'Password berhasil diperbarui.');
        } catch (\Exception $e) {
            return $this->jsonResponse(false, 'Gagal memperbarui password', null, 500);
        }
    }

    /**
     * Registrasi Wajah
     */
    public function registerFace(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'face_model' => 'required',
            'image'      => 'required|image|max:2048',
        ]);

        if ($validator->fails()) {
            return $this->jsonResponse(false, 'Validasi gagal', $validator->errors(), 422);
        }

        try {
            $user = Auth::user();

            if ($request->hasFile('image')) {
                // Hapus foto wajah lama jika ada
                if ($user->image && Storage::disk('public')->exists($user->image)) {
                    Storage::disk('public')->delete($user->image);
                }

                $path = $request->file('image')->store('users/faces', 'public');
                $user->image = $path;
            }

            $user->face_model_path = $request->face_model;
            $user->save();

            return $this->jsonResponse(true, 'Data wajah berhasil didaftarkan.', [
                'image_url' => $user->image_url
            ]);
        } catch (\Exception $e) {
            return $this->jsonResponse(false, 'Gagal mendaftarkan wajah', null, 500);
        }
    }

    /**
     * Logout
     */
    public function logout(Request $request)
    {
        try {
            Auth::user()->currentAccessToken()->delete();
            return $this->jsonResponse(true, 'Berhasil keluar dari aplikasi.');
        } catch (\Exception $e) {
            return $this->jsonResponse(false, 'Gagal logout', null, 500);
        }
    }
}
