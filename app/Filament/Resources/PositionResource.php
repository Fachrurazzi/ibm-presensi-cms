<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PositionResource\Pages;
use App\Models\Position;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PositionResource extends Resource
{
    protected static ?string $model = Position::class;
    protected static ?string $navigationIcon = 'heroicon-o-briefcase';
    protected static ?string $navigationGroup = 'Manajemen Pengguna';
    protected static ?int $navigationSort = 2;

    public static function getModelLabel(): string
    {
        return 'Jabatan';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Jabatan';
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Informasi Jabatan')
                    ->description('Kelola nama jabatan yang tersedia di perusahaan.')
                    ->icon('heroicon-m-briefcase')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Nama Jabatan')
                            ->placeholder('Masukkan nama jabatan (contoh: Manajer IT)')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255)
                            ->autofocus() // Memudahkan input cepat
                            ->columnSpanFull(),
                    ])
                    ->columnSpan(['lg' => 2]), // Konsisten dengan lebar section di UserResource

                Forms\Components\Section::make('Statistik')
                    ->description('Informasi tambahan mengenai jabatan ini.')
                    ->schema([
                        Forms\Components\Placeholder::make('created_at')
                            ->label('Dibuat Pada')
                            ->content(fn(?Position $record): string => $record ? $record->created_at->diffForHumans() : '-'),

                        Forms\Components\Placeholder::make('updated_at')
                            ->label('Terakhir Diubah')
                            ->content(fn(?Position $record): string => $record ? $record->updated_at->diffForHumans() : '-'),
                    ])
                    ->columnSpan(['lg' => 1])
                    ->hidden(fn(?Position $record) => $record === null), // Sembunyikan saat "Create"
            ])->columns(3); // Layout 3 kolom agar konsisten dengan UserResource
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nama Jabatan')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->color('primary'),

                Tables\Columns\TextColumn::make('users_count')
                    ->label('Total Karyawan')
                    ->counts('users') // Menggunakan eager loading count otomatis
                    ->badge()
                    ->color('info') // Menggunakan warna info agar lebih menarik
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Terakhir Perbarui')
                    ->dateTime('d M Y')
                    ->color('gray')
                    ->sortable(),
            ])
            ->defaultSort('name', 'asc')
            ->actions([
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
            'index' => Pages\ListPositions::route('/'),
            'create' => Pages\CreatePosition::route('/create'),
            'edit' => Pages\EditPosition::route('/{record}/edit'),
        ];
    }
}
