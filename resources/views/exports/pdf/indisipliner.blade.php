<!DOCTYPE html>
<html>

<head>
    <title>Laporan Kedisiplinan Karyawan</title>
    <style>
        body {
            font-family: 'Arial Narrow', sans-serif;
            font-size: 11px;
            margin: 0;
            padding: 10px;
        }

        .header {
            text-align: center;
            margin-bottom: 20px;
            border-bottom: 2px solid #333;
            padding-bottom: 10px;
        }

        .header h2 {
            margin: 0;
            padding: 0;
            font-size: 16px;
        }

        .header p {
            margin: 5px 0 0;
            font-size: 10px;
            color: #666;
        }

        .summary {
            margin-bottom: 20px;
            padding: 10px;
            background-color: #f9f9f9;
            border: 1px solid #ddd;
            border-radius: 5px;
        }

        .summary table {
            width: 100%;
            border: none;
        }

        .summary td {
            border: none;
            padding: 4px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
            font-size: 10px;
        }

        th,
        td {
            border: 1px solid #000;
            padding: 6px 8px;
            text-align: left;
        }

        th {
            background-color: #f2f2f2;
            text-align: center;
            font-weight: bold;
        }

        .text-center {
            text-align: center;
        }

        .text-danger {
            color: #c62828;
            font-weight: bold;
        }

        .footer {
            position: fixed;
            bottom: 10px;
            left: 0;
            right: 0;
            text-align: center;
            font-size: 8px;
            color: #999;
            border-top: 1px solid #ddd;
            padding-top: 5px;
        }

        .badge-late {
            background-color: #ffebee;
            color: #c62828;
            padding: 2px 6px;
            border-radius: 10px;
            font-weight: bold;
        }
    </style>
</head>

<body>
    @php
        $totalLate = $data->count();
        $totalUsers = $data->unique('user_id')->count();
        $totalLateMinutes = $data->sum(
            fn($a) => $a->isLate() ? $a->start_time->diffInMinutes($a->schedule_start_time) : 0,
        );
        $avgLateMinutes = $totalLate > 0 ? round($totalLateMinutes / $totalLate, 1) : 0;

        $groupByOffice = $data->groupBy(fn($a) => $a->user->schedule?->office?->name ?? 'Unknown');
        $worstOffice = $groupByOffice->sortDesc()->keys()->first();
        $worstCount = $groupByOffice->max()->count() ?? 0;
    @endphp

    <div class="header">
        <h2>LAPORAN KEDISIPLINAN KARYAWAN</h2>
        <p>PT Intiboga Mandiri</p>
        <p>Dicetak pada: {{ now()->format('d M Y H:i:s') }} | Oleh: {{ auth()->user()->name }}</p>
    </div>

    <div class="summary">
        <table>
            <tr>
                <td width="50%"><strong>Total Keterlambatan:</strong></td>
                <td width="50%">{{ $totalLate }} kali</td>
            </tr>
            <tr>
                <td><strong>Total Karyawan Terlambat:</strong></td>
                <td>{{ $totalUsers }} orang</td>
            </tr>
            <tr>
                <td><strong>Total Menit Keterlambatan:</strong></td>
                <td>{{ number_format($totalLateMinutes) }} menit ({{ round($totalLateMinutes / 60, 1) }} jam)</td>
            </tr>
            <tr>
                <td><strong>Rata-rata Keterlambatan:</strong></td>
                <td>{{ $avgLateMinutes }} menit per kejadian</td>
            </tr>
            <tr>
                <td><strong>Area Terbanyak Terlambat:</strong></td>
                <td>{{ $worstOffice ?? '-' }} ({{ $worstCount }} kali)</td>
            </tr>
        </table>
    </div>

    <table>
        <thead>
            <tr>
                <th>No</th>
                <th>Tanggal</th>
                <th>Nama Karyawan</th>
                <th>Jabatan</th>
                <th>Area</th>
                <th>Jadwal Masuk</th>
                <th>Jam Datang</th>
                <th>Durasi Terlambat</th>
                <th>Jam Pulang</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($data as $index => $row)
                @php
                    $lateMinutes = $row->isLate() ? $row->start_time->diffInMinutes($row->schedule_start_time) : 0;
                @endphp
                <tr>
                    <td class="text-center">{{ $index + 1 }}</td>
                    <td class="text-center">{{ $row->created_at->format('d/m/Y') }}</td>
                    <td>{{ $row->user->name ?? '-' }}</td>
                    <td>{{ $row->user->position?->name ?? '-' }}</td>
                    <td>{{ $row->user->schedule?->office?->name ?? '-' }}</td>
                    <td class="text-center">{{ $row->schedule_start_time?->format('H:i') ?? '-' }}</td>
                    <td class="text-center {{ $row->isLate() ? 'text-danger' : '' }}">
                        {{ $row->start_time?->format('H:i') ?? '-' }}
                    </td>
                    <td class="text-center">
                        @if ($row->isLate())
                            <span class="badge-late">{{ $lateMinutes }} menit</span>
                        @else
                            -
                        @endif
                    </td>
                    <td class="text-center">{{ $row->end_time?->format('H:i') ?? '-' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="9" class="text-center">Tidak ada data keterlambatan</td>
                </tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr style="background-color: #f2f2f2; font-weight: bold;">
                <td colspan="8" style="text-align: right;">TOTAL:</td>
                <td class="text-center">{{ $totalLate }} kejadian</td>
            </tr>
        </tfoot>
    </table>

    <div class="footer">
        Laporan ini dihasilkan secara otomatis oleh sistem | *Data hanya menampilkan keterlambatan
    </div>
</body>

</html>
