<?php
// app/Filament/Resources/AttendancePermissionResource.php

namespace App\Filament\Resources;

use App\Filament\Resources\AttendancePermissionResource\Pages;
use App\Models\AttendancePermission;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class AttendancePermissionResource extends Resource
{
    protected static ?string $model = AttendancePermission::class;
    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static ?string $navigationGroup = 'Manajemen Absensi';
    protected static ?int $navigationSort = 4;

    public static function getModelLabel(): string
    {
        return 'Izin Karyawan';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Izin Karyawan';
    }

    public static function getNavigationBadge(): ?string
    {
        $query = static::getModel()::query();

        if (!auth()->user()->hasRole(['super_admin', 'admin'])) {
            $query->where('user_id', auth()->id());
        }

        $count = $query->where('status', 'PENDING')->count();
        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function form(Form $form): Form
    {
        $isCreating = request()->routeIs('filament.admin.resources.attendance-permissions.create');
        $isAdmin = auth()->user()->hasRole(['super_admin', 'admin']);

        return $form
            ->schema([
                Forms\Components\Grid::make(3)
                    ->schema([
                        // BAGIAN KIRI
                        Forms\Components\Group::make()
                            ->schema([
                                Forms\Components\Section::make('Detail Izin')
                                    ->icon('heroicon-m-document-text')
                                    ->schema([
                                        Forms\Components\Select::make('user_id')
                                            ->label('Karyawan')
                                            ->relationship('user', 'name')
                                            ->default(Auth::id())
                                            ->required()
                                            ->disabled(!$isAdmin && !$isCreating)
                                            ->searchable()
                                            ->preload()
                                            ->columnSpanFull(),

                                        Forms\Components\Select::make('type')
                                            ->label('Jenis Izin')
                                            ->options([
                                                'LATE' => 'Izin Terlambat',
                                                'EARLY_LEAVE' => 'Izin Pulang Cepat',
                                                'BUSINESS_TRIP' => 'Dinas Luar Kota',
                                                'SICK_WITH_CERT' => 'Sakit (Surat Dokter)',
                                            ])
                                            ->required()
                                            ->native(false)
                                            ->live()
                                            ->afterStateUpdated(function ($state, Forms\Set $set) {
                                                if ($state === 'SICK_WITH_CERT') {
                                                    $set('image_proof_required', true);
                                                } else {
                                                    $set('image_proof_required', false);
                                                }
                                            }),

                                        Forms\Components\DatePicker::make('date')
                                            ->label('Tanggal Izin')
                                            ->required()
                                            ->native(false)
                                            ->displayFormat('d/m/Y')
                                            ->default(now()),

                                        Forms\Components\FileUpload::make('image_proof')
                                            ->label('Bukti (Surat Dokter / Surat Tugas)')
                                            ->image()
                                            ->imageEditor()
                                            ->directory('permissions')
                                            ->nullable()
                                            ->helperText(
                                                fn(Forms\Get $get) =>
                                                $get('type') === 'SICK_WITH_CERT'
                                                    ? 'Wajib upload surat dokter'
                                                    : 'Opsional (upload jika ada)'
                                            )
                                            ->required(fn(Forms\Get $get) => $get('type') === 'SICK_WITH_CERT'),

                                        Forms\Components\Textarea::make('reason')
                                            ->label('Alasan Izin')
                                            ->required()
                                            ->rows(4)
                                            ->placeholder('Jelaskan alasan pengajuan izin...')
                                            ->columnSpanFull(),
                                    ])->columns(2),
                            ])->columnSpan(2),

                        // BAGIAN KANAN
                        Forms\Components\Group::make()
                            ->schema([
                                Forms\Components\Section::make('Status Approval')
                                    ->icon('heroicon-m-check-badge')
                                    ->schema([
                                        Forms\Components\Select::make('status')
                                            ->label('Status')
                                            ->options([
                                                'PENDING' => 'Menunggu',
                                                'APPROVED' => 'Disetujui',
                                                'REJECTED' => 'Ditolak',
                                            ])
                                            ->default('PENDING')
                                            ->required()
                                            ->native(false)
                                            ->disabled(!$isAdmin),

                                        Forms\Components\Textarea::make('note')
                                            ->label('Catatan Admin')
                                            ->rows(3)
                                            ->placeholder('Alasan persetujuan/penolakan...')
                                            ->disabled(!$isAdmin),
                                    ]),

                                Forms\Components\Section::make('Informasi Sistem')
                                    ->schema([
                                        Forms\Components\Placeholder::make('created_at')
                                            ->label('Diajukan Pada')
                                            ->content(fn($record) => $record?->created_at?->diffForHumans() ?? '-'),

                                        Forms\Components\Placeholder::make('status_label')
                                            ->label('Status Saat Ini')
                                            ->content(fn($record) => $record?->status_label ?? '-')
                                            ->color(fn($record) => match ($record?->status) {
                                                'APPROVED' => 'success',
                                                'REJECTED' => 'danger',
                                                default => 'warning',
                                            }),
                                    ]),
                            ])->columnSpan(1),
                    ]),
            ]);
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
                    ->description(fn($record) => $record->user->position?->name ?? 'Staff'),

                Tables\Columns\TextColumn::make('type_label')
                    ->label('Jenis Izin')
                    ->badge()
                    ->color('info')
                    ->icon('heroicon-m-document-text'),

                Tables\Columns\TextColumn::make('date')
                    ->label('Tanggal')
                    ->date('d M Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('status_label')
                    ->label('Status')
                    ->badge()
                    ->color(fn($record) => match ($record->status) {
                        'APPROVED' => 'success',
                        'REJECTED' => 'danger',
                        default => 'warning',
                    })
                    ->icon(fn($record) => match ($record->status) {
                        'APPROVED' => 'heroicon-m-check-circle',
                        'REJECTED' => 'heroicon-m-x-circle',
                        default => 'heroicon-m-clock',
                    }),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Diajukan')
                    ->since()
                    ->color('gray')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label('Jenis Izin')
                    ->options([
                        'LATE' => 'Izin Terlambat',
                        'EARLY_LEAVE' => 'Izin Pulang Cepat',
                        'BUSINESS_TRIP' => 'Dinas Luar Kota',
                        'SICK_WITH_CERT' => 'Sakit (Surat Dokter)',
                    ]),

                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'PENDING' => 'Menunggu',
                        'APPROVED' => 'Disetujui',
                        'REJECTED' => 'Ditolak',
                    ]),

                Filter::make('date')
                    ->label('Tanggal Izin')
                    ->form([
                        Forms\Components\DatePicker::make('from')->label('Dari Tanggal'),
                        Forms\Components\DatePicker::make('until')->label('Sampai Tanggal'),
                    ])
                    ->query(
                        fn(Builder $query, array $data) =>
                        $query->when($data['from'], fn($q, $date) => $q->whereDate('date', '>=', $date))
                            ->when($data['until'], fn($q, $date) => $q->whereDate('date', '<=', $date))
                    ),
            ])
            ->actions([
                Tables\Actions\Action::make('approve')
                    ->label('Setujui')
                    ->icon('heroicon-m-check')
                    ->color('success')
                    ->visible(
                        fn($record) =>
                        $record->status === 'PENDING' &&
                            auth()->user()->hasRole(['super_admin', 'admin'])
                    )
                    ->action(function ($record) {
                        $record->update(['status' => 'APPROVED']);

                        \Filament\Notifications\Notification::make()
                            ->success()
                            ->title('Izin Disetujui')
                            ->body("Izin {$record->type_label} untuk {$record->user->name} telah disetujui.")
                            ->send();
                    }),

                Tables\Actions\Action::make('reject')
                    ->label('Tolak')
                    ->icon('heroicon-m-x-mark')
                    ->color('danger')
                    ->visible(
                        fn($record) =>
                        $record->status === 'PENDING' &&
                            auth()->user()->hasRole(['super_admin', 'admin'])
                    )
                    ->requiresConfirmation()
                    ->form([
                        Forms\Components\Textarea::make('note')
                            ->label('Alasan Penolakan')
                            ->required(),
                    ])
                    ->action(function ($record, array $data) {
                        $record->update([
                            'status' => 'REJECTED',
                            'note' => $data['note'],
                        ]);

                        \Filament\Notifications\Notification::make()
                            ->danger()
                            ->title('Izin Ditolak')
                            ->body("Izin {$record->type_label} untuk {$record->user->name} telah ditolak.")
                            ->send();
                    }),

                Tables\Actions\Action::make('view_proof')
                    ->label('Lihat Bukti')
                    ->icon('heroicon-m-photo')
                    ->color('gray')
                    ->url(fn($record) => $record->image_proof_url)
                    ->openUrlInNewTab()
                    ->visible(fn($record) => !empty($record->image_proof)),

                Tables\Actions\EditAction::make()
                    ->visible(fn($record) => $record->status === 'PENDING'),

                Tables\Actions\DeleteAction::make()
                    ->visible(fn($record) => $record->status === 'PENDING'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\BulkAction::make('approve_selected')
                        ->label('Setujui Terpilih')
                        ->icon('heroicon-m-check')
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(fn($records) => $records->each->update(['status' => 'APPROVED'])),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAttendancePermissions::route('/'),
            'create' => Pages\CreateAttendancePermission::route('/create'),
            'edit' => Pages\EditAttendancePermission::route('/{record}/edit'),
        ];
    }
}
