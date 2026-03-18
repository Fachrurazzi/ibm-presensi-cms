<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use Carbon\Carbon;

class ProcessYearlyLeave extends Command
{
    protected $signature = 'leave:process-yearly';
    protected $description = 'Reset kuota cuti dan pindahkan sisa cuti ke saldo pencairan berdasarkan tanggal masuk';

    public function handle()
    {
        $today = Carbon::today();

        // Cari user yang bulan dan tanggal masuknya SAMA dengan hari ini
        // dan sudah bekerja minimal 1 tahun
        $users = User::whereNotNull('join_date')
            ->whereMonth('join_date', $today->month)
            ->whereDay('join_date', $today->day)
            ->whereDate('join_date', '<', $today->copy()->subYear())
            ->get();

        foreach ($users as $user) {
            // PERBAIKAN: Gunakan '=' agar hanya menyimpan sisa cuti SATU TAHUN terakhir.
            // Jika ada saldo tahun sebelumnya yang belum dicairkan HRD, akan otomatis hangus (tertimpa sisa tahun ini).
            $user->cashable_leave = $user->leave_quota;

            // Reset jatah cuti tahunan menjadi 12
            $user->leave_quota = 12;
            $user->save();

            $this->info("Cuti diproses untuk: {$user->name}");
        }
        $this->info('Proses reset cuti tahunan selesai.');
    }
}
