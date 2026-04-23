<!DOCTYPE html>
<html>

<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <style>
        @page {
            margin: 0.15cm;
        }

        body {
            font-family: 'Arial Narrow', sans-serif;
            font-size: 7px;
            color: #000;
            margin: 0;
            padding: 0;
        }

        .page-break {
            page-break-after: always;
        }

        .page-break:last-child {
            page-break-after: avoid;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        th,
        td {
            border: 0.1pt solid #000;
            padding: 2px 2px;
            text-align: center;
            vertical-align: middle;
        }

        th {
            background-color: #f2f2f2;
            font-weight: bold;
            font-size: 6px;
        }

        .col-no {
            width: 2%;
        }

        .col-name {
            width: 14%;
            text-align: left;
            padding-left: 3px;
            font-weight: bold;
        }

        .col-jabatan {
            width: 10%;
            text-align: left;
            padding-left: 3px;
        }

        .col-time {
            font-size: 5px;
            width: 3%;
        }

        .area-header {
            background-color: #e3f2fd;
            text-align: left;
            font-weight: bold;
            font-size: 9px;
            padding: 4px 8px;
            border-bottom: 1pt solid #000;
        }

        h2,
        p {
            text-align: center;
            text-transform: uppercase;
            margin: 2px 0;
        }

        h2 {
            font-size: 12px;
        }

        p {
            font-size: 8px;
        }

        .footer {
            position: fixed;
            bottom: 5px;
            left: 0;
            right: 0;
            text-align: center;
            font-size: 5px;
            color: #999;
        }

        .late {
            background-color: #ffebee;
            color: #c62828;
        }

        .ontime {
            background-color: #e8f5e9;
            color: #2e7d32;
        }

        .absent {
            background-color: #f5f5f5;
            color: #9e9e9e;
        }
    </style>
</head>

<body>
    @php
        $groupedByOffice = $users->groupBy(fn($u) => $u->schedules->first()?->office?->name ?? 'TANPA KANTOR');
        $globalTotalHadir = 0;
        $globalTotalTerlambat = 0;
    @endphp

    @foreach ($groupedByOffice as $officeName => $officeGroup)
        @php
            $totalHadir = 0;
            $totalTerlambat = 0;
        @endphp

        <div class="page-break">
            <h2>{{ $title }}</h2>
            <p>AREA: {{ strtoupper($officeName) }} | PERIODE: {{ Carbon\Carbon::parse($startDate)->format('d M Y') }} -
                {{ Carbon\Carbon::parse($endDate)->format('d M Y') }}</p>

            <table>
                <thead>
                    <tr>
                        <th rowspan="2" class="col-no">NO</th>
                        <th rowspan="2" class="col-name">NAMA KARYAWAN</th>
                        @foreach ($dates as $date)
                            <th colspan="2">{{ $date->format('d/m') }}</th>
                        @endforeach
                        <th rowspan="2" class="col-jabatan">JABATAN</th>
                    </tr>
                    <tr>
                        @foreach ($dates as $date)
                            <th class="col-time">M</th>
                            <th class="col-time">P</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td colspan="{{ count($dates) * 2 + 3 }}" class="area-header">
                            📍 LOKASI / DEPO: {{ strtoupper($officeName) }}
                        </td>
                    </tr>

                    @foreach ($officeGroup as $index => $user)
                        @php
                            $userLateCount = 0;
                        @endphp
                        <tr>
                            <td class="col-no">{{ $loop->iteration }}</td>
                            <td class="col-name">{{ Str::limit($user->name, 35) }}</td>

                            @foreach ($dates as $date)
                                @php
                                    $att = $user->attendances->first(fn($a) => $a->created_at->isSameDay($date));
                                    $isLate = $att && $att->isLate();
                                    $hasPermission =
                                        $att && $att->permission && $att->permission->status === 'APPROVED';

                                    if ($isLate && !$hasPermission) {
                                        $userLateCount++;
                                    }
                                @endphp
                                <td
                                    class="col-time {{ $isLate && !$hasPermission ? 'late' : ($att ? 'ontime' : 'absent') }}">
                                    @if ($hasPermission)
                                        📋
                                    @else
                                        {{ $att?->start_time?->format('H:i') ?? '-' }}
                                    @endif
                                </td>
                                <td class="col-time {{ $att ? 'ontime' : 'absent' }}">
                                    {{ $att?->end_time?->format('H:i') ?? '-' }}
                                </td>
                            @endforeach

                            <td class="col-jabatan">{{ Str::limit($user->position->name ?? '-', 20) }}</td>
                        </tr>
                        @php
                            $totalHadir += $user->attendances->count();
                            $totalTerlambat += $userLateCount;
                        @endphp
                    @endforeach

                    <tr>
                        <td colspan="{{ count($dates) * 2 + 2 }}"
                            style="text-align: right; font-weight: bold; background-color: #f2f2f2;">
                            TOTAL HADIR:
                        </td>
                        <td colspan="2" style="font-weight: bold; background-color: #f2f2f2;">
                            {{ $totalHadir }} Hadir ({{ $totalTerlambat }} Terlambat)
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        @php
            $globalTotalHadir += $totalHadir;
            $globalTotalTerlambat += $totalTerlambat;
        @endphp
    @endforeach

    <div class="footer">
        Dicetak pada: {{ now()->format('d/m/Y H:i:s') }} | Generated by: {{ auth()->user()->name }} | Total Karyawan:
        {{ $users->count() }} | Total Hadir: {{ $globalTotalHadir }} | Total Terlambat: {{ $globalTotalTerlambat }}
    </div>
</body>

</html>
