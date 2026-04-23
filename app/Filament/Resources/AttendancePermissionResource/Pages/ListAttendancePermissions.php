<?php
// app/Filament/Resources/AttendancePermissionResource/Pages/ListAttendancePermissions.php

namespace App\Filament\Resources\AttendancePermissionResource\Pages;

use App\Filament\Resources\AttendancePermissionResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListAttendancePermissions extends ListRecords
{
    protected static string $resource = AttendancePermissionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
