<?php

namespace App\Filament\Resources\LeaveResource\Pages;

use App\Filament\Resources\LeaveResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateLeave extends CreateRecord
{
    protected static string $resource = LeaveResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['user_id'] = Auth::id();
        $data['status'] = 'PENDING';

        // Hitung durasi
        $startDate = \Carbon\Carbon::parse($data['start_date']);
        $endDate = \Carbon\Carbon::parse($data['end_date']);
        $duration = $startDate->diffInDays($endDate) + 1;

        // Cek kuota cuti
        $user = \App\Models\User::find(Auth::id());
        if ($user && $duration > $user->getRemainingLeaveQuota()) {
            \Filament\Notifications\Notification::make()
                ->danger()
                ->title('Kuota Cuti Tidak Mencukupi')
                ->body("Sisa kuota cuti Anda: {$user->getRemainingLeaveQuota()} hari. Pengajuan: {$duration} hari.")
                ->send();

            $this->halt();
        }

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
