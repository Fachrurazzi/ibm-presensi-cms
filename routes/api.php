<?php

use App\Http\Controllers\API\AttendanceController;
use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\LeaveController;
use App\Http\Controllers\API\ProfileController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Response;

/*
|--------------------------------------------------------------------------
| API Routes - PT INTIBOGA MANDIRI (IBM)
|--------------------------------------------------------------------------
*/

Route::prefix('v1')->group(function () {

    // --- Public Routes ---
    Route::post('/login', [AuthController::class, 'login'])->name('login');

    // --- Protected Routes (Sanctum) ---
    Route::middleware('auth:sanctum')->group(function () {

        // --- AUTH & ONBOARDING ---
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::post('/update-password', [AuthController::class, 'updatePassword']);
        Route::post('/register-face', [AuthController::class, 'registerFace']);

        // --- GLOBAL DATA (Direct Access) ---
        // Dipindah ke sini agar sinkron dengan Flutter: /api/v1/schedule
        Route::get('/schedule', [AttendanceController::class, 'getSchedule']);

        // --- PROFILE ---
        Route::prefix('profile')->group(function () {
            Route::post('/update', [ProfileController::class, 'update']);
            Route::get('/photo', [ProfileController::class, 'showPhoto']);
        });

        // --- ABSENSI (ATTENDANCE) ---
        Route::prefix('attendance')->controller(AttendanceController::class)->group(function () {
            Route::get('/today', 'getAttendanceToday');
            Route::post('/store', 'store');
            Route::get('/history/{month}/{year}', 'history');
            Route::post('/banned', 'banned');
        });

        // --- CUTI (LEAVES) ---
        Route::prefix('leaves')->controller(LeaveController::class)->group(function () {
            Route::get('/history', 'index');
            Route::post('/store', 'store');
            Route::post('/{id}/status', 'updateStatus');
        });
    });
});

/**
 * LOGIKA AKSES GAMBAR (Solusi Permission Linux CachyOS)
 * Menangani URL: base_url/storage/users-avatar/nama_file.jpg
 */
Route::get('/storage/{path}', function ($path) {
    $fullPath = storage_path('app/public/' . $path);

    if (!file_exists($fullPath)) {
        return response()->json([
            'success' => false,
            'message' => 'File tidak ditemukan di storage server'
        ], 404);
    }

    $file = file_get_contents($fullPath);
    $type = mime_content_type($fullPath);

    return Response::make($file, 200)->header("Content-Type", $type);
})->where('path', '.*');
