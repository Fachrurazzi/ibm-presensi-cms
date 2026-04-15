<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Hash, Storage, DB, Validator};
use Illuminate\Validation\Rules\Password;

class ProfileController extends Controller
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
     * Update Profil (Nama, Foto, Password)
     */
    public function update(Request $request)
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'name'         => 'required|string|max:255',
            'image'        => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
            'old_password' => 'nullable|required_with:new_password',
            'new_password' => ['nullable', Password::min(6)],
        ]);

        if ($validator->fails()) {
            return $this->jsonResponse(false, 'Validasi gagal', $validator->errors(), 422);
        }

        try {
            $updatedUser = DB::transaction(function () use ($request, $user) {
                // 1. Logika Ganti Password
                if ($request->new_password) {
                    if (!Hash::check($request->old_password, $user->password)) {
                        return false;
                    }
                    $user->password = Hash::make($request->new_password);
                }

                // 2. Logika Ganti Nama
                $user->name = $request->name;

                // 3. Logika Ganti Foto Profil
                if ($request->hasFile('image')) {
                    // Hapus file lama jika ada
                    if ($user->image && Storage::disk('public')->exists($user->image)) {
                        Storage::disk('public')->delete($user->image);
                    }

                    // Simpan file baru ke folder public/users-avatar
                    $path = $request->file('image')->store('users-avatar', 'public');

                    // Optimasi untuk Linux (CachyOS): Set visibilitas file
                    Storage::disk('public')->setVisibility($path, 'public');

                    $user->image = $path;
                }

                $user->save();

                // Refresh dan load relasi position agar data jabatan terbawa
                $user->refresh();
                $user->load('position');

                return $user;
            });

            if ($updatedUser === false) {
                return $this->jsonResponse(false, 'Password lama tidak sesuai', null, 422);
            }

            // REFORMAT RESPONSE: Disamakan dengan AuthController & Flutter Entity
            $responseData = [
                'id'       => $updatedUser->id,
                'name'     => $updatedUser->name,
                'email'    => $updatedUser->email,
                'image'    => $updatedUser->image, // Flutter mencari key 'image'
                'position' => [
                    'id'   => $updatedUser->position?->id,
                    'name' => $updatedUser->position?->name ?? 'Karyawan IBM',
                ],
                'join_date' => $updatedUser->join_date,
                // Status biometrik tetap dikirim agar state di Flutter konsisten
                'is_face_registered' => !empty($updatedUser->face_model_path),
            ];

            return $this->jsonResponse(true, 'Profil berhasil diperbarui', $responseData);
        } catch (\Exception $e) {
            return $this->jsonResponse(false, 'Terjadi kesalahan: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * Mengambil Path Foto Profil
     */
    public function showPhoto(Request $request)
    {
        $user = $request->user();

        if (!$user || !$user->image) {
            return $this->jsonResponse(false, 'Foto profil belum diatur', null, 404);
        }

        // Penting untuk Linux: Bersihkan cache status file
        clearstatcache(true, Storage::disk('public')->path($user->image));

        if (!Storage::disk('public')->exists($user->image)) {
            return $this->jsonResponse(false, 'File fisik tidak ditemukan di storage', [
                'path' => $user->image
            ], 404);
        }

        return $this->jsonResponse(true, 'Foto profil berhasil diambil', $user->image);
    }
}
