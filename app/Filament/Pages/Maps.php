<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;

class Maps extends Page
{
    // Mengganti icon agar sesuai dengan desain sidebar yang Anda inginkan
    protected static ?string $navigationIcon = 'heroicon-o-map-pin';

    protected static string $view = 'filament.pages.maps';

    protected static ?string $title = 'Monitoring Lokasi'; // Nama di breadcrumb lebih keren

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()->can('page_Maps');
    }

    public function mount(): void
    {
        // Tambahan keamanan: Tendang jika coba akses manual via URL
        abort_unless(auth()->user()->can('page_Maps'), 403);
    }
}
