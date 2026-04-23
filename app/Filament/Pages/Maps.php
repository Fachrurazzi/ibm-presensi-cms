<?php

namespace App\Filament\Pages;

use App\Models\Attendance;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;

class Maps extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-map-pin';
    protected static ?string $navigationGroup = 'Manajemen Absensi';
    protected static ?string $navigationLabel = 'Monitoring Lokasi';
    protected static ?string $title = 'Monitoring Lokasi';
    protected static ?int $navigationSort = 6;

    protected static string $view = 'filament.pages.maps';

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()->hasRole(['super_admin', 'admin']);
    }

    public static function getNavigationBadge(): ?string
    {
        $count = Attendance::whereDate('created_at', now())
            ->whereNotNull('start_latitude')
            ->count();
        
        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'success';
    }

    public function mount(): void
    {
        abort_unless(auth()->user()->hasRole(['super_admin', 'admin']), 403);
    }
}