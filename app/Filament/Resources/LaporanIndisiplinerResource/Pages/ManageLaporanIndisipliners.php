<?php

namespace App\Filament\Resources\LaporanIndisiplinerResource\Pages;

use App\Filament\Resources\LaporanIndisiplinerResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageLaporanIndisipliners extends ManageRecords
{
    protected static string $resource = LaporanIndisiplinerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
