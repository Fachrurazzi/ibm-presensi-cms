<?php

namespace App\Filament\Pages;

use App\Models\User;
use Filament\Pages\Page;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Actions\Action;
use Illuminate\Support\HtmlString;

class LeaveStats extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-chart-pie';
    protected static ?string $navigationGroup = 'Manajemen Absensi';
    protected static ?string $navigationLabel = 'Statistik Cuti';
    protected static ?string $title = 'Monitoring Sisa Cuti Karyawan';
    protected static ?int $navigationSort = 5;

    protected static string $view = 'filament.pages.leave-stats';

    public static function canAccess(): bool
    {
        return auth()->user()->hasRole('super_admin');
    }

    public static function getNavigationBadge(): ?string
    {
        $criticalCount = User::role('karyawan')->where('leave_quota', '<=', 2)->count();
        return $criticalCount > 0 ? (string) $criticalCount : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public function getSummaryStats(): array
    {
        $totalKaryawan = User::role('karyawan')->count();
        $totalQuota = User::role('karyawan')->sum('leave_quota');
        $avgQuota = $totalKaryawan > 0 ? round($totalQuota / $totalKaryawan, 1) : 0;
        $criticalCount = User::role('karyawan')->where('leave_quota', '<=', 2)->count();

        return [
            'total_karyawan' => $totalKaryawan,
            'total_quota' => $totalQuota,
            'avg_quota' => $avgQuota,
            'critical_count' => $criticalCount,
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export')
                ->label('Export Data')
                ->icon('heroicon-o-document-arrow-down')
                ->color('success')
                ->action(function () {
                    // TODO: Implement export logic
                    \Filament\Notifications\Notification::make()
                        ->info()
                        ->title('Coming Soon')
                        ->body('Fitur export sedang dalam pengembangan.')
                        ->send();
                }),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(User::query()->role('karyawan'))
            ->columns([
                TextColumn::make('name')
                    ->label('Nama Karyawan')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('position.name')
                    ->label('Jabatan')
                    ->badge()
                    ->color('gray'),

                TextColumn::make('join_date')
                    ->label('Bergabung')
                    ->date('d M Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('leave_quota')
                    ->label('Sisa Hari')
                    ->sortable()
                    ->weight('bold')
                    ->color(fn($state) => $state <= 2 ? 'danger' : ($state <= 5 ? 'warning' : 'success'))
                    ->suffix(' Hari'),

                TextColumn::make('progress')
                    ->label('Persentase Sisa')
                    ->getStateUsing(fn($record) => max(0, min(100, ($record->leave_quota / 12) * 100)))
                    ->formatStateUsing(function ($state, $record) {
                        $color = $state <= 20 ? '#ef4444' : ($state <= 40 ? '#f59e0b' : '#10b981');
                        $percentage = round($state, 1);

                        return new HtmlString('
                            <div class="flex items-center gap-2">
                                <div class="flex-1 min-w-[100px]">
                                    <div style="width: 100%; background-color: #e5e7eb; border-radius: 999px; height: 8px; overflow: hidden;">
                                        <div style="width: ' . $state . '%; background-color: ' . $color . '; height: 100%; border-radius: 999px; transition: width 0.5s ease;"></div>
                                    </div>
                                </div>
                                <span class="text-xs text-gray-500 dark:text-gray-400">' . $percentage . '%</span>
                            </div>
                        ');
                    }),
            ])
            ->filters([
                SelectFilter::make('leave_quota_status')
                    ->label('Status Cuti')
                    ->options([
                        'critical' => 'Kritis (≤ 2 hari)',
                        'warning' => 'Warning (3-5 hari)',
                        'good' => 'Aman (> 5 hari)',
                    ])
                    ->query(function ($query, array $data) {
                        if ($data['value'] === 'critical') {
                            return $query->where('leave_quota', '<=', 2);
                        }
                        if ($data['value'] === 'warning') {
                            return $query->whereBetween('leave_quota', [3, 5]);
                        }
                        if ($data['value'] === 'good') {
                            return $query->where('leave_quota', '>', 5);
                        }
                        return $query;
                    }),

                \Filament\Tables\Filters\Filter::make('join_date')
                    ->label('Tahun Bergabung')
                    ->form([
                        \Filament\Forms\Components\Select::make('year')
                            ->label('Tahun')
                            ->options(range(now()->year, now()->year - 10)),
                    ])
                    ->query(function ($query, array $data) {
                        return $query->when(
                            $data['year'],
                            fn($q, $year) =>
                            $q->whereYear('join_date', $year)
                        );
                    }),
            ])
            ->defaultSort('leave_quota', 'asc')
            ->actions([
                \Filament\Tables\Actions\Action::make('detail')
                    ->label('Detail')
                    ->icon('heroicon-m-eye')
                    ->url(fn($record) => route('filament.admin.resources.users.edit', $record))
                    ->openUrlInNewTab(),
            ]);
    }
}
