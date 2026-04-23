<?php

namespace App\Filament\Widgets;

use App\Models\Attendance;
use App\Models\Office;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

class LatestAttendances extends BaseWidget
{
    protected static ?string $heading = 'Presensi Terbaru (Hari Ini)';
    protected static ?int $sort = 4;
    protected int | string | array $columnSpan = 'full';

    public static function canView(): bool
    {
        return auth()->user()->hasRole(['super_admin', 'admin']);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Attendance::query()
                    ->with(['user.position', 'schedule.office'])
                    ->whereDate('created_at', today())
                    ->latest()
                    ->limit(10)
            )
            ->poll('30s')
            ->emptyStateHeading('Belum Ada Presensi')
            ->emptyStateDescription('Belum ada karyawan yang melakukan presensi hari ini.')
            ->emptyStateIcon('heroicon-o-clock')
            ->columns([
                Tables\Columns\ImageColumn::make('user.image')
                    ->label('')
                    ->circular()
                    ->defaultImageUrl(fn($record) => 'https://ui-avatars.com/api/?name=' . urlencode($record->user->name) . '&background=f97316&color=fff'),

                Tables\Columns\TextColumn::make('user.name')
                    ->label('Karyawan')
                    ->description(fn($record) => $record->user->position?->name ?? 'Staff')
                    ->weight('bold')
                    ->searchable(),

                Tables\Columns\TextColumn::make('schedule.office.name')
                    ->label('Cabang')
                    ->badge()
                    ->color('gray')
                    ->icon('heroicon-m-building-office-2'),

                Tables\Columns\IconColumn::make('schedule.is_wfa')
                    ->label('Mode')
                    ->boolean()
                    ->trueIcon('heroicon-o-globe-alt')
                    ->falseIcon('heroicon-o-building-office-2')
                    ->trueColor('purple')
                    ->falseColor('gray')
                    ->tooltip(fn($record) => $record->schedule->is_wfa ? 'Work From Anywhere' : 'On Site'),

                Tables\Columns\TextColumn::make('start_time')
                    ->label('Check In')
                    ->dateTime('H:i')
                    ->badge()
                    ->color('info')
                    ->icon('heroicon-m-arrow-right-start-on-rectangle'),

                Tables\Columns\TextColumn::make('end_time')
                    ->label('Check Out')
                    ->dateTime('H:i')
                    ->placeholder('Masih Bekerja')
                    ->badge()
                    ->color('warning')
                    ->icon('heroicon-m-arrow-right-end-on-rectangle'),

                Tables\Columns\TextColumn::make('work_duration')
                    ->label('Durasi')
                    ->getStateUsing(fn($record) => $record->workDuration())
                    ->color('gray')
                    ->icon('heroicon-m-clock')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('status')
                    ->label('Keterangan')
                    ->badge()
                    ->getStateUsing(fn($record) => $record->isLate() ? 'Terlambat' : 'Tepat Waktu')
                    ->color(fn($record) => $record->isLate() ? 'danger' : 'success')
                    ->icon(fn($record) => $record->isLate() ? 'heroicon-m-exclamation-triangle' : 'heroicon-m-check-badge'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('office_id')
                    ->label('Cabang')
                    ->options(fn() => Office::pluck('name', 'id'))
                    ->query(
                        fn(Builder $query, array $data) =>
                        $query->when(
                            $data['value'],
                            fn($q, $id) =>
                            $q->whereHas('schedule', fn($sq) => $sq->where('office_id', $id))
                        )
                    ),

                Tables\Filters\SelectFilter::make('status_filter')
                    ->label('Status')
                    ->options([
                        'late' => 'Terlambat',
                        'ontime' => 'Tepat Waktu',
                    ])
                    ->query(
                        fn(Builder $query, array $data) =>
                        $data['value'] === 'late'
                            ? $query->whereRaw('TIME(start_time) > TIME(schedule_start_time)')
                            : ($data['value'] === 'ontime'
                                ? $query->whereRaw('TIME(start_time) <= TIME(schedule_start_time)')
                                : $query)
                    ),
            ])
            ->actions([
                Tables\Actions\Action::make('view')
                    ->label('Detail')
                    ->icon('heroicon-m-eye')
                    ->url(fn($record) => route('filament.admin.resources.attendances.edit', $record))
                    ->openUrlInNewTab(),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
