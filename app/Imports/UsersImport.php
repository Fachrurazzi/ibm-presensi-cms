<?php

namespace App\Imports;

use App\Models\User;
use App\Models\Position;
use App\Models\Office;
use App\Models\Shift;
use App\Models\Schedule;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Maatwebsite\Excel\Validators\Failure;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UsersImport implements ToModel, WithHeadingRow, WithValidation, SkipsOnFailure
{
    use SkipsFailures;

    protected $cabangId;
    protected $overwrite;
    protected $rowCount = 0;
    protected $successCount = 0;
    protected $failCount = 0;
    protected $errors = [];
    protected $skipFirstRow = true;

    public function __construct($cabangId = null, $overwrite = false)
    {
        $this->cabangId = $cabangId;
        $this->overwrite = $overwrite;
    }

    public function model(array $row)
    {
        $this->rowCount++;

        try {
            DB::beginTransaction();

            // Cek apakah email sudah ada
            $existingUser = User::where('email', $row['email'])->first();

            if ($existingUser && !$this->overwrite) {
                $this->errors[] = "Baris {$this->rowCount}: Email {$row['email']} sudah ada. Gunakan mode timpa atau hapus manual.";
                $this->failCount++;
                DB::rollBack();
                return null;
            }

            // Dapatkan atau buat jabatan
            $position = Position::firstOrCreate(
                ['name' => $row['jabatan']],
                ['name' => $row['jabatan']]
            );

            // Dapatkan kantor
            $office = null;
            if ($this->cabangId) {
                $office = Office::find($this->cabangId);
            } else {
                $office = Office::where('name', $row['kantor'])->first();
            }

            if (!$office) {
                $this->errors[] = "Baris {$this->rowCount}: Kantor '{$row['kantor']}' tidak ditemukan.";
                $this->failCount++;
                DB::rollBack();
                return null;
            }

            // Dapatkan shift
            $shift = Shift::where('name', $row['shift'])->first();
            if (!$shift) {
                $this->errors[] = "Baris {$this->rowCount}: Shift '{$row['shift']}' tidak ditemukan.";
                $this->failCount++;
                DB::rollBack();
                return null;
            }

            // Buat atau update user
            $userData = [
                'name' => $row['nama'],
                'password' => Hash::make('password'),
                'position_id' => $position->id,
                'join_date' => Carbon::parse($row['tanggal_bergabung']),
                'leave_quota' => $row['sisa_cuti_awal'] ?? 12,
                'cashable_leave' => 0,
                'is_default_password' => true,
                'email_verified_at' => now(),
            ];

            if ($existingUser && $this->overwrite) {
                $user = $existingUser;
                $user->update($userData);
                $this->errors[] = "Baris {$this->rowCount}: Email {$row['email']} sudah ada, data ditimpa.";
            } else {
                $userData['email'] = $row['email'];
                $user = User::create($userData);
            }

            // Assign role karyawan
            if (!$user->hasRole('karyawan')) {
                $user->assignRole('karyawan');
            }

            // Buat schedule permanen untuk user
            Schedule::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'start_date' => $user->join_date->format('Y-m-d'),
                    'end_date' => null,
                ],
                [
                    'shift_id' => $shift->id,
                    'office_id' => $office->id,
                    'is_wfa' => false,
                    'is_banned' => false,
                ]
            );

            DB::commit();
            $this->successCount++;

            Log::info('User imported successfully', [
                'email' => $row['email'],
                'name' => $row['nama'],
                'office' => $office->name,
            ]);

            return $user;
        } catch (\Exception $e) {
            DB::rollBack();
            $this->errors[] = "Baris {$this->rowCount}: " . $e->getMessage();
            $this->failCount++;
            Log::error('User import failed', [
                'email' => $row['email'] ?? 'unknown',
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    public function rules(): array
    {
        return [
            'nama' => 'required|string|max:255',
            'email' => 'required|email',
            'jabatan' => 'required|string',
            'kantor' => 'required|string',
            'shift' => 'required|string',
            'tanggal_bergabung' => 'required|date',
            'sisa_cuti_awal' => 'nullable|integer|min:0|max:365',
        ];
    }

    public function customValidationMessages()
    {
        return [
            'nama.required' => 'Nama wajib diisi',
            'email.required' => 'Email wajib diisi',
            'email.email' => 'Format email tidak valid',
            'jabatan.required' => 'Jabatan wajib diisi',
            'kantor.required' => 'Kantor wajib diisi',
            'shift.required' => 'Shift wajib diisi',
            'tanggal_bergabung.required' => 'Tanggal bergabung wajib diisi',
            'tanggal_bergabung.date' => 'Format tanggal tidak valid (YYYY-MM-DD)',
            'sisa_cuti_awal.integer' => 'Sisa cuti awal harus berupa angka',
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
