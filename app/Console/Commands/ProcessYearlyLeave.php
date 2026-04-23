<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class ProcessYearlyLeave extends Command
{
    protected $signature = 'leave:process-yearly 
                            {--dry-run : Simulasi tanpa menyimpan perubahan}
                            {--user= : ID user spesifik yang akan diproses}';

    protected $description = 'Reset kuota cuti dan pindahkan sisa cuti ke saldo pencairan';

    public function handle()
    {
        $isDryRun = $this->option('dry-run');
        $specificUser = $this->option('user');

        if ($isDryRun) {
            $this->warn('===== DRY RUN MODE - Tidak ada perubahan yang disimpan =====');
        }

        // Query user yang aktif (role karyawan)
        $query = User::whereNotNull('join_date')
            ->whereHas('roles', fn($q) => $q->where('name', 'karyawan'));

        if ($specificUser) {
            $query->where('id', $specificUser);
        }

        $users = $query->get();

        $this->info("Memproses cuti tahunan...");
        $this->info("Total karyawan: " . $users->count());

        $processed = 0;
        $errors = 0;

        foreach ($users as $user) {
            try {
                $joinDate = Carbon::parse($user->join_date);
                $yearsWorked = $joinDate->diffInYears(now());

                if ($yearsWorked < 1) {
                    $this->line("  - {$user->name}: Belum genap 1 tahun kerja, dilewati.");
                    continue;
                }

                $oldQuota = $user->leave_quota;
                $oldCashable = $user->cashable_leave;

                // Sisa cuti tahun ini pindah ke cashable (maksimal 12 hari)
                $cashableToAdd = min($oldQuota, 12);
                $newCashable = $oldCashable + $cashableToAdd;

                // Maksimal cashable leave 24 hari (kebijakan perusahaan)
                $maxCashable = 24;
                $newCashable = min($newCashable, $maxCashable);

                if (!$isDryRun) {
                    $user->cashable_leave = $newCashable;
                    $user->leave_quota = 12;
                    $user->save();
                }

                $this->line("  ✓ {$user->name}:");
                $this->line("      - Masa kerja: {$yearsWorked} tahun");
                $this->line("      - Sisa cuti lama: {$oldQuota} hari");
                $this->line("      - Cashable bertambah: +{$cashableToAdd} hari");
                $this->line("      - Total cashable: {$newCashable} hari");
                $this->line("      - Kuota baru: 12 hari");

                // Log untuk audit (opsional)
                if (!$isDryRun) {
                    Log::info('Yearly leave processed', [
                        'user_id' => $user->id,
                        'user_name' => $user->name,
                        'old_quota' => $oldQuota,
                        'new_quota' => 12,
                        'cashable_added' => $cashableToAdd,
                        'total_cashable' => $newCashable,
                    ]);
                }

                $processed++;
            } catch (\Exception $e) {
                $this->error("  ✗ Error processing {$user->name}: " . $e->getMessage());
                $errors++;
            }
        }

        $this->newLine();
        $this->info("===== Selesai =====");
        $this->info("Berhasil diproses: {$processed} karyawan");
        $this->info("Gagal: {$errors} karyawan");

        if ($isDryRun) {
            $this->warn("===== DRY RUN MODE - Tidak ada perubahan yang disimpan =====");
        }
    }
}
