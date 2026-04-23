<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Summary Stats --}}
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div class="bg-white rounded-lg shadow p-4 dark:bg-gray-800">
                <div class="text-sm text-gray-500 dark:text-gray-400">Total Karyawan</div>
                <div class="text-2xl font-bold">{{ $this->getSummaryStats()['total_karyawan'] }}</div>
            </div>
            <div class="bg-white rounded-lg shadow p-4 dark:bg-gray-800">
                <div class="text-sm text-gray-500 dark:text-gray-400">Total Sisa Cuti</div>
                <div class="text-2xl font-bold">{{ $this->getSummaryStats()['total_quota'] }} Hari</div>
            </div>
            <div class="bg-white rounded-lg shadow p-4 dark:bg-gray-800">
                <div class="text-sm text-gray-500 dark:text-gray-400">Rata-rata Sisa</div>
                <div class="text-2xl font-bold">{{ $this->getSummaryStats()['avg_quota'] }} Hari</div>
            </div>
            <div class="bg-white rounded-lg shadow p-4 dark:bg-gray-800">
                <div class="text-sm text-gray-500 dark:text-gray-400">Kritis (≤2 Hari)</div>
                <div
                    class="text-2xl font-bold {{ $this->getSummaryStats()['critical_count'] > 0 ? 'text-danger-600' : '' }}">
                    {{ $this->getSummaryStats()['critical_count'] }} Karyawan
                </div>
            </div>
        </div>

        {{-- Tabel --}}
        {{ $this->table }}
    </div>
</x-filament-panels::page>
