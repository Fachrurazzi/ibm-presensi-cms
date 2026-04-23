<?php
// app/Imports/SchedulesImport.php

namespace App\Imports;

use App\Models\User;
use App\Models\Shift;
use App\Models\Office;
use App\Models\Schedule;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class SchedulesImport implements ToModel, WithHeadingRow, WithValidation, SkipsOnFailure
{
    use SkipsFailures;

    protected $overwrite;
    protected $rowCount = 0;
    protected $successCount = 0;
    protected $failCount = 0;
    protected $errors = [];

    public function __construct($overwrite = false)
    {
        $this->overwrite = $overwrite;
    }

    public function model(array $row)
    {
        $this->rowCount++;

        try {
            DB::beginTransaction();

            // Cek user berdasarkan email
            $user = User::where('email', $row['email_karyawan'])->first();
            if (!$user) {
                $this->errors[] = "Baris {$this->rowCount}: Email '{$row['email_karyawan']}' tidak ditemukan.";
                $this->failCount++;
                DB::rollBack();
                return null;
            }

            // Cek shift
            $shift = Shift::where('name', $row['shift'])->first();
            if (!$shift) {
                $this->errors[] = "Baris {$this->rowCount}: Shift '{$row['shift']}' tidak ditemukan.";
                $this->failCount++;
                DB::rollBack();
                return null;
            }

            // Cek kantor
            $office = Office::where('name', $row['kantor'])->first();
            if (!$office) {
                $this->errors[] = "Baris {$this->rowCount}: Kantor '{$row['kantor']}' tidak ditemukan.";
                $this->failCount++;
                DB::rollBack();
                return null;
            }

            $startDate = Carbon::parse($row['tanggal_mulai']);
            $endDate = !empty($row['tanggal_selesai']) ? Carbon::parse($row['tanggal_selesai']) : null;
            $isWfa = strtolower($row['wfa'] ?? 'tidak') === 'ya';

            // Cek apakah ada schedule yang overlap
            $overlap = Schedule::where('user_id', $user->id)
                ->where(function ($q) use ($startDate, $endDate) {
                    $q->where(function ($sub) use ($startDate, $endDate) {
                        $sub->whereNull('end_date')
                            ->orWhere('end_date', '>=', $startDate);
                    })->where('start_date', '<=', $endDate ?? '9999-12-31');
                })
                ->exists();

            if ($overlap && !$this->overwrite) {
                $this->errors[] = "Baris {$this->rowCount}: Schedule untuk {$user->name} overlap dengan schedule yang sudah ada.";
                $this->failCount++;
                DB::rollBack();
                return null;
            }

            if ($overlap && $this->overwrite) {
                // Hapus schedule lama yang overlap
                Schedule::where('user_id', $user->id)
                    ->where(function ($q) use ($startDate, $endDate) {
                        $q->where(function ($sub) use ($startDate, $endDate) {
                            $sub->whereNull('end_date')
                                ->orWhere('end_date', '>=', $startDate);
                        })->where('start_date', '<=', $endDate ?? '9999-12-31');
                    })
                    ->delete();
            }

            // Buat schedule baru
            Schedule::create([
                'user_id' => $user->id,
                'shift_id' => $shift->id,
                'office_id' => $office->id,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'is_wfa' => $isWfa,
                'is_banned' => false,
            ]);

            DB::commit();
            $this->successCount++;

        } catch (\Exception $e) {
            DB::rollBack();
            $this->errors[] = "Baris {$this->rowCount}: " . $e->getMessage();
            $this->failCount++;
        }

        return null;
    }

    public function rules(): array
    {
        return [
            'email_karyawan' => 'required|email',
            'shift' => 'required|string',
            'kantor' => 'required|string',
            'tanggal_mulai' => 'required|date',
            'tanggal_selesai' => 'nullable|date|after_or_equal:tanggal_mulai',
            'wfa' => 'required|in:Ya,Tidak,ya,tidak',
        ];
    }

    public function customValidationMessages()
    {
        return [
            'email_karyawan.required' => 'Email karyawan wajib diisi',
            'email_karyawan.email' => 'Format email tidak valid',
            'shift.required' => 'Shift wajib diisi',
            'kantor.required' => 'Kantor wajib diisi',
            'tanggal_mulai.required' => 'Tanggal mulai wajib diisi',
            'tanggal_mulai.date' => 'Format tanggal mulai tidak valid',
            'tanggal_selesai.date' => 'Format tanggal selesai tidak valid',
            'tanggal_selesai.after_or_equal' => 'Tanggal selesai harus setelah atau sama dengan tanggal mulai',
            'wfa.required' => 'Kolom WFA wajib diisi',
            'wfa.in' => 'WFA harus diisi "Ya" atau "Tidak"',
        ];
    }

    public function getSuccessCount(): int
    {
        return $this->successCount;
    }

    public function getFailCount(): int
    {
        return $this->failCount;
    }

    public function getTotalRows(): int
    {
        return $this->rowCount;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }
}