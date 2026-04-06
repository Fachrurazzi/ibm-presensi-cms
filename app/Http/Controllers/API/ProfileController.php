<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Hash, Storage, DB, Validator};
use Illuminate\Validation\Rules\Password;

class ProfileController extends Controller
{
    private function jsonResponse($success, $message, $data = null, $code = 200)
    {
        return response()->json([
            'success' => $success,
            'message' => $message,
            'data'    => $data,
        ], $code);
    }

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
                // 1. Logika Password
                if ($request->new_password) {
                    if (!Hash::check($request->old_password, $user->password)) {
                        return false;
                    }
                    $user->password = Hash::make($request->new_password);
                }

                // 2. Logika Nama
                $user->name = $request->name;

                // 3. Logika Foto
                if ($request->hasFile('image')) {
                    // Hapus foto lama jika ada
                    if ($user->image && Storage::disk('public')->exists($user->image)) {
                        Storage::disk('public')->delete($user->image);
                    }

                    // Simpan ke storage/app/public/users-avatar
                    $path = $request->file('image')->store('users-avatar', 'public');

                    // Pastikan OS Linux (CachyOS) memberikan permission yang benar segera
                    Storage::disk('public')->setVisibility($path, 'public');

                    $user->image = $path;
                }

                $user->save();

                // Refresh data terbaru agar ID image yang baru terbawa
                $user->refresh();
                $user->load('position');

                return $user;
            });

            if ($updatedUser === false) {
                return $this->jsonResponse(false, 'Password lama tidak sesuai', null, 422);
            }

            // Opsional: Kasih jeda sangat singkat agar filesystem Linux stabil
            // usleep(100000); // 0.1 detik

            return $this->jsonResponse(true, 'Profil berhasil diperbarui', $updatedUser);
        } catch (\Exception $e) {
            return $this->jsonResponse(false, 'Terjadi kesalahan: ' . $e->getMessage(), null, 500);
        }
    }

    public function showPhoto(Request $request)
    {
        $user = $request->user();

        if (!$user || !$user->image) {
            return $this->jsonResponse(false, 'Foto profil belum diatur', null, 404);
        }

        // Paksa PHP bersihkan cache status file agar tidak salah deteksi file exists
        clearstatcache(true, Storage::disk('public')->path($user->image));

        if (!Storage::disk('public')->exists($user->image)) {
            return $this->jsonResponse(false, 'File fisik tidak ditemukan', [
                'jalur_dicari' => Storage::disk('public')->path($user->image)
            ], 404);
        }

        return $this->jsonResponse(true, 'Foto profil berhasil diambil', $user->image);
    }
}
