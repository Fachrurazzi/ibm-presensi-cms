<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ScheduleResource\Pages;
use App\Models\Schedule;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Filament\Tables\Actions\Action;
use Filament\Notifications\Notification;
use App\Imports\SchedulesImport;
use App\Exports\ScheduleTemplateExport;
use Maatwebsite\Excel\Facades\Excel;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Toggle;

class ScheduleResource extends Resource
{
    protected static ?string $model = Schedule::class;
    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';
    protected static ?string $navigationGroup = 'Manajemen Absensi';
    protected static ?int $navigationSort = 3;

    public static function getModelLabel(): string
    {
        return 'Jadwal Kerja';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Jadwal Kerja';
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::count();
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'success';
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Group::make()
                    ->schema([
                        Forms\Components\Section::make('Penugasan Karyawan')
                            ->description('Hubungkan karyawan dengan shift dan lokasi kantor.')
                            ->icon('heroicon-m-user-group')
                            ->schema([
                                Forms\Components\DatePicker::make('start_date')
                                    ->label('Mulai Berlaku')
                                    ->required()
                                    ->default(now())
                                    ->native(false)
                                    ->displayFormat('d M Y')
                                    ->helperText('Tanggal mulai berlakunya jadwal ini'),

                                Forms\Components\DatePicker::make('end_date')
                                    ->label('Sampai Tanggal')
                                    ->nullable()
                                    ->native(false)
                                    ->displayFormat('d M Y')
                                    ->afterOrEqual('start_date')
                                    ->helperText('Kosongkan jika berlaku seterusnya'),

                                Forms\Components\Select::make('user_id')
                                    ->label('Karyawan')
                                    ->relationship('user', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->columnSpanFull(),

                                Forms\Components\Select::make('shift_id')
                                    ->label('Shift Kerja')
                                    ->relationship('shift', 'name')
                                    ->required()
                                    ->preload()
                                    ->native(false),

                                Forms\Components\Select::make('office_id')
                                    ->label('Lokasi Penempatan')
                                    ->relationship('office', 'name')
                                    ->required()
                                    ->preload()
                                    ->native(false),
                            ])->columns(2),

                        Forms\Components\Section::make('Kebijakan Absensi')
                            ->icon('heroicon-m-adjustments-horizontal')
                            ->schema([
                                Forms\Components\Toggle::make('is_wfa')
                                    ->label('Izin WFA')
                                    ->helperText('Bisa absen dari mana saja (radius diabaikan).')
                                    ->onColor('success'),

                                Forms\Components\Toggle::make('is_banned')
                                    ->label('Blokir Absensi')
                                    ->helperText('Karyawan tidak akan bisa menekan tombol absen.')
                                    ->onColor('danger'),

                                Forms\Components\Textarea::make('banned_reason')
                                    ->label('Alasan Blokir')
                                    ->nullable()
                                    ->rows(2)
                                    ->columnSpanFull()
                                    ->visible(fn(Forms\Get $get) => $get('is_banned')),
                            ])->columns(2),
                    ])->columnSpan(['lg' => 2]),

                Forms\Components\Section::make('Audit Log')
                    ->schema([
                        Forms\Components\Placeholder::make('created_at')
                            ->label('Dibuat pada')
                            ->content(fn(?Schedule $record): string => $record ? $record->created_at->format('d M Y H:i') : '-'),
                        Forms\Components\Placeholder::make('updated_at')
                            ->label('Update Terakhir')
                            ->content(fn(?Schedule $record): string => $record ? $record->updated_at->diffForHumans() : '-'),
                        Forms\Components\Placeholder::make('date_range_display')
                            ->label('Periode')
                            ->content(fn(?Schedule $record): string => $record ? $record->date_range_display : '-'),
                    ])->columnSpan(['lg' => 1])
                    ->hidden(fn(?Schedule $record) => $record === null),
            ])->columns(3);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function (Builder $query) {
                $query->with(['user', 'shift', 'office']);
                if (!auth()->user()->hasRole(['super_admin', 'admin'])) {
                    $query->where('user_id', auth()->id());
                }
            })
            ->defaultSort('start_date', 'desc')
            ->headerActions([
                // ========== TOMBOL DOWNLOAD TEMPLATE ==========
                Action::make('download_template')
                    ->label('Download Template Excel')
                    ->icon('heroicon-m-document-arrow-down')
                    ->color('gray')
                    ->action(function () {
                        return Excel::download(new ScheduleTemplateExport(), 'template_import_jadwal.xlsx');
                    }),

                // ========== TOMBOL IMPORT ==========
                Action::make('import')
                    ->label('Import Excel')
                    ->icon('heroicon-m-arrow-up-tray')
                    ->color('success')
                    ->modalHeading('Import Data Jadwal Kerja')
                    ->modalDescription('Upload file Excel yang sudah diisi sesuai template')
                    ->modalWidth('lg')
                    ->form([
                        FileUpload::make('file')
                            ->label('File Excel')
                            ->required()
                            ->acceptedFileTypes([
                                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                                'application/vnd.ms-excel'
                            ])
                            ->disk('local')
                            ->directory('imports')
                            ->visibility('private')
                            ->helperText('Format yang diterima: .xlsx, .xls'),

                        Toggle::make('overwrite')
                            ->label('Timpa schedule yang overlap')
                            ->default(false)
                            ->helperText('Jika diaktifkan, schedule lama yang overlap akan dihapus dan diganti dengan yang baru.'),
                    ])
                    ->action(function (array $data) {
                        try {
                            $import = new SchedulesImport($data['overwrite'] ?? false);
                            Excel::import($import, $data['file']);

                            $notification = Notification::make()
                                ->title('Import Selesai!')
                                ->body("✅ Berhasil: {$import->getSuccessCount()} data\n❌ Gagal: {$import->getFailCount()} data\n📊 Total: {$import->getTotalRows()} data");

                            if ($import->getSuccessCount() > 0) {
                                $notification->success();
                            } elseif ($import->getFailCount() > 0) {
                                $notification->warning();
                            } else {
                                $notification->info();
                            }

                            $notification->send();

                            // Tampilkan error detail jika ada
                            $errors = $import->getErrors();
                            if (!empty($errors)) {
                                Notification::make()
                                    ->title('Detail Error')
                                    ->body(implode("\n", array_slice($errors, 0, 10)))
                                    ->danger()
                                    ->send();
                            }
                        } catch (\Maatwebsite\Excel\Validators\ValidationException $e) {
                            $failures = $e->failures();
                            $errorMessages = [];
                            foreach ($failures as $failure) {
                                $errorMessages[] = "Baris {$failure->row()}: " . implode(', ', $failure->errors());
                            }

                            Notification::make()
                                ->title('Validasi Gagal!')
                                ->body(implode("\n", array_slice($errorMessages, 0, 10)))
                                ->danger()
                                ->send();
                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Import Gagal')
                                ->body('Terjadi kesalahan: ' . $e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
            ])
            ->columns([
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Karyawan')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->description(fn(Schedule $record): string => $record->user->email),

                Tables\Columns\TextColumn::make('shift.name')
                    ->label('Shift')
                    ->badge()
                    ->color('info')
                    ->description(fn(Schedule $record): string =>
                    $record->shift->start_time_display . ' - ' . $record->shift->end_time_display),

                Tables\Columns\TextColumn::make('office.name')
                    ->label('Kantor')
                    ->icon('heroicon-m-map-pin')
                    ->description(fn(Schedule $record): string => $record->is_wfa ? '🔓 Mode WFA' : '📍 Mode On-Site'),

                Tables\Columns\TextColumn::make('start_date')
                    ->label('Mulai')
                    ->date('d M Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('end_date')
                    ->label('Sampai')
                    ->date('d M Y')
                    ->placeholder('🔁 Permanen')
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Status')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger')
                    ->getStateUsing(fn($record) => $record->is_active)
                    ->tooltip('Status schedule apakah sedang aktif'),

                Tables\Columns\IconColumn::make('is_banned')
                    ->label('Blokir')
                    ->boolean()
                    ->trueIcon('heroicon-o-x-circle')
                    ->falseIcon('heroicon-o-check-circle')
                    ->trueColor('danger')
                    ->falseColor('success')
                    ->alignCenter()
                    ->tooltip('Jika diblokir, karyawan tidak bisa absen'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('office_id')
                    ->label('Kantor')
                    ->relationship('office', 'name'),

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Status Aktif')
                    ->queries(
                        true: fn(Builder $query) => $query->active(),
                        false: fn(Builder $query) => $query->expired(),
                        blank: fn(Builder $query) => $query,
                    ),

                Tables\Filters\TernaryFilter::make('is_wfa')->label('WFA'),
                Tables\Filters\TernaryFilter::make('is_banned')->label('Blokir Absensi'),

                Tables\Filters\Filter::make('date_range')
                    ->label('Rentang Tanggal')
                    ->form([
                        Forms\Components\DatePicker::make('from_date')->label('Dari Tanggal'),
                        Forms\Components\DatePicker::make('until_date')->label('Sampai Tanggal'),
                    ])
                    ->query(function (Builder $query, array $data) {
                        return $query
                            ->when($data['from_date'], fn($q) => $q->whereDate('start_date', '>=', $data['from_date']))
                            ->when($data['until_date'], fn($q) => $q->whereDate('start_date', '<=', $data['until_date']));
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('end_schedule')
                    ->label('Akhiri')
                    ->icon('heroicon-m-stop')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Akhiri Jadwal')
                    ->modalDescription('Apakah Anda yakin ingin mengakhiri jadwal ini?')
                    ->action(function (Schedule $record) {
                        $record->endSchedule();
                        Notification::make()
                            ->title('Jadwal Diakhiri')
                            ->body("Jadwal untuk {$record->user->name} telah diakhiri.")
                            ->success()
                            ->send();
                    })
                    ->visible(fn(Schedule $record) => $record->end_date === null && !$record->is_banned),

                Tables\Actions\Action::make('extend_schedule')
                    ->label('Perpanjang')
                    ->icon('heroicon-m-arrow-path')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Perpanjang Jadwal')
                    ->modalDescription('Jadwal akan berlaku kembali (end_date dihapus).')
                    ->action(function (Schedule $record) {
                        $record->extendSchedule();
                        Notification::make()
                            ->title('Jadwal Diperpanjang')
                            ->body("Jadwal untuk {$record->user->name} telah diperpanjang.")
                            ->success()
                            ->send();
                    })
                    ->visible(fn(Schedule $record) => $record->end_date !== null && $record->end_date < now()),

                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\BulkAction::make('ban_attendance')
                        ->label('Blokir Absensi')
                        ->icon('heroicon-m-x-circle')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->action(fn($records) => $records->each->update(['is_banned' => true])),
                    Tables\Actions\BulkAction::make('unban_attendance')
                        ->label('Buka Blokir')
                        ->icon('heroicon-m-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(fn($records) => $records->each->update(['is_banned' => false])),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSchedules::route('/'),
            'create' => Pages\CreateSchedule::route('/create'),
            'edit' => Pages\EditSchedule::route('/{record}/edit'),
        ];
    }
}
