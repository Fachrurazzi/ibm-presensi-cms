<?php

namespace App\Filament\Resources\LeaveResource\Pages;

use App\Filament\Resources\LeaveResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditLeave extends EditRecord
{
    protected static string $resource = LeaveResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $record = $this->getRecord();

        // ========== KASUS 1: PENDING -> APPROVED ==========
        if ($record->status === 'PENDING' && $data['status'] === 'APPROVED') {
            $user = $record->user;

            $startDate = \Carbon\Carbon::parse($data['start_date']);
            $endDate = \Carbon\Carbon::parse($data['end_date']);
            $duration = $startDate->diffInDays($endDate) + 1;

            // Validasi kuota
            if ($user->leave_quota < $duration) {
                \Filament\Notifications\Notification::make()
                    ->title('Kuota Cuti Tidak Cukup')
                    ->body("Karyawan hanya memiliki {$user->leave_quota} hari tersisa. Dibutuhkan {$duration} hari.")
                    ->danger()
                    ->send();

                $this->halt();
            }

            // Potong kuota
            $user->decrement('leave_quota', $duration);

            \Filament\Notifications\Notification::make()
                ->title('Cuti Disetujui')
                ->body("Kuota cuti {$user->name} dipotong {$duration} hari. Sisa: {$user->leave_quota} hari.")
                ->success()
                ->send();
        }

        // ========== KASUS 2: APPROVED -> REJECTED ==========
        if ($record->status === 'APPROVED' && $data['status'] === 'REJECTED') {
            $user = $record->user;

            $startDate = \Carbon\Carbon::parse($data['start_date']);
            $endDate = \Carbon\Carbon::parse($data['end_date']);
            $duration = $startDate->diffInDays($endDate) + 1;

            // Kembalikan kuota
            $user->increment('leave_quota', $duration);

            \Filament\Notifications\Notification::make()
                ->title('Cuti Ditolak')
                ->body("Kuota cuti {$user->name} dikembalikan {$duration} hari. Sisa: {$user->leave_quota} hari.")
                ->warning()
                ->send();
        }

        // ========== KASUS 3: APPROVED, tapi tanggal berubah ==========
        if ($record->status === 'APPROVED') {
            $oldStartDate = \Carbon\Carbon::parse($record->start_date);
            $oldEndDate = \Carbon\Carbon::parse($record->end_date);
            $newStartDate = \Carbon\Carbon::parse($data['start_date']);
            $newEndDate = \Carbon\Carbon::parse($data['end_date']);

            $oldDuration = $oldStartDate->diffInDays($oldEndDate) + 1;
            $newDuration = $newStartDate->diffInDays($newEndDate) + 1;
            $durationDiff = $newDuration - $oldDuration;

            if ($durationDiff != 0) {
                $user = $record->user;

                // Cek kuota jika durasi bertambah
                if ($durationDiff > 0 && $user->leave_quota < $durationDiff) {
                    \Filament\Notifications\Notification::make()
                        ->title('Kuota Cuti Tidak Cukup')
                        ->body("Karyawan hanya memiliki {$user->leave_quota} hari tersisa. Perubahan durasi membutuhkan tambahan {$durationDiff} hari.")
                        ->danger()
                        ->send();

                    $this->halt();
                }

                // Sesuaikan kuota
                if ($durationDiff > 0) {
                    $user->decrement('leave_quota', $durationDiff);
                } else {
                    $user->increment('leave_quota', abs($durationDiff));
                }

                \Filament\Notifications\Notification::make()
                    ->title('Durasi Cuti Diubah')
                    ->body("Kuota cuti {$user->name} disesuaikan. Perubahan: " . ($durationDiff > 0 ? "+{$durationDiff}" : $durationDiff) . " hari. Sisa: {$user->leave_quota} hari.")
                    ->info()
                    ->send();
            }
        }

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
