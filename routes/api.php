<?php

use App\Http\Controllers\API\AttendanceController;
use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\DashboardController;
use App\Http\Controllers\API\LeaveController;
use App\Http\Controllers\API\OfficeController;
use App\Http\Controllers\API\PermissionController;
use App\Http\Controllers\API\PositionController;
use App\Http\Controllers\API\ScheduleController;
use App\Http\Controllers\API\ShiftController;
use App\Http\Controllers\API\UserController;
use App\Http\Controllers\API\UserLocationController;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes (v1)
|--------------------------------------------------------------------------
*/

Route::prefix('v1')->group(function () {

    // ========== PUBLIC ROUTES (Dengan Rate Limiting) ==========
    Route::middleware('throttle:10,1')->group(function () {
        Route::post('/login', [AuthController::class, 'login'])->name('login');
        Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
        Route::post('/reset-password', [AuthController::class, 'resetPassword']);
    });

    // ========== PUBLIC READ-ONLY ROUTES (untuk dropdown) ==========
    Route::get('/offices', [OfficeController::class, 'index']);
    Route::get('/offices/{id}', [OfficeController::class, 'show']);
    Route::get('/offices/nearest', [OfficeController::class, 'nearest']);
    Route::get('/positions', [PositionController::class, 'index']);
    Route::get('/shifts', [ShiftController::class, 'index']);
    Route::get('/shifts/{id}', [ShiftController::class, 'show']);

    // ========== PROTECTED ROUTES (SANCTUM) ==========
    Route::middleware('auth:sanctum')->group(function () {

        // ----- AUTH & PROFILE -----
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::post('/logout-all', [AuthController::class, 'logoutAllDevices']);
        Route::put('/password', [AuthController::class, 'updatePassword']);
        Route::post('/register-face', [AuthController::class, 'registerFace']);
        Route::post('/resend-verification', [AuthController::class, 'resendVerification']);

        // ----- DASHBOARD -----
        Route::prefix('dashboard')->controller(DashboardController::class)->group(function () {
            Route::get('/stats', 'stats');
            Route::get('/monthly-summary', 'monthlySummary');
            Route::get('/recent-activities', 'recentActivities');
        });

        // ----- USER PROFILE -----
        Route::prefix('user')->controller(UserController::class)->group(function () {
            Route::get('/profile', 'profile');
            Route::put('/', 'update');
            Route::get('/photo', 'showPhoto');
            Route::delete('/photo', 'deletePhoto');
            Route::get('/schedule', 'schedule');
            Route::get('/schedule/today', 'todaySchedule');
            Route::get('/leave-summary', 'leaveSummary');
            Route::put('/fcm-token', 'updateFCMToken');
            Route::post('/location', 'updateLocation');
        });

        // ----- USER LOCATION TRACKING (Admin & Manager) -----
        Route::prefix('user-locations')->controller(UserLocationController::class)->group(function () {
            Route::get('/all', 'getAllUserLocations');      // Admin only
            Route::get('/team', 'getTeamLocations');        // Manager only
            Route::get('/{userId}', 'getUserLocation');     // Single user
        });

        // ----- ATTENDANCE -----
        Route::prefix('attendance')->controller(AttendanceController::class)->group(function () {
            Route::get('/today', 'getAttendanceToday');
            Route::get('/history', 'history');
            Route::get('/schedule', 'getSchedule');
            Route::get('/summary', 'summary');
            Route::post('/', 'store');
            Route::post('/report-suspicious', 'reportSuspiciousActivity');
        });

        // ----- LEAVES (CUTI) -----
        Route::prefix('leaves')->controller(LeaveController::class)->group(function () {
            Route::get('/', 'index');
            Route::get('/types', 'types');
            Route::get('/quota', 'quota');
            Route::get('/{id}', 'show');
            Route::post('/', 'store');
            Route::delete('/{id}', 'destroy');
            Route::patch('/{id}/status', 'updateStatus');
        });

        // ----- PERMISSIONS (IZIN) -----
        Route::prefix('permissions')->controller(PermissionController::class)->group(function () {
            Route::get('/', 'index');
            Route::get('/types', 'types');
            Route::get('/check', 'check');
            Route::get('/{id}', 'show');
            Route::post('/', 'store');
            Route::delete('/{id}', 'destroy');
            Route::patch('/{id}/status', 'updateStatus');
        });

        // ----- SCHEDULES -----
        Route::prefix('schedules')->controller(ScheduleController::class)->group(function () {
            Route::get('/', 'index');                    // Admin only
            Route::get('/{id}', 'show');                 // Admin/User sendiri
            Route::get('/user/{userId}', 'byUser');      // Admin/User sendiri
            Route::post('/', 'store');                   // Admin only
            Route::put('/{id}', 'update');               // Admin only
            Route::delete('/{id}', 'destroy');           // Admin only
            Route::patch('/{id}/ban', 'ban');            // Admin only
            Route::patch('/{id}/unban', 'unban');        // Admin only
        });

        // ========== ADMIN ONLY ROUTES ==========
        Route::middleware('role:admin,super_admin')->group(function () {

            // ----- OFFICES -----
            Route::prefix('offices')->controller(OfficeController::class)->group(function () {
                Route::post('/', 'store');
                Route::put('/{id}', 'update');
                Route::delete('/{id}', 'destroy');
                Route::post('/check-radius', 'checkRadius');
            });

            // ----- POSITIONS -----
            Route::prefix('positions')->controller(PositionController::class)->group(function () {
                Route::post('/', 'store');
                Route::put('/{id}', 'update');
                Route::delete('/{id}', '404');  // Fixed typo: 'destroy' bukan '404'
            });

            // ----- SHIFTS -----
            Route::prefix('shifts')->controller(ShiftController::class)->group(function () {
                Route::post('/', 'store');
                Route::put('/{id}', 'update');
                Route::delete('/{id}', 'destroy');
            });
        });

        // ========== MANAGER ONLY ROUTES ==========
        Route::middleware('role:admin,super_admin,manager')->group(function () {
            // Additional manager-specific routes can be added here
            // Contoh: approval routes sudah ada di leaves dan permissions
        });
    });
});

// ========== STORAGE ROUTE (untuk akses file) ==========
Route::get('/storage/{path}', function ($path) {
    // Cegah path traversal
    if (str_contains($path, '..') || str_contains($path, './')) {
        abort(404);
    }

    $fullPath = storage_path('app/public/' . $path);

    if (!File::exists($fullPath) || !File::isFile($fullPath)) {
        return response()->json([
            'success' => false,
            'message' => 'File tidak ditemukan'
        ], 404);
    }

    return response()->file($fullPath, [
        'Content-Type' => File::mimeType($fullPath),
        'Cache-Control' => 'max-age=86400, public',
    ]);
})->where('path', '.*');
