<table border="1" cellpadding="5" cellspacing="0" style="border-collapse: collapse; width: 100%;">
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
                    {{ $date->format('d/m') }}
                </th>
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
        @php $grandTotalAll = 0; @endphp

        @foreach ($users as $supervisor => $userGroup)
            <tr>
                <td colspan="{{ count($dates) * 3 + 5 }}"
                    style="background-color: #e3f2fd; font-weight: bold; border: 1px solid #000;">
                    AREA SUPERVISOR: {{ strtoupper($supervisor) }}
                </td>
            </tr>

            @php
                $groupedByCabang = $userGroup->groupBy(function ($u) {
                    return $u->schedules->first()?->office?->name ?? '-';
                });
            @endphp

            @foreach ($groupedByCabang as $cabangName => $cabangGroup)
                @php
                    $groupedByJabatan = $cabangGroup->groupBy(function ($u) {
                        return $u->position?->name ?? '-';
                    });
                    $cabangRowIndex = 0;
                @endphp

                @foreach ($groupedByJabatan as $jabatanName => $jabatanGroup)
                    @foreach ($jabatanGroup as $jabatanRowIndex => $user)
                        @php
                            $grandTotalUM = 0;
                        @endphp
                        <tr>
                            <td style="border: 1px solid #000; text-align: center; vertical-align: middle;">
                                {{ $user->id }}
                            </td>
                            <td style="border: 1px solid #000; vertical-align: middle;">{{ $user->name }}</td>

                            @foreach ($dates as $date)
                                @php
                                    $attendance = $user->attendances->first(
                                        fn($item) => $item->created_at->isSameDay($date),
                                    );

                                    $uangMakan = 0;
                                    $statusColor = '';
                                    $hasApprovedPermission = false;
                                    $isLate = false;

                                    if ($attendance) {
                                        $permission = $attendance->permission;
                                        $hasApprovedPermission = $permission && $permission->status === 'APPROVED';
                                        $isLate = $attendance->isLate();

                                        if ($hasApprovedPermission) {
                                            if ($permission->type === 'BUSINESS_TRIP') {
                                                $uangMakan = 15000;
                                            }
                                        } elseif (!$isLate) {
                                            $uangMakan = 15000;
                                        }
                                    }

                                    $grandTotalUM += $uangMakan;
                                    $grandTotalAll += $uangMakan;
                                @endphp

                                <td style="border: 1px solid #000; text-align: center; vertical-align: middle;">
                                    @if ($attendance)
                                        <span style="cursor: help;"
                                            title="{{ $hasApprovedPermission ? 'Izin: ' . ($permission->type_label ?? $permission->type) : ($isLate ? 'Terlambat' : 'Tepat waktu') }}">
                                            {{ $attendance->start_time?->format('H:i') ?? '-' }}
                                            @if ($hasApprovedPermission)
                                                📋
                                            @elseif($isLate)
                                                ⚠️
                                            @else
                                                ✅
                                            @endif
                                        </span>
                                    @else
                                        -
                                    @endif
                                </td>

                                <td style="border: 1px solid #000; text-align: center; vertical-align: middle;">
                                    {{ $attendance?->end_time?->format('H:i') ?? '-' }}
                                </td>

                                <td
                                    style="border: 1px solid #000; text-align: right; vertical-align: middle; 
                                    {{ $uangMakan > 0 ? 'background-color: #d4edda;' : ($attendance ? 'background-color: #f8d7da;' : '') }}">
                                    @if ($attendance)
                                        @if ($uangMakan > 0)
                                            Rp {{ number_format($uangMakan, 0, ',', '.') }}
                                        @else
                                            0
                                        @endif
                                    @else
                                        -
                                    @endif
                                </td>
                            @endforeach

                            <td
                                style="border: 1px solid #000; text-align: right; font-weight: bold; background-color: #f0f0f0; vertical-align: middle;">
                                Rp {{ number_format($grandTotalUM, 0, ',', '.') }}
                            </td>

                            @if ($jabatanRowIndex === 0)
                                <td rowspan="{{ $jabatanGroup->count() }}"
                                    style="border: 1px solid #000; text-align: center; vertical-align: middle; background-color: #d1e9ff;">
                                    {{ $jabatanName }}
                                </td>
                            @endif

                            @if ($cabangRowIndex === 0)
                                <td rowspan="{{ $cabangGroup->count() }}"
                                    style="border: 1px solid #000; text-align: center; vertical-align: middle; background-color: #d1e9ff;">
                                    {{ $cabangName }}
                                </td>
                            @endif
                        </tr>
                        @php $cabangRowIndex++; @endphp
                    @endforeach
                @endforeach
            @endforeach
        @endforeach
    </tbody>

    <tfoot>
        <tr>
            <td colspan="{{ count($dates) * 3 + 4 }}"
                style="background-color: #e3f2fd; font-weight: bold; text-align: right; border: 1px solid #000;">
                GRAND TOTAL UANG MAKAN:
            </td>
            <td colspan="2"
                style="background-color: #e3f2fd; font-weight: bold; text-align: right; border: 1px solid #000;">
                Rp {{ number_format($grandTotalAll, 0, ',', '.') }}
            </td>
        </tr>
    </tfoot>
</table>
