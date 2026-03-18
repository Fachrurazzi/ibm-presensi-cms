<?php

namespace App\Filament\Pages;

use App\Models\User;
use Filament\Pages\Page;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Concerns\InteractsWithTable;
use Illuminate\Support\HtmlString; // <-- INI SANGAT PENTING

class LeaveStats extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-chart-pie';
    protected static ?string $navigationGroup = 'Manajemen Absensi';
    protected static ?string $navigationLabel = 'Statistik Cuti';
    protected static ?string $title = 'Monitoring Sisa Cuti Karyawan';
    protected static ?int $navigationSort = 4;

    protected static string $view = 'filament.pages.leave-stats';

    public static function canAccess(): bool
    {
        // Hanya izinkan jika user yang sedang login memiliki role 'super_admin'
        return auth()->user()->hasRole('super_admin');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(User::query()->whereHas('roles', fn($q) => $q->where('name', 'karyawan')))
            ->columns([
                TextColumn::make('name')
                    ->label('Nama Karyawan')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('position.name')
                    ->label('Jabatan')
                    ->badge()
                    ->color('gray'),

                TextColumn::make('leave_quota')
                    ->label('Sisa Hari')
                    ->sortable()
                    ->weight('bold')
                    ->color(fn($state) => $state <= 2 ? 'danger' : 'success')
                    ->suffix(' Hari'),

                // PERBAIKAN: Menggunakan HtmlString dan Inline CSS untuk menjamin render visual
                TextColumn::make('progress')
                    ->label('Persentase Sisa')
                    ->getStateUsing(fn($record) => max(0, min(100, ($record->leave_quota / 12) * 100)))
                    ->formatStateUsing(function ($state) {
                        // Tentukan warna berdasarkan sisa persentase
                        $color = $state <= 20 ? '#ef4444' : '#10b981'; // Merah (danger) atau Hijau (success)

                        // Render HTML murni menggunakan HtmlString
                        return new HtmlString('
                            <div style="width: 100%; min-width: 120px; background-color: #e5e7eb; border-radius: 999px; height: 8px; margin-top: 6px; overflow: hidden;">
                                <div style="width: ' . $state . '%; background-color: ' . $color . '; height: 100%; border-radius: 999px; transition: width 0.5s ease;"></div>
                            </div>
                        ');
                    }),
            ])
            ->defaultSort('leave_quota', 'asc');
    }
}
