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

        /* Kunci agar ganti area = ganti halaman */
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
            padding: 1px 0;
            text-align: center;
            vertical-align: middle;
            overflow: hidden;
            white-space: nowrap;
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
            width: 12%;
            text-align: left;
            padding-left: 3px;
        }

        .col-time {
            font-size: 5px;
        }

        .area-header {
            background-color: #e3f2fd;
            text-align: left;
            font-weight: bold;
            font-size: 11px;
            padding: 5px 10px;
            border-bottom: 2pt solid #000;
        }

        h2,
        p {
            text-align: center;
            text-transform: uppercase;
            margin: 2px 0;
        }
    </style>
</head>

<body>

    @php
        // Grouping data di level View berdasarkan nama Office
        $groupedByOffice = $users->groupBy(fn($u) => $u->schedules->first()?->office?->name ?? 'TANPA KANTOR');
    @endphp

    @foreach ($groupedByOffice as $officeName => $officeGroup)
        <div class="page-break">
            <h2>{{ $title }}</h2>
            <p>AREA: {{ strtoupper($officeName) }} | PERIODE: {{ $dates[0]->format('d M Y') }} -
                {{ end($dates)->format('d M Y') }}</p>

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
                    {{-- Judul Sub-Area dalam tabel --}}
                    <tr>
                        <td colspan="{{ count($dates) * 2 + 3 }}" class="area-header">
                            LOKASI / DEPO: {{ strtoupper($officeName) }}
                        </td>
                    </tr>

                    @foreach ($officeGroup as $index => $user)
                        <tr>
                            <td class="col-no">{{ $loop->iteration }}</td>
                            <td class="col-name">{{ Str::limit($user->name, 35) }}</td>

                            @foreach ($dates as $date)
                                @php
                                    $att = $user->attendances->first(fn($a) => $a->created_at->isSameDay($date));
                                @endphp
                                <td class="col-time">{{ $att?->start_time?->format('H:i') ?? '-' }}</td>
                                <td class="col-time">{{ $att?->end_time?->format('H:i') ?? '-' }}</td>
                            @endforeach

                            <td class="col-jabatan">{{ Str::limit($user->position->name ?? '-', 20) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endforeach

</body>

</html>
