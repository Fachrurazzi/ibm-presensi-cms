<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use App\Models\User;
use App\Models\Leave; // Tambahkan ini
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\DatePicker;
use Illuminate\Support\Carbon;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),

            // TOMBOL POTONG CUTI BERSAMA
            Actions\Action::make('potongCutiBersama')
                ->label('Potong Cuti Bersama')
                ->color('warning')
                ->icon('heroicon-m-scissors')
                ->requiresConfirmation()
                ->modalHeading('Eksekusi Cuti Bersama')
                ->modalDescription('Aksi ini akan memotong saldo cuti seluruh karyawan dan otomatis membuat riwayat cuti (Disetujui) di akun mereka masing-masing.')
                ->form([
                    TextInput::make('days')
                        ->label('Jumlah Hari Dipotong')
                        ->numeric()
                        ->required()
                        ->default(1),

                    TextInput::make('reason')
                        ->label('Nama Event / Keterangan')
                        ->placeholder('Contoh: Cuti Bersama Idul Fitri 2026')
                        ->required(),

                    DatePicker::make('date')
                        ->label('Tanggal Pelaksanaan')
                        ->required()
                        ->default(now()),
                ])
                ->action(function (array $data) {
                    // Ambil semua user dengan role karyawan
                    $users = User::whereHas('roles', fn($q) => $q->where('name', 'karyawan'))->get();

                    $endDate = Carbon::parse($data['date'])->addDays($data['days'] - 1);

                    foreach ($users as $user) {
                        // 1. Potong sisa hari di profil user
                        $user->decrement('leave_quota', $data['days']);

                        // 2. Buat riwayat transparan di tabel Leave (Cuti/Izin)
                        Leave::create([
                            'user_id' => $user->id,
                            'start_date' => $data['date'],
                            'end_date' => $endDate,
                            'reason' => 'Cuti Bersama: ' . $data['reason'],
                            'status' => 'approved', // Langsung berstatus disetujui
                            'note' => 'Otomatis dipotong oleh sistem manajemen.',
                        ]);
                    }

                    \Filament\Notifications\Notification::make()
                        ->title('Berhasil!')
                        ->body("Sisa cuti {$users->count()} karyawan telah dipotong dan riwayat telah dibuat.")
                        ->success()
                        ->send();
                }),
        ];
    }
}
