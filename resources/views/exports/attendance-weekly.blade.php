<table>
    <thead>
        <tr>
            <th colspan="{{ count($dates) * 3 + 5 }}" style="font-weight: bold; font-size: 14pt; text-align: center;">
                REKAP ABSENSI & UANG MAKAN MINGGUAN
            </th>
        </tr>
        <tr>
            <th colspan="{{ count($dates) * 3 + 5 }}" style="text-align: center;">
                Periode: {{ $dates[0]->format('d M Y') }} - {{ end($dates)->format('d M Y') }}
            </th>
        </tr>
        <tr></tr>
        <tr>
            <th rowspan="2"
                style="background-color: #cccccc; border: 1px solid #000; font-weight: bold; text-align: center; vertical-align: middle;">
                ID</th>
            <th rowspan="2"
                style="background-color: #cccccc; border: 1px solid #000; font-weight: bold; text-align: center; vertical-align: middle;">
                NAME</th>
            @foreach ($dates as $date)
                <th colspan="2" style="background-color: #cccccc; border: 1px solid #000; text-align: center;">
                    {{ $date->format('d/m') }}</th>
                <th rowspan="2"
                    style="background-color: #cccccc; border: 1px solid #000; font-weight: bold; text-align: center; vertical-align: middle;">
                    U.M</th>
            @endforeach
            <th rowspan="2"
                style="background-color: #cccccc; border: 1px solid #000; font-weight: bold; text-align: center; vertical-align: middle;">
                TOTAL U.M</th>
            <th rowspan="2"
                style="background-color: #cccccc; border: 1px solid #000; font-weight: bold; text-align: center; vertical-align: middle;">
                JABATAN</th>
            <th rowspan="2"
                style="background-color: #cccccc; border: 1px solid #000; font-weight: bold; text-align: center; vertical-align: middle;">
                CABANG</th>
        </tr>
        <tr>
            @foreach ($dates as $date)
                <th style="background-color: #cccccc; border: 1px solid #000; text-align: center;">M</th>
                <th style="background-color: #cccccc; border: 1px solid #000; text-align: center;">P</th>
            @endforeach
        </tr>
    </thead>
    <tbody>
        @foreach ($users as $supervisor => $userGroup)
            <tr>
                <td colspan="{{ count($dates) * 3 + 5 }}"
                    style="background-color: #e3f2fd; font-weight: bold; border: 1px solid #000;">
                    AREA SUPERVISOR: {{ strtoupper($supervisor) }}
                </td>
            </tr>
            @foreach ($userGroup as $user)
                @php $grandTotalUM = 0; @endphp
                <tr>
                    <td style="border: 1px solid #000; text-align: center; vertical-align: middle;">{{ $user->id }}
                    </td>
                    <td style="border: 1px solid #000; vertical-align: middle;">{{ $user->name }}</td>
                    @foreach ($dates as $date)
                        @php
                            $attendance = $user->attendances->first(fn($item) => $item->created_at->isSameDay($date));
                            $uangMakan = 0;
                            if ($attendance && $attendance->start_time && $attendance->schedule_start_time) {
                                // Logika: Jika datang sebelum/pas jam jadwal masuk
                                if (
                                    $attendance->start_time->format('H:i') <=
                                    $attendance->schedule_start_time->format('H:i')
                                ) {
                                    $uangMakan = 15000;
                                }
                            }
                            $grandTotalUM += $uangMakan;
                        @endphp
                        <td style="border: 1px solid #000; text-align: center; vertical-align: middle;">
                            {{ $attendance?->start_time?->format('H:i') }}</td>
                        <td style="border: 1px solid #000; text-align: center; vertical-align: middle;">
                            {{ $attendance?->end_time?->format('H:i') }}</td>
                        <td style="border: 1px solid #000; text-align: right; vertical-align: middle;">
                            {{ $uangMakan ?: ($attendance ? '0' : '') }}</td>
                    @endforeach
                    <td
                        style="border: 1px solid #000; text-align: right; font-weight: bold; background-color: #f0f0f0; vertical-align: middle;">
                        {{ $grandTotalUM }}</td>
                    <td
                        style="border: 1px solid #000; text-align: center; vertical-align: middle; background-color: #d1e9ff;">
                        {{ $user->position?->name ?? '-' }}</td>
                    <td
                        style="border: 1px solid #000; text-align: center; vertical-align: middle; background-color: #d1e9ff;">
                        {{ $user->schedules->first()?->office?->name ?? '-' }}</td>
                </tr>
            @endforeach
        @endforeach
    </tbody>
</table>
