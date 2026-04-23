<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Hash, Storage, Validator, Auth, Log, RateLimiter, Password as PasswordBroker};
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\JsonResponse;

class AuthController extends Controller
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
     * Login user dan berikan token.
     */
    public function login(Request $request): JsonResponse
    {
        // Rate limiting dengan built-in Laravel RateLimiter
        $throttleKey = 'login:' . $request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            Log::warning('Rate limit exceeded', [
                'ip'      => $request->ip(),
                'email'   => $request->input('email'),
            ]);

            return $this->jsonResponse(
                false,
                "Terlalu banyak percobaan login. Silakan coba lagi dalam {$seconds} detik.",
                ['retry_after' => $seconds],
                429
            );
        }

        // Validasi input
        $validator = Validator::make($request->all(), [
            'email'       => 'required|email|max:255',
            'password'    => 'required|string|min:6',
            'device_name' => 'nullable|string|max:100',
        ]);

        if ($validator->fails()) {
            return $this->jsonResponse(false, 'Validasi gagal', $validator->errors(), 422);
        }

        try {
            $user = User::with('position')
                ->where('email', $request->email)
                ->first();

            // Verifikasi kredensial
            if (!$user || !Hash::check($request->password, $user->password)) {
                RateLimiter::hit($throttleKey, 60);

                Log::warning('Login gagal - kredensial salah', [
                    'email' => $request->email,
                    'ip'    => $request->ip(),
                ]);

                return $this->jsonResponse(false, 'Email atau password salah.', null, 401);
            }

            // Cek verifikasi email
            if (!$user->hasVerifiedEmail()) {
                return $this->jsonResponse(
                    false,
                    'Email belum diverifikasi. Silakan cek inbox atau minta kirim ulang verifikasi.',
                    ['email_verified' => false],
                    403
                );
            }

            // Reset rate limiter setelah login sukses
            RateLimiter::clear($throttleKey);

            // Hapus token lama untuk perangkat yang sama
            $deviceName = $request->device_name ?? 'unknown_device';
            $user->tokens()->where('name', $deviceName)->delete();

            // Buat token baru dengan abilities
            $token = $user->createToken($deviceName, ['*'])->plainTextToken;

            Log::info('Login sukses', [
                'user_id' => $user->id,
                'device'  => $deviceName,
                'ip'      => $request->ip(),
            ]);

            return $this->jsonResponse(true, 'Login berhasil', [
                'access_token' => $token,
                'token_type'   => 'Bearer',
                'user'         => $user->formatForApi(),
            ]);
        } catch (\Exception $e) {
            Log::error('Login error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'email' => $request->email ?? null,
            ]);

            return $this->jsonResponse(false, 'Terjadi kesalahan sistem. Silakan coba lagi.', null, 500);
        }
    }

    /**
     * Mendapatkan data user yang sedang login.
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->load('position');

        return $this->jsonResponse(true, 'Data user', $user->formatForApi());
    }

    /**
     * Update password dengan verifikasi password lama.
     */
    public function updatePassword(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'current_password' => 'required|string',
            'password'         => [
                'required',
                'string',
                'confirmed',
                Password::min(8)
                    ->letters()
                    ->mixedCase()
                    ->numbers()
                    ->symbols()
                    ->uncompromised(),
            ],
        ], [
            'password.uncompromised' => 'Password ini terlalu umum atau pernah bocor. Gunakan password yang lebih aman.',
        ]);

        if ($validator->fails()) {
            return $this->jsonResponse(false, 'Validasi gagal', $validator->errors(), 422);
        }

        try {
            $user = $request->user();

            if (!Hash::check($request->current_password, $user->password)) {
                return $this->jsonResponse(false, 'Password lama salah', null, 422);
            }

            // Cegah penggunaan password yang sama
            if (Hash::check($request->password, $user->password)) {
                return $this->jsonResponse(
                    false,
                    'Password baru tidak boleh sama dengan password lama',
                    null,
                    422
                );
            }

            $user->updatePassword($request->password);

            Log::info('Password diubah', ['user_id' => $user->id]);

            return $this->jsonResponse(true, 'Password berhasil diperbarui');
        } catch (\Exception $e) {
            Log::error('Gagal update password: ' . $e->getMessage(), [
                'user_id' => $user->id ?? null,
            ]);

            return $this->jsonResponse(false, 'Gagal memperbarui password', null, 500);
        }
    }

    /**
     * Registrasi data wajah (face recognition).
     */
    public function registerFace(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'face_model' => 'required|string',
            'image'      => 'required|image|mimes:jpeg,png,jpg|max:5120',
        ]);

        if ($validator->fails()) {
            return $this->jsonResponse(false, 'Validasi gagal', $validator->errors(), 422);
        }

        try {
            // Validasi struktur face model JSON
            $faceModel = json_decode($request->face_model, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return $this->jsonResponse(false, 'Format face model tidak valid (JSON error)', null, 422);
            }

            // Validasi minimal struktur face model
            if (!isset($faceModel['descriptors']) || !is_array($faceModel['descriptors'])) {
                return $this->jsonResponse(false, 'Struktur face model tidak valid', null, 422);
            }

            $user = $request->user();

            // Upload gambar wajah
            if ($request->hasFile('image')) {
                // Hapus gambar lama jika ada
                if ($user->image) {
                    Storage::disk('public')->delete($user->image);
                }

                $path = $request->file('image')->store(
                    'users/faces/' . date('Y/m'),
                    'public'
                );
                $user->image = $path;
            }

            // Simpan face model
            $user->registerFace(json_encode($faceModel));

            Log::info('Wajah terdaftar', [
                'user_id'    => $user->id,
                'image_path' => $user->image,
            ]);

            return $this->jsonResponse(true, 'Data wajah berhasil didaftarkan', [
                'image_url'          => $user->avatar_url,
                'is_face_registered' => true,
            ]);
        } catch (\Exception $e) {
            Log::error('Gagal registrasi wajah: ' . $e->getMessage(), [
                'user_id' => $request->user()->id ?? null,
                'trace'   => $e->getTraceAsString(),
            ]);

            return $this->jsonResponse(false, 'Gagal mendaftarkan wajah', null, 500);
        }
    }

    /**
     * Kirim ulang email verifikasi.
     */
    public function resendVerification(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return $this->jsonResponse(false, 'Email sudah terverifikasi', null, 400);
        }

        try {
            $user->sendEmailVerificationNotification();

            Log::info('Email verifikasi dikirim ulang', ['user_id' => $user->id]);

            return $this->jsonResponse(true, 'Link verifikasi telah dikirim ke email Anda');
        } catch (\Exception $e) {
            Log::error('Gagal kirim email verifikasi: ' . $e->getMessage());

            return $this->jsonResponse(false, 'Gagal mengirim email verifikasi', null, 500);
        }
    }

    /**
     * Logout dari perangkat saat ini.
     */
    public function logout(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $tokenId = $user->currentAccessToken()->id;

            $user->tokens()->where('id', $tokenId)->delete();

            Log::info('Logout', [
                'user_id'  => $user->id,
                'token_id' => $tokenId,
            ]);

            return $this->jsonResponse(true, 'Berhasil keluar');
        } catch (\Exception $e) {
            Log::error('Gagal logout: ' . $e->getMessage());

            return $this->jsonResponse(false, 'Gagal logout', null, 500);
        }
    }

    /**
     * Logout dari semua perangkat (revoke semua token).
     */
    public function logoutAllDevices(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $tokenCount = $user->tokens()->count();

            $user->tokens()->delete();

            Log::info('Logout semua perangkat', [
                'user_id'     => $user->id,
                'token_count' => $tokenCount,
            ]);

            return $this->jsonResponse(true, "Berhasil keluar dari {$tokenCount} perangkat");
        } catch (\Exception $e) {
            Log::error('Gagal logout semua perangkat: ' . $e->getMessage());

            return $this->jsonResponse(false, 'Gagal logout', null, 500);
        }
    }

    /**
     * Kirim link reset password.
     */
    public function forgotPassword(Request $request): JsonResponse
    {
        // Rate limiting untuk forgot password
        $throttleKey = 'forgot-password:' . $request->ip();
        if (RateLimiter::tooManyAttempts($throttleKey, 3)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            return $this->jsonResponse(
                false,
                "Terlalu banyak permintaan. Coba lagi dalam {$seconds} detik.",
                null,
                429
            );
        }

        $validator = Validator::make($request->all(), [
            'email' => 'required|email|exists:users,email',
        ]);

        if ($validator->fails()) {
            return $this->jsonResponse(false, 'Validasi gagal', $validator->errors(), 422);
        }

        try {
            $status = PasswordBroker::sendResetLink($request->only('email'));

            if ($status === PasswordBroker::RESET_LINK_SENT) {
                RateLimiter::clear($throttleKey);
                Log::info('Reset password link sent', ['email' => $request->email]);

                return $this->jsonResponse(true, 'Link reset password telah dikirim ke email Anda');
            }

            RateLimiter::hit($throttleKey, 300); // 5 menit
            return $this->jsonResponse(false, 'Gagal mengirim link reset password', null, 500);
        } catch (\Exception $e) {
            Log::error('Forgot password error: ' . $e->getMessage());

            return $this->jsonResponse(false, 'Terjadi kesalahan sistem', null, 500);
        }
    }

    /**
     * Reset password dengan token.
     */
    public function resetPassword(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'token'    => 'required|string',
            'email'    => 'required|email|exists:users,email',
            'password' => [
                'required',
                'string',
                'confirmed',
                Password::min(8)
                    ->letters()
                    ->mixedCase()
                    ->numbers()
                    ->symbols()
                    ->uncompromised(),
            ],
        ]);

        if ($validator->fails()) {
            return $this->jsonResponse(false, 'Validasi gagal', $validator->errors(), 422);
        }

        try {
            $status = PasswordBroker::reset(
                $request->only('email', 'password', 'password_confirmation', 'token'),
                function (User $user, string $password) {
                    $user->updatePassword($password);
                }
            );

            if ($status === PasswordBroker::PASSWORD_RESET) {
                Log::info('Password direset', ['email' => $request->email]);

                // Revoke semua token setelah reset password
                User::where('email', $request->email)->first()?->tokens()->delete();

                return $this->jsonResponse(true, 'Password berhasil direset. Silakan login dengan password baru.');
            }

            return $this->jsonResponse(false, 'Token tidak valid atau sudah kadaluarsa', null, 400);
        } catch (\Exception $e) {
            Log::error('Reset password error: ' . $e->getMessage());

            return $this->jsonResponse(false, 'Terjadi kesalahan sistem', null, 500);
        }
    }
}
