<?php

namespace App\Filament\Resources\LeaveResource\Pages;

use App\Filament\Resources\LeaveResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Auth;

class CreateLeave extends CreateRecord
{
    protected static string $resource = LeaveResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Memastikan user_id adalah ID user yang sedang login
        $data['user_id'] = Auth::id();

        // Memastikan status default saat create adalah pending
        $data['status'] = 'pending';

        return $data;
    }
}
