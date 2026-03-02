<?php

namespace App\Filament\Resources;

use App\Filament\Resources\LeaveResource\Pages;
use App\Models\Leave;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class LeaveResource extends Resource
{
    protected static ?string $model = Leave::class;
    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static ?string $navigationGroup = 'Manajemen Absensi';

    public static function getModelLabel(): string
    {
        return 'Cuti/Izin';
    }
    public static function getPluralModelLabel(): string
    {
        return 'Data Cuti/Izin';
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Group::make()
                    ->schema([
                        Forms\Components\Section::make('Detail Pengajuan')
                            ->icon('heroicon-m-clipboard-document-list')
                            ->schema([
                                // User Selection: Hanya muncul untuk Admin
                                Forms\Components\Select::make('user_id')
                                    ->label('Karyawan')
                                    ->relationship('user', 'name')
                                    ->default(Auth::id())
                                    ->required()
                                    ->disabled(!auth()->user()->hasRole(['super_admin', 'admin']))
                                    ->columnSpanFull(),

                                Forms\Components\DatePicker::make('start_date')
                                    ->label('Tanggal Mulai')
                                    ->required()
                                    ->native(false),

                                Forms\Components\DatePicker::make('end_date')
                                    ->label('Tanggal Selesai')
                                    ->required()
                                    ->native(false)
                                    ->afterOrEqual('start_date'),

                                Forms\Components\Textarea::make('reason')
                                    ->label('Alasan Cuti/Izin')
                                    ->required()
                                    ->rows(4)
                                    ->columnSpanFull(),
                            ])->columns(2),
                    ])->columnSpan(['lg' => 2]),

                Forms\Components\Group::make()
                    ->schema([
                        Forms\Components\Section::make('Status Approval')
                            ->icon('heroicon-m-check-badge')
                            ->schema([
                                Forms\Components\Select::make('status')
                                    ->options([
                                        'pending' => 'Menunggu',
                                        'approved' => 'Disetujui',
                                        'rejected' => 'Ditolak',
                                    ])
                                    ->default('pending')
                                    ->required()
                                    ->native(false)
                                    ->disabled(!auth()->user()->hasRole(['super_admin', 'admin'])),

                                Forms\Components\Textarea::make('note')
                                    ->label('Catatan Admin')
                                    ->rows(3)
                                    ->placeholder('Alasan persetujuan/penolakan...')
                                    ->disabled(!auth()->user()->hasRole(['super_admin', 'admin'])),
                            ]),
                    ])->columnSpan(['lg' => 1]),
            ])->columns(3);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function (Builder $query) {
                // Eager Loading user & position
                $query->with(['user.position']);

                // Jika bukan admin, hanya lihat punya sendiri
                if (!auth()->user()->hasRole(['super_admin', 'admin'])) {
                    $query->where('user_id', auth()->id());
                }
            })
            ->columns([
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Karyawan')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->description(fn(Leave $record) => $record->user->position?->name ?? 'Staff'),

                Tables\Columns\TextColumn::make('start_date')
                    ->label('Periode Cuti')
                    ->date('d M Y')
                    ->description(fn(Leave $record) => "Sampai " . $record->end_date->format('d M Y'))
                    ->color('info')
                    ->icon('heroicon-m-calendar-days'),

                Tables\Columns\TextColumn::make('duration')
                    ->label('Durasi')
                    ->getStateUsing(fn(Leave $record) => $record->start_date->diffInDays($record->end_date) + 1 . ' Hari')
                    ->badge()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'approved' => 'success',
                        'pending' => 'warning',
                        'rejected' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn(string $state): string => ucfirst($state))
                    ->description(fn(Leave $record) => $record->note),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'Menunggu',
                        'approved' => 'Disetujui',
                        'rejected' => 'Ditolak',
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }

    public static function getNavigationBadge(): ?string
    {
        $query = static::getModel()::where('status', 'pending');

        if (!auth()->user()->hasRole(['super_admin', 'admin'])) {
            $query->where('user_id', auth()->id());
        }

        $count = $query->count();
        return $count > 0 ? (string) $count : null;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLeaves::route('/'),
            'create' => Pages\CreateLeave::route('/create'),
            'edit' => Pages\EditLeave::route('/{record}/edit'),
        ];
    }
}
