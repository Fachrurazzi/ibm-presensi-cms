<?php
// app/Filament/Resources/AttendancePermissionResource/Pages/EditAttendancePermission.php

namespace App\Filament\Resources\AttendancePermissionResource\Pages;

use App\Filament\Resources\AttendancePermissionResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditAttendancePermission extends EditRecord
{
    protected static string $resource = AttendancePermissionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
