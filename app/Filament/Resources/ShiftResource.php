<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ShiftResource\Pages;
use App\Models\Shift;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ShiftResource extends Resource
{
    protected static ?string $model = Shift::class;
    protected static ?string $navigationIcon = 'heroicon-o-clock';
    protected static ?string $navigationGroup = 'Master Data';
    protected static ?int $navigationSort = 4;

    public static function getModelLabel(): string
    {
        return 'Jadwal Shift';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Jadwal Shift';
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::count();
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Konfigurasi Shift')
                    ->description('Tentukan nama shift dan jam operasional kerja.')
                    ->icon('heroicon-m-clock')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Nama Shift')
                            ->placeholder('Contoh: Shift Pagi / Shift Malam')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->columnSpanFull(),

                        Forms\Components\TimePicker::make('start_time')
                            ->label('Jam Mulai Kerja')
                            ->required()
                            ->seconds(false)
                            ->displayFormat('H:i')
                            ->suffixIcon('heroicon-m-play-circle'),

                        Forms\Components\TimePicker::make('end_time')
                            ->label('Jam Selesai Kerja')
                            ->required()
                            ->seconds(false)
                            ->displayFormat('H:i')
                            ->suffixIcon('heroicon-m-stop-circle')
                            ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get) {
                                $start = $get('start_time');
                                $end = $state;

                                if ($start && $end && $start >= $end) {
                                    $set('end_time', null);
                                    \Filament\Notifications\Notification::make()
                                        ->danger()
                                        ->title('Invalid Shift')
                                        ->body('Jam selesai harus lebih besar dari jam mulai.')
                                        ->send();
                                }
                            }),
                    ])->columnSpan(['lg' => 2])->columns(2),

                Forms\Components\Section::make('Informasi Tambahan')
                    ->schema([
                        Forms\Components\Placeholder::make('created_at')
                            ->label('Dibuat Pada')
                            ->content(fn(?Shift $record): string => $record ? $record->created_at->diffForHumans() : '-'),

                        Forms\Components\Placeholder::make('updated_at')
                            ->label('Pembaruan Terakhir')
                            ->content(fn(?Shift $record): string => $record ? $record->updated_at->diffForHumans() : '-'),
                    ])
                    ->columnSpan(['lg' => 1])
                    ->hidden(fn(?Shift $record) => $record === null),
            ])->columns(3);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('start_time', 'asc')
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nama Shift')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->color('primary'),

                Tables\Columns\TextColumn::make('start_time')
                    ->label('Mulai')
                    ->dateTime('H:i')
                    ->badge()
                    ->color('info')
                    ->icon('heroicon-m-play')
                    ->tooltip('Jam mulai kerja'),

                Tables\Columns\TextColumn::make('end_time')
                    ->label('Selesai')
                    ->dateTime('H:i')
                    ->badge()
                    ->color('warning')
                    ->icon('heroicon-m-stop')
                    ->tooltip('Jam selesai kerja'),

                Tables\Columns\TextColumn::make('schedules_count')
                    ->label('Karyawan')
                    ->counts('schedules')
                    ->badge()
                    ->color('gray')
                    ->alignCenter()
                    ->tooltip('Jumlah karyawan dengan shift ini'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('shift_type')
                    ->label('Tipe Shift')
                    ->options([
                        'regular' => 'Shift Reguler (start < end)',
                        'overnight' => 'Shift Overnight (start > end)',
                    ])
                    ->query(function (Builder $query, array $data) {
                        if (!$data['value']) return $query;

                        return match ($data['value']) {
                            'regular' => $query->whereRaw('start_time < end_time'),
                            'overnight' => $query->whereRaw('start_time > end_time'),
                            default => $query,
                        };
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('duplicate')
                    ->label('Duplikat')
                    ->icon('heroicon-m-document-duplicate')
                    ->color('gray')
                    ->action(function (Shift $record) {
                        $newShift = $record->replicate();
                        $newShift->name = $record->name . ' (Salinan)';
                        $newShift->save();

                        \Filament\Notifications\Notification::make()
                            ->title('Shift Berhasil Diduplikat')
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
            'index' => Pages\ListShifts::route('/'),
            'create' => Pages\CreateShift::route('/create'),
            'edit' => Pages\EditShift::route('/{record}/edit'),
        ];
    }
}
