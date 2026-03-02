<?php

namespace App\Filament\Resources\AttendanceResource\Pages;

use App\Filament\Resources\AttendanceResource;
use App\Models\Office;
use Filament\Actions;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;

class ListAttendances extends ListRecords
{
    protected static string $resource = AttendanceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Tombol Export: Hanya Admin/Super Admin yang bisa lihat
            Action::make('export')
                ->label('Export Rekap Mingguan')
                ->visible(fn() => auth()->user()->hasRole('super_admin'))
                ->color('success')
                ->icon('heroicon-o-document-arrow-down')
                ->modalHeading('Export Data Presensi per Area')
                ->form([
                    DatePicker::make('start')
                        ->label('Dari Tanggal')
                        ->required()
                        ->default(now()->startOfWeek()),
                    DatePicker::make('end')
                        ->label('Sampai Tanggal')
                        ->required()
                        ->default(now()->endOfWeek()),
                    Select::make('supervisor')
                        ->label('Area Supervisor')
                        ->placeholder('Pilih Supervisor (Kosongkan untuk Semua Area)')
                        ->options(fn() => Office::whereNotNull('supervisor_name')->pluck('supervisor_name', 'supervisor_name')->unique()->toArray())
                        ->searchable(),
                ])
                ->action(fn(array $data) => redirect()->route('attendance-export', [
                    'start' => $data['start'],
                    'end' => $data['end'],
                    'supervisor' => $data['supervisor'] ?? null,
                ])),

            // Tombol Presensi: Disembunyikan untuk Karyawan (karena sudah ada di Dashboard)
            Action::make('presensi')
                ->label('Tagging Presensi')
                ->visible(fn() => auth()->user()->hasRole('super_admin'))
                ->url(route('presensi'))
                ->extraAttributes([
                    'style' => 'background-color: #0000FF; color: white;'
                ])
                ->icon('heroicon-o-plus-circle'),

            // Tombol Tambah Manual: Hanya untuk Admin jika ada data yang terlewat
            Actions\CreateAction::make()
                ->label('Input Manual')
                ->visible(fn() => auth()->user()->hasRole('super_admin')),
        ];
    }
}
