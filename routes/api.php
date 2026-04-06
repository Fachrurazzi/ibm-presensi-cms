    <?php

    use App\Http\Controllers\API\AttendanceController;
    use App\Http\Controllers\API\AuthController;
    use App\Http\Controllers\API\LeaveController;
    use App\Http\Controllers\API\ProfileController;
    use Illuminate\Support\Facades\Route;
    use Illuminate\Support\Facades\Response;

    /*
    |--------------------------------------------------------------------------
    | API Routes - PT INTIBOGA MANDIRI
    |--------------------------------------------------------------------------
    */

    // --- Public Routes ---
    Route::prefix('v1')->group(function () {
        Route::post('/login', [AuthController::class, 'login'])->name('login');
    });

    // --- Protected Routes (Sanctum) ---
    Route::middleware('auth:sanctum')->group(function () {
        Route::prefix('v1')->group(function () {

            // --- AUTH & PROFILE ---
            Route::post('/logout', [AuthController::class, 'logout']);
            Route::post('/profile/update', [ProfileController::class, 'update']);
            Route::get('/profile/photo', [ProfileController::class, 'showPhoto']);

            // --- ABSENSI (ATTENDANCE) ---
            Route::controller(AttendanceController::class)->group(function () {
                Route::get('/today', 'getAttendanceToday');
                Route::get('/schedule', 'getSchedule');
                Route::post('/store', 'store');
                Route::get('/history/{month}/{year}', 'getAttendanceByMonthAndYear');
                Route::post('/banned', 'banned');
            });

            // --- CUTI (LEAVES) ---
            Route::prefix('leaves')->controller(LeaveController::class)->group(function () {
                Route::get('/', 'index');
                Route::post('/', 'store');
                Route::middleware('is_admin')->post('/{id}/status', 'updateStatus');
            });
        });
    });

    /**
     * ROUTE AKSES GAMBAR (LOGIKA SAKTI)
     * Diletakkan di level 'api/storage/...' agar sesuai dengan AppConfig.STORAGE_URL di Flutter
     */
    Route::get('/storage/{path}', function ($path) {
        // Jalur absolut
        $fullPath = storage_path('app/public/' . $path);

        // KUNCI: Bersihkan cache status file Linux
        clearstatcache(true, $fullPath);

        if (!file_exists($fullPath)) {
            return response()->json([
                'success' => false,
                'error' => 'File tidak ditemukan di server fisik',
                'path_debug' => $fullPath,
            ], 404);
        }

        $file = file_get_contents($fullPath);
        $type = mime_content_type($fullPath);

        if (ob_get_level()) ob_end_clean();

        // REVISI HEADER: Set ke no-cache agar Flutter yang memegang kendali cache
        return Response::make($file, 200)
            ->header("Content-Type", $type)
            ->header("Cache-Control", "no-cache, no-store, must-revalidate")
            ->header("Pragma", "no-cache")
            ->header("Expires", "0");
    })->where('path', '.*');
