<?php

namespace App\Filament\Resources\AttendanceResource\Pages;

use App\Filament\Resources\AttendanceResource;
use App\Models\Office;
use App\Models\User;
use Filament\Actions;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Actions as FormActions;
use Carbon\Carbon;

class ListAttendances extends ListRecords
{
    protected static string $resource = AttendanceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export')
                ->label('Export Rekap Presensi')
                ->visible(fn() => auth()->user()->hasRole('super_admin'))
                ->color('success')
                ->icon('heroicon-o-document-arrow-down')
                ->modalHeading('Export Data Presensi')
                ->modalWidth('lg')
                ->modalSubmitActionLabel('Export Sekarang')
                ->form([
                    // Shortcut buttons
                    FormActions::make([
                        FormActions\Action::make('this_week')
                            ->label('Minggu Ini')
                            ->action(function ($set) {
                                $set('start', Carbon::now()->startOfWeek()->format('Y-m-d'));
                                $set('end', Carbon::now()->endOfWeek()->format('Y-m-d'));
                            }),
                        FormActions\Action::make('last_week')
                            ->label('Minggu Lalu')
                            ->action(function ($set) {
                                $set('start', Carbon::now()->subWeek()->startOfWeek()->format('Y-m-d'));
                                $set('end', Carbon::now()->subWeek()->endOfWeek()->format('Y-m-d'));
                            }),
                        FormActions\Action::make('this_month')
                            ->label('Bulan Ini')
                            ->action(function ($set) {
                                $set('start', Carbon::now()->startOfMonth()->format('Y-m-d'));
                                $set('end', Carbon::now()->endOfMonth()->format('Y-m-d'));
                            }),
                    ])->fullWidth(),

                    DatePicker::make('start')
                        ->label('Dari Tanggal')
                        ->required()
                        ->default(now()->startOfWeek())
                        ->maxDate(now())
                        ->displayFormat('d/m/Y'),

                    DatePicker::make('end')
                        ->label('Sampai Tanggal')
                        ->required()
                        ->default(now()->endOfWeek())
                        ->maxDate(now())
                        ->minDate(fn($get) => $get('start'))
                        ->displayFormat('d/m/Y')
                        ->helperText('Maksimal rentang 31 hari'),

                    Select::make('supervisor')
                        ->label('Area Supervisor')
                        ->placeholder('Semua Area')
                        ->options(fn() => Office::whereNotNull('supervisor_name')
                            ->pluck('supervisor_name', 'supervisor_name')
                            ->unique()
                            ->toArray())
                        ->searchable(),

                    Select::make('office_id')
                        ->label('Cabang / Area')
                        ->placeholder('Semua Cabang')
                        ->options(fn() => Office::pluck('name', 'id')->toArray())
                        ->searchable(),

                    Select::make('user_id')
                        ->label('Karyawan Tertentu')
                        ->placeholder('Semua Karyawan')
                        ->options(fn() => User::pluck('name', 'id')->toArray())
                        ->searchable(),
                ])
                ->action(function (array $data) {
                    $start = Carbon::parse($data['start']);
                    $end = Carbon::parse($data['end']);

                    // Validasi maksimal 31 hari
                    if ($start->diffInDays($end) > 31) {
                        \Filament\Notifications\Notification::make()
                            ->danger()
                            ->title('Range Tanggal Terlalu Panjang')
                            ->body('Maksimal export data adalah 31 hari. Silakan pilih rentang tanggal yang lebih pendek.')
                            ->send();
                        return;
                    }

                    return redirect()->route('attendance-export', [
                        'start' => $data['start'],
                        'end' => $data['end'],
                        'supervisor' => $data['supervisor'] ?? null,
                        'office_id' => $data['office_id'] ?? null,
                        'user_id' => $data['user_id'] ?? null,
                    ]);
                }),

            Action::make('presensi')
                ->label('Tagging Presensi')
                ->visible(fn() => auth()->user()->hasRole('super_admin'))
                ->color('info')
                ->icon('heroicon-o-plus-circle')
                ->tooltip('Manual tagging absensi karyawan')
                ->url(route('presensi')),

            Actions\CreateAction::make()
                ->label('Input Manual')
                ->visible(fn() => auth()->user()->hasRole('super_admin'))
                ->tooltip('Input manual data presensi karyawan'),
        ];
    }
}
