<?php

namespace App\Filament\Resources\LeaveResource\Pages;

use App\Filament\Resources\LeaveResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\DB;

class EditLeave extends EditRecord
{
    protected static string $resource = LeaveResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Ambil data sebelum diupdate
        $record = $this->getRecord();

        // Logika: Jika status berubah dari 'pending' menjadi 'approved'
        if ($record->status === 'pending' && $data['status'] === 'approved') {

            $user = $record->user;

            // Hitung selisih hari cuti
            $startDate = \Carbon\Carbon::parse($data['start_date']);
            $endDate = \Carbon\Carbon::parse($data['end_date']);
            $duration = $startDate->diffInDays($endDate) + 1;

            // Validasi: Cek apakah kuota cukup
            if ($user->leave_quota < $duration) {
                \Filament\Notifications\Notification::make()
                    ->title('Kuota Cuti Tidak Cukup')
                    ->body("Karyawan hanya memiliki {$user->leave_quota} hari tersisa.")
                    ->danger()
                    ->send();

                $this->halt(); // Batalkan proses save
            }

            // Potong Kuota
            $user->decrement('leave_quota', $duration);
        }

        return $data;
    }
}
