<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use App\Models\Office;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Hash;
use Filament\Notifications\Notification;
use App\Imports\UsersImport;
use App\Exports\UserTemplateExport;
use Maatwebsite\Excel\Facades\Excel;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Tables\Actions\Action;
use Illuminate\Support\Facades\Storage;

class UserResource extends Resource
{
    protected static ?string $model = User::class;
    protected static ?string $navigationIcon = 'heroicon-o-users';
    protected static ?string $navigationGroup = 'Manajemen Pengguna';
    protected static ?int $navigationSort = 1;

    public static function getModelLabel(): string
    {
        return 'Karyawan';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Karyawan';
    }

    public static function form(Form $form): Form
    {
        $isCreating = request()->routeIs('filament.admin.resources.users.create');

        return $form
            ->schema([
                Forms\Components\Section::make('Informasi Profil')
                    ->icon('heroicon-m-user-circle')
                    ->schema([
                        Forms\Components\FileUpload::make('image')
                            ->label('Foto Profil')
                            ->image()
                            ->imageEditor()
                            ->directory('users-avatar')
                            ->avatar()
                            ->columnSpanFull()
                            ->alignCenter(),

                        Forms\Components\TextInput::make('name')
                            ->label('Nama Lengkap')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('email')
                            ->label('Alamat Email')
                            ->email()
                            ->required()
                            ->unique(ignoreRecord: true),

                        Forms\Components\Select::make('position_id')
                            ->label('Jabatan')
                            ->relationship('position', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),

                        Forms\Components\Select::make('roles')
                            ->label('Peran / Role')
                            ->relationship('roles', 'name')
                            ->preload()
                            ->required(),

                        Forms\Components\DatePicker::make('join_date')
                            ->label('Tanggal Bergabung')
                            ->required()
                            ->default(now())
                            ->native(false)
                            ->displayFormat('d M Y'),
                    ])->columnSpan(['lg' => 2])->columns(2),

                Forms\Components\Group::make()
                    ->schema([
                        Forms\Components\Section::make('Keamanan')
                            ->icon('heroicon-m-key')
                            ->schema([
                                Forms\Components\TextInput::make('password')
                                    ->label('Kata Sandi Baru')
                                    ->password()
                                    ->required(fn(string $context) => $context === 'create')
                                    ->dehydrateStateUsing(fn($state) => filled($state) ? Hash::make($state) : null)
                                    ->dehydrated(fn($state) => filled($state))
                                    ->same('password_confirmation'),

                                Forms\Components\TextInput::make('password_confirmation')
                                    ->label('Konfirmasi Kata Sandi')
                                    ->password()
                                    ->required(fn(string $context) => $context === 'create')
                                    ->dehydrated(false),
                            ]),

                        Forms\Components\Section::make('Informasi Cuti')
                            ->icon('heroicon-m-calendar-days')
                            ->schema([
                                Forms\Components\TextInput::make('leave_quota')
                                    ->label('Sisa Kuota Cuti')
                                    ->numeric()
                                    ->default(12)
                                    ->minValue(0)
                                    ->suffix('Hari'),

                                Forms\Components\TextInput::make('cashable_leave')
                                    ->label('Saldo Uang Cuti')
                                    ->numeric()
                                    ->default(0)
                                    ->minValue(0)
                                    ->maxValue(365)
                                    ->suffix('Hari'),
                            ]),
                    ])->columnSpan(['lg' => 1]),
            ])->columns(3);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn(Builder $query) => $query->with(['position', 'roles']))
            ->headerActions([
                // ========== TOMBOL DOWNLOAD TEMPLATE ==========
                Action::make('download_template')
                    ->label('Download Template Excel')
                    ->icon('heroicon-m-document-arrow-down')
                    ->color('gray')
                    ->action(function () {
                        return Excel::download(new UserTemplateExport(), 'template_import_karyawan.xlsx');
                    }),

                // ========== TOMBOL IMPORT ==========
                Action::make('import')
                    ->label('Import Excel')
                    ->icon('heroicon-m-arrow-up-tray')
                    ->color('success')
                    ->modalHeading('Import Data Karyawan')
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

                        Select::make('cabang')
                            ->label('Cabang / Kantor')
                            ->options(fn() => Office::pluck('name', 'id'))
                            ->helperText('Pilih cabang untuk data karyawan ini (jika tidak dipilih, ambil dari kolom "kantor" di Excel)')
                            ->nullable(),

                        Toggle::make('overwrite')
                            ->label('Timpa data jika email sudah ada')
                            ->default(false)
                            ->helperText('Jika diaktifkan, data karyawan yang sudah ada akan diperbarui. Jika tidak, akan dilewati.'),
                    ])
                    ->action(function (array $data) {
                        try {
                            $import = new UsersImport($data['cabang'] ?? null, $data['overwrite'] ?? false);
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
                                \Filament\Notifications\Notification::make()
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
                Tables\Columns\ImageColumn::make('image')
                    ->label('Foto')
                    ->circular()
                    ->defaultImageUrl(fn($record) => $record->avatar_url),

                Tables\Columns\TextColumn::make('name')
                    ->label('Nama Pengguna')
                    ->searchable()
                    ->sortable()
                    ->description(fn(User $record): string => $record->email),

                Tables\Columns\TextColumn::make('position.name')
                    ->label('Jabatan')
                    ->badge()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('join_date')
                    ->label('Tgl Masuk')
                    ->date('d M Y')
                    ->sortable()
                    ->color('info'),

                Tables\Columns\TextColumn::make('cashable_leave')
                    ->label('Saldo Uang')
                    ->badge()
                    ->color(fn($state) => $state > 0 ? 'success' : 'gray')
                    ->suffix(' Hari')
                    ->sortable(),

                Tables\Columns\TextColumn::make('roles.name')
                    ->label('Peran')
                    ->badge()
                    ->color('success')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('position_id')
                    ->label('Jabatan')
                    ->relationship('position', 'name'),

                Tables\Filters\SelectFilter::make('face_registered')
                    ->label('Status Face')
                    ->options([
                        '1' => 'Sudah Registrasi',
                        '0' => 'Belum Registrasi',
                    ])
                    ->query(
                        fn(Builder $query, array $data) =>
                        $data['value'] === '1'
                            ? $query->whereNotNull('face_model_path')
                            : $query->whereNull('face_model_path')
                    ),
            ])
            ->actions([
                Action::make('reset_password')
                    ->label('Reset Password')
                    ->icon('heroicon-m-key')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Reset Password')
                    ->modalDescription('Reset password karyawan ke default "password123"?')
                    ->action(function (User $record) {
                        $record->update([
                            'password' => Hash::make('password123'),
                            'is_default_password' => true,
                        ]);

                        Notification::make()
                            ->title('Password Direset')
                            ->body("Password {$record->name} telah direset ke default.")
                            ->success()
                            ->send();
                    }),

                Action::make('cairkan_cuti')
                    ->label('Cairkan')
                    ->icon('heroicon-m-banknotes')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Konfirmasi Pencairan Cuti')
                    ->modalDescription(fn(User $record) => "Karyawan ini memiliki saldo {$record->cashable_leave} hari cuti yang belum diuangkan. Lanjutkan pencairan?")
                    ->visible(fn(User $record) => $record->cashable_leave > 0)
                    ->action(function (User $record) {
                        $record->update(['cashable_leave' => 0]);

                        Notification::make()
                            ->title('Pencairan Berhasil')
                            ->body("Saldo cuti {$record->name} berhasil dicairkan.")
                            ->success()
                            ->send();
                    }),

                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return (string) static::getModel()::count();
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'primary';
    }
}
