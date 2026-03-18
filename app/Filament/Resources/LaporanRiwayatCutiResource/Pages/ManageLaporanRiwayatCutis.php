<?php

namespace App\Filament\Resources\LaporanRiwayatCutiResource\Pages;

use App\Filament\Resources\LaporanRiwayatCutiResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageLaporanRiwayatCutis extends ManageRecords
{
    protected static string $resource = LaporanRiwayatCutiResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
