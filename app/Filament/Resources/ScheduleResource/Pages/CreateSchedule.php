<?php

namespace App\Filament\Resources\ScheduleResource\Pages;

use App\Filament\Resources\ScheduleResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class CreateSchedule extends CreateRecord
{
    protected static string $resource = ScheduleResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Set default end_date jika kosong (berlaku selamanya)
        if (empty($data['end_date'])) {
            $data['end_date'] = null;
        }

        return $data;
    }

    protected function handleRecordCreation(array $data): Model
    {
        // Cek apakah ada multiple user (dari form sebelumnya)
        $userIds = $data['user_id'] ?? [];
        
        // Jika multiple user, proses satu per satu
        if (is_array($userIds) && count($userIds) > 0) {
            unset($data['user_id']);
            
            $lastRecord = null;
            
            foreach ($userIds as $userId) {
                // Cek apakah sudah ada schedule untuk periode yang sama
                $existing = $this->getModel()::where('user_id', $userId)
                    ->where('start_date', '<=', $data['end_date'] ?? '9999-12-31')
                    ->where(function ($q) use ($data) {
                        $q->whereNull('end_date')
                          ->orWhere('end_date', '>=', $data['start_date']);
                    })
                    ->first();
                
                if ($existing) {
                    // Update existing schedule jika overlap
                    $existing->update(array_merge($data, [
                        'user_id' => $userId,
                    ]));
                    $lastRecord = $existing;
                } else {
                    // Buat schedule baru
                    $lastRecord = $this->getModel()::create(array_merge($data, [
                        'user_id' => $userId,
                    ]));
                }
            }
            
            return $lastRecord;
        }
        
        // Single user (mode baru)
        return parent::handleRecordCreation($data);
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}