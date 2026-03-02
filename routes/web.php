<?php

use App\Exports\AttendanceExport;
use App\Livewire\Presensi;
use Illuminate\Support\Facades\Route;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Http\Request;

Route::middleware('auth')->group(function () {
    // Halaman presensi bisa diakses semua karyawan yang login
    Route::get('presensi', Presensi::class)->name('presensi');

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

        $nameTag = $supervisor ? preg_replace('/[^A-Za-z0-9\-]/', '_', $supervisor) : 'Semua_Area';
        $fileName = "Rekap_Presensi_{$nameTag}_{$startDate}_sd_{$endDate}.xlsx";

        return Excel::download(new AttendanceExport($startDate, $endDate, $supervisor), $fileName);
    })->name('attendance-export');
});

// Redirect login agar seragam ke Filament
Route::get('/login', fn() => redirect('/admin/login'))->name('login');

Route::get('/', function () {
    // Bisa diarahkan langsung ke admin jika user sudah login
    return auth()->check() ? redirect('/admin') : view('welcome');
});
