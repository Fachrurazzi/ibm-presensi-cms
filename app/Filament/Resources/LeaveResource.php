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
    protected static ?int $navigationSort = 2;

    public static function getModelLabel(): string
    {
        return 'Cuti/Izin';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Data Cuti/Izin';
    }

    public static function getNavigationBadge(): ?string
    {
        $count = static::getModel()::where('status', 'PENDING')->count();
        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        $count = static::getModel()::where('status', 'PENDING')->count();
        return $count > 0 ? 'warning' : null;
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
                                Forms\Components\Select::make('user_id')
                                    ->label('Karyawan')
                                    ->relationship('user', 'name')
                                    ->default(Auth::id())
                                    ->required()
                                    ->disabled(!auth()->user()->hasRole(['super_admin', 'admin']))
                                    ->columnSpanFull(),

                                Forms\Components\Select::make('category')
                                    ->label('Jenis Cuti')
                                    ->options([
                                        'annual' => 'Cuti Tahunan',
                                        'sick' => 'Cuti Sakit',
                                        'emergency' => 'Cuti Darurat',
                                        'maternity' => 'Cuti Melahirkan',
                                        'important' => 'Cuti Penting',
                                    ])
                                    ->default('annual')
                                    ->required()
                                    ->native(false),

                                Forms\Components\DatePicker::make('start_date')
                                    ->label('Tanggal Mulai')
                                    ->required()
                                    ->native(false)
                                    ->displayFormat('d M Y'),

                                Forms\Components\DatePicker::make('end_date')
                                    ->label('Tanggal Selesai')
                                    ->required()
                                    ->native(false)
                                    ->displayFormat('d M Y')
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
                                        'PENDING' => 'Menunggu',
                                        'APPROVED' => 'Disetujui',
                                        'REJECTED' => 'Ditolak',
                                    ])
                                    ->default('PENDING')
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
                $query->with(['user.position']);

                if (!auth()->user()->hasRole(['super_admin', 'admin'])) {
                    $query->where('user_id', auth()->id());
                }
            })
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Karyawan')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->description(fn(Leave $record) => $record->user->position?->name ?? 'Staff'),

                Tables\Columns\TextColumn::make('category_label')
                    ->label('Jenis')
                    ->badge()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('start_date')
                    ->label('Periode Cuti')
                    ->date('d M Y')
                    ->description(fn(Leave $record) => "sd " . $record->end_date->format('d M Y'))
                    ->color('info')
                    ->icon('heroicon-m-calendar-days'),

                Tables\Columns\TextColumn::make('duration')
                    ->label('Durasi')
                    ->getStateUsing(fn(Leave $record) => $record->duration . ' Hari')
                    ->badge()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'APPROVED' => 'success',
                        'PENDING' => 'warning',
                        'REJECTED' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn(string $state): string => match ($state) {
                        'APPROVED' => 'Disetujui',
                        'PENDING' => 'Menunggu',
                        'REJECTED' => 'Ditolak',
                        default => $state,
                    })
                    ->description(fn(Leave $record) => $record->note),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'PENDING' => 'Menunggu',
                        'APPROVED' => 'Disetujui',
                        'REJECTED' => 'Ditolak',
                    ]),
                Tables\Filters\SelectFilter::make('category')
                    ->label('Jenis Cuti')
                    ->options([
                        'annual' => 'Cuti Tahunan',
                        'sick' => 'Cuti Sakit',
                        'emergency' => 'Cuti Darurat',
                        'maternity' => 'Cuti Melahirkan',
                        'important' => 'Cuti Penting',
                    ]),
            ])
            ->actions([
                Tables\Actions\Action::make('approve')
                    ->label('Setujui')
                    ->icon('heroicon-m-check')
                    ->color('success')
                    ->visible(
                        fn(Leave $record) =>
                        $record->status === 'PENDING' &&
                            auth()->user()->hasRole(['super_admin', 'admin'])
                    )
                    ->action(function (Leave $record) {
                        $record->update(['status' => 'APPROVED']);

                        \Filament\Notifications\Notification::make()
                            ->success()
                            ->title('Cuti Disetujui')
                            ->send();
                    }),

                Tables\Actions\Action::make('reject')
                    ->label('Tolak')
                    ->icon('heroicon-m-x-mark')
                    ->color('danger')
                    ->visible(
                        fn(Leave $record) =>
                        $record->status === 'PENDING' &&
                            auth()->user()->hasRole(['super_admin', 'admin'])
                    )
                    ->requiresConfirmation()
                    ->form([
                        Forms\Components\Textarea::make('note')
                            ->label('Alasan Penolakan')
                            ->required(),
                    ])
                    ->action(function (Leave $record, array $data) {
                        $record->update([
                            'status' => 'REJECTED',
                            'note' => $data['note'],
                        ]);

                        \Filament\Notifications\Notification::make()
                            ->danger()
                            ->title('Cuti Ditolak')
                            ->send();
                    }),

                Tables\Actions\Action::make('cancel')
                    ->label('Batalkan')
                    ->icon('heroicon-m-x-circle')
                    ->color('gray')
                    ->visible(
                        fn(Leave $record) =>
                        $record->status === 'PENDING' &&
                            $record->user_id === auth()->id()
                    )
                    ->requiresConfirmation()
                    ->action(function (Leave $record) {
                        $record->delete();

                        \Filament\Notifications\Notification::make()
                            ->warning()
                            ->title('Pengajuan Dibatalkan')
                            ->send();
                    }),

                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
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
