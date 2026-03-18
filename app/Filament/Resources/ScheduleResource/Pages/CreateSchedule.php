<?php

namespace App\Filament\Resources\ScheduleResource\Pages;

use App\Filament\Resources\ScheduleResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateSchedule extends CreateRecord
{
    protected static string $resource = ScheduleResource::class;

    // CreateSchedule.php

    protected function handleRecordCreation(array $data): Model
    {
        // Ambil user_id dari data (pastikan namanya sesuai dengan Select::make('user_id'))
        $userIds = $data['user_id'] ?? [];

        // Hapus dari data agar tidak konflik saat create record utama jika model tidak punya kolom user_id
        // Tapi jika tabel 'schedules' punya kolom 'user_id', biarkan saja atau sesuaikan.
        unset($data['user_id']);

        $lastRecord = null;

        foreach ($userIds as $userId) {
            $lastRecord = ($this->getModel())::updateOrCreate(
                ['user_id' => $userId], // Mencari berdasarkan user_id
                $data                   // Update/Insert data lainnya (shift_id, office_id, dll)
            );
        }

        // Filament mewajibkan return sebuah Model
        return $lastRecord;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
