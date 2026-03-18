<!DOCTYPE html>
<html>

<head>
    <title>Laporan Kedisiplinan Karyawan</title>
    <style>
        body {
            font-family: sans-serif;
            font-size: 12px;
        }

        .header {
            text-align: center;
            margin-bottom: 20px;
        }

        .header h2 {
            margin: 0;
            padding: 0;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
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
        }

        .text-center {
            text-align: center;
        }

        .text-danger {
            color: red;
            font-weight: bold;
        }
    </style>
</head>

<body>
    <div class="header">
        <h2>LAPORAN KEDISIPLINAN KARYAWAN</h2>
        <p>PT Intiboga Mandiri - Dicetak pada: {{ now()->format('d M Y H:i') }}</p>
    </div>

    <table>
        <thead>
            <tr>
                <th>No</th>
                <th>Tanggal</th>
                <th>Nama Karyawan</th>
                <th>Jam Datang</th>
                <th>Jam Pulang</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($data as $index => $row)
                <tr>
                    <td class="text-center">{{ $index + 1 }}</td>
                    <td class="text-center">{{ $row->created_at->format('d/m/Y') }}</td>
                    <td>{{ $row->user->name ?? '-' }}</td>
                    <td class="text-center">{{ $row->start_time ? $row->start_time->format('H:i') : '-' }}</td>
                    <td class="text-center">{{ $row->end_time ? $row->end_time->format('H:i') : '-' }}</td>
                    <td class="text-center {{ $row->isLate() ? 'text-danger' : '' }}">
                        {{ $row->isLate() ? 'Terlambat' : 'Tepat Waktu' }}
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>

</html>
