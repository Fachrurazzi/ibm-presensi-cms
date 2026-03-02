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

class UserResource extends Resource
{
    protected static ?string $model = User::class;
    protected static ?string $navigationIcon = 'heroicon-o-users';
    protected static ?string $navigationGroup = 'Manajemen Pengguna';

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
                    ])->columnSpan(['lg' => 2])->columns(2),

                Forms\Components\Section::make('Keamanan')
                    ->description('Kelola kata sandi akun.')
                    ->icon('heroicon-m-key')
                    ->schema([
                        Forms\Components\TextInput::make('password')
                            ->label('Kata Sandi Baru')
                            ->password()
                            // Wajib diisi hanya saat membuat user baru (create)
                            ->required(fn(string $context) => $context === 'create')
                            // Password tidak boleh di-hash jika kosong (saat edit)
                            ->dehydrateStateUsing(fn($state) => filled($state) ? Hash::make($state) : null)
                            // Field ini hanya dikirim ke database jika ada isinya
                            ->dehydrated(fn($state) => filled($state))
                            ->same('password_confirmation')
                            ->helperText('Kosongkan jika tidak ingin mengubah kata sandi.'),

                        Forms\Components\TextInput::make('password_confirmation')
                            ->label('Konfirmasi Kata Sandi')
                            ->password()
                            // Wajib diisi hanya jika password utama diisi
                            ->required(fn(string $context) => $context === 'create')
                            ->dehydrated(false),
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

                Tables\Columns\TextColumn::make('roles.name')
                    ->label('Peran')
                    ->badge()
                    ->color('success'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Bergabung')
                    ->dateTime('d M Y')
                    ->color('gray'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('position_id')
                    ->label('Jabatan')
                    ->relationship('position', 'name'),
            ])
            ->actions([
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
