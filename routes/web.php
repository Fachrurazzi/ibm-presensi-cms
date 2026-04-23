    <?php

    use App\Exports\AttendanceExport;
    use App\Livewire\Presensi;
    use Illuminate\Support\Facades\Route;
    use Maatwebsite\Excel\Facades\Excel;
    use Illuminate\Http\Request;
    use App\Models\Attendance;
    use Illuminate\Support\Facades\DB;

    Route::get('/debug-att', function () {
        if (!auth()->check()) {
            return 'Silakan login dulu sebagai admin';
        }

        $user = auth()->user();
        $role = $user->roles->pluck('name')->first();

        $query = Attendance::with(['user', 'schedule.office']);

        // Simulasi filter di resource
        if (!in_array($role, ['super_admin', 'admin'])) {
            $query->where('user_id', $user->id);
        }

        $attendances = $query->get();

        return [
            'user_id' => $user->id,
            'user_name' => $user->name,
            'user_role' => $role,
            'is_admin' => in_array($role, ['super_admin', 'admin']),
            'total_attendance' => $attendances->count(),
            'attendance_data' => $attendances->map(fn($a) => [
                'id' => $a->id,
                'user_name' => $a->user?->name,
                'office_name' => $a->schedule?->office?->name,
                'start_time' => $a->start_time?->format('Y-m-d H:i'),
                'schedule_id' => $a->schedule_id,
            ])
        ];
    })->middleware('auth');

    Route::middleware('auth')->group(function () {
        // Halaman presensi bisa diakses semua karyawan yang login
        Route::get('presensi', Presensi::class)->name('presensi');

        // PROTEKSI: Hanya admin/super_admin yang bisa mengakses URL export
        // PROTEKSI: Hanya admin/super_admin yang bisa mengakses URL export
        Route::get('attendance/export', function (Request $request) {
            // Cek Role secara manual jika tidak ingin pakai middleware tambahan
            if (!auth()->user()->hasRole(['super_admin', 'admin'])) {
                return abort(403, 'Anda tidak memiliki akses untuk mengekspor data.');
            }

            if (!$request->has(['start', 'end'])) {
                return abort(400, 'Parameter tanggal mulai dan selesai wajib diisi.');
            }

            $startDate = $request->query('start');
            $endDate = $request->query('end');
            $supervisor = $request->query('supervisor');

            // --- TAMBAHAN: Tangkap filter Cabang dan Karyawan ---
            $officeId = $request->query('office_id');
            $userId = $request->query('user_id');

            // --- OPTIONAL: Modifikasi penamaan file agar lebih rapi ---
            $nameTag = 'Semua_Data';
            if ($supervisor) {
                $nameTag = 'Area_' . preg_replace('/[^A-Za-z0-9\-]/', '_', $supervisor);
            } elseif ($userId) {
                // Jika filter per karyawan, ambil nama user langsung dari database untuk nama file
                $user = \App\Models\User::find($userId);
                $nameTag = 'Karyawan_' . preg_replace('/[^A-Za-z0-9\-]/', '_', $user->name ?? $userId);
            } elseif ($officeId) {
                $office = \App\Models\Office::find($officeId);
                $nameTag = 'Cabang_' . preg_replace('/[^A-Za-z0-9\-]/', '_', $office->name ?? $officeId);
            }

            $fileName = "Rekap_Presensi_{$nameTag}_{$startDate}_sd_{$endDate}.xlsx";

            // --- PERBAIKAN: Masukkan 5 variabel ke dalam AttendanceExport ---
            return Excel::download(
                new AttendanceExport($startDate, $endDate, $supervisor, $userId, $officeId),
                $fileName
            );
        })->name('attendance-export');
    });

    // Redirect login agar seragam ke Filament
    Route::get('/login', fn() => redirect('/admin/login'))->name('login');

    Route::get('/', function () {
        // Bisa diarahkan langsung ke admin jika user sudah login
        return auth()->check() ? redirect('/admin') : view('welcome');
    });
