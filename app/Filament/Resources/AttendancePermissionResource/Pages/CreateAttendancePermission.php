<?php
// app/Filament/Resources/AttendancePermissionResource/Pages/CreateAttendancePermission.php

namespace App\Filament\Resources\AttendancePermissionResource\Pages;

use App\Filament\Resources\AttendancePermissionResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateAttendancePermission extends CreateRecord
{
    protected static string $resource = AttendancePermissionResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['user_id'] = Auth::id();
        $data['status'] = 'PENDING';

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
