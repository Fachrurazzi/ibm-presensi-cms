<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Hash;
use Filament\Notifications\Notification;

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
        return $form
            ->schema([
                Forms\Components\Section::make('Informasi Profil')
                    ->icon('heroicon-m-user-circle')
                    ->schema([
                        Forms\Components\FileUpload::make('image')
                            ->label('Foto Profil')
                            ->image()
                            ->avatar()
                            ->imageEditor()
                            ->directory('users-avatar')
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

                        // --- TAMBAHAN: Tanggal Bergabung ---
                        Forms\Components\DatePicker::make('join_date')
                            ->label('Tanggal Bergabung')
                            ->required()
                            ->native(false)
                            ->displayFormat('d M Y')
                            ->helperText('Digunakan untuk acuan reset cuti tahunan.'),
                    ])->columnSpan(['lg' => 2])->columns(2),

                Forms\Components\Group::make()
                    ->schema([
                        Forms\Components\Section::make('Keamanan')
                            ->description('Kelola kata sandi akun.')
                            ->icon('heroicon-m-key')
                            ->schema([
                                Forms\Components\TextInput::make('password')
                                    ->label('Kata Sandi Baru')
                                    ->password()
                                    ->required(fn(string $context) => $context === 'create')
                                    ->dehydrateStateUsing(fn($state) => filled($state) ? Hash::make($state) : null)
                                    ->dehydrated(fn($state) => filled($state))
                                    ->same('password_confirmation')
                                    ->helperText('Kosongkan jika tidak ingin mengubah kata sandi.'),

                                Forms\Components\TextInput::make('password_confirmation')
                                    ->label('Konfirmasi Kata Sandi')
                                    ->password()
                                    ->required(fn(string $context) => $context === 'create')
                                    ->dehydrated(false),
                            ]),

                        // --- TAMBAHAN: Informasi Cuti & Saldo Uang ---
                        Forms\Components\Section::make('Informasi Cuti')
                            ->icon('heroicon-m-calendar-days')
                            ->schema([
                                Forms\Components\TextInput::make('leave_quota')
                                    ->label('Sisa Kuota Cuti (Tahun Ini)')
                                    ->numeric()
                                    ->default(12)
                                    ->suffix('Hari'),

                                Forms\Components\TextInput::make('cashable_leave')
                                    ->label('Saldo Uang Cuti')
                                    ->numeric()
                                    ->default(0)
                                    ->helperText('Sisa cuti tahun sebelumnya yang bisa diuangkan.')
                                    ->suffix('Hari'),
                            ]),
                    ])->columnSpan(['lg' => 1]),
            ])->columns(3);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn(Builder $query) => $query->with(['position', 'roles']))
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

                // --- TAMBAHAN: Menampilkan Tanggal Masuk Kerja ---
                Tables\Columns\TextColumn::make('join_date')
                    ->label('Tgl Masuk')
                    ->date('d M Y')
                    ->sortable()
                    ->color('info'),

                // --- TAMBAHAN: Menampilkan Saldo Uang Cuti di Tabel ---
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
                    ->toggleable(isToggledHiddenByDefault: true), // Disembunyikan secara default agar tabel tidak terlalu padat
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('position_id')
                    ->label('Jabatan')
                    ->relationship('position', 'name'),
            ])
            ->actions([
                // --- TAMBAHAN: Tombol Cairkan Uang Cuti ---
                Tables\Actions\Action::make('cairkan_cuti')
                    ->label('Cairkan')
                    ->icon('heroicon-m-banknotes')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Konfirmasi Pencairan Cuti')
                    ->modalDescription(fn(User $record) => "Karyawan ini memiliki saldo {$record->cashable_leave} hari cuti yang belum diuangkan. Lanjutkan pencairan menjadi 0?")
                    ->modalSubmitActionLabel('Ya, Cairkan & Kosongkan Saldo')
                    ->visible(fn(User $record) => $record->cashable_leave > 0) // Hanya muncul jika saldo lebih dari 0
                    ->action(function (User $record) {
                        $record->update(['cashable_leave' => 0]);

                        Notification::make()
                            ->title('Pencairan Berhasil')
                            ->body("Saldo cuti {$record->name} berhasil dicairkan dan dikosongkan.")
                            ->success()
                            ->send();
                    }),

                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
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
}
