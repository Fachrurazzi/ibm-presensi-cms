<?php

namespace App\Filament\Resources\LaporanAbsensiResource\Pages;

use App\Filament\Resources\LaporanAbsensiResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageLaporanAbsensis extends ManageRecords
{
    protected static string $resource = LaporanAbsensiResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
