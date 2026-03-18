<?php

use App\Http\Controllers\API\AttendanceController;
use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\LeaveController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// --- Public Routes ---
Route::post('/v1/login', [AuthController::class, 'login'])->name('login');

// --- Protected Routes (Sanctum) ---
Route::middleware('auth:sanctum')->group(function () {

    // Semua rute di dalam grup ini akan diawali dengan /v1
    Route::prefix('v1')->group(function () {

        // --- FITUR ABSENSI & KEAMANAN ---
        Route::get('/today', [AttendanceController::class, 'getAttendanceToday'])->name('get_attendance_today');
        Route::get('/schedule', [AttendanceController::class, 'getSchedule'])->name('get_schedule');
        Route::post('/store', [AttendanceController::class, 'store'])->name('tagging_presensi');
        Route::get('/history/{month}/{year}', [AttendanceController::class, 'getAttendanceByMonthAndYear'])->name('get_attendance_by_month_and_year');
        Route::get('/image', [AttendanceController::class, 'getImage'])->name('get_image');

        // Rute Auto-Banned (Bisa dipanggil oleh sistem aplikasi user sendiri)
        Route::post('/banned', [AttendanceController::class, 'banned'])->name('user.auto_banned');

        // --- FITUR CUTI (LEAVES) ---
        Route::prefix('leaves')->group(function () {
            Route::get('/', [LeaveController::class, 'index'])->name('leaves.index');
            Route::post('/', [LeaveController::class, 'store'])->name('leaves.store');

            // Approval Cuti (Hanya Admin)
            Route::middleware('is_admin')->post('/{id}/status', [LeaveController::class, 'updateStatus'])->name('leaves.update_status');
        });

        // --- FITUR KHUSUS ADMIN ---
        Route::middleware('is_admin')->group(function () {
            // Admin juga bisa mem-banned user tertentu secara manual jika diperlukan
            Route::post('/admin/user/banned', [AttendanceController::class, 'banned'])->name('admin.user.banned');
        });

        // Info User
        Route::get('/user', function (Request $request) {
            return $request->user();
        })->name('user.profile');
    });
});
