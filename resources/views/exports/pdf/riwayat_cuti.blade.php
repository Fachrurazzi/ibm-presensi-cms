<!DOCTYPE html>
<html>

<head>
    <title>Laporan Riwayat Cuti</title>
    <style>
        body {
            font-family: sans-serif;
            font-size: 11px;
            color: #333;
        }

        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #444;
            padding-bottom: 10px;
        }

        .header h2 {
            margin: 0;
            text-transform: uppercase;
            color: #000;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th,
        td {
            border: 1px solid #444;
            padding: 8px;
            text-align: left;
        }

        th {
            background-color: #f2f2f2;
            font-weight: bold;
            text-align: center;
            text-transform: uppercase;
        }

        .text-center {
            text-align: center;
        }

        .footer {
            margin-top: 20px;
            text-align: right;
            font-style: italic;
            font-size: 10px;
        }
    </style>
</head>

<body>
    <div class="header">
        <h2>{{ $title }}</h2>
        <p>PT Intiboga Mandiri - Laporan Operasional HRD</p>
        <p>Dicetak pada: {{ now()->format('d F Y H:i') }}</p>
    </div>

    <table>
        <thead>
            <tr>
                <th width="30px">No</th>
                <th>Nama Karyawan</th>
                <th>Keterangan / Alasan</th>
                <th width="100px">Mulai</th>
                <th width="100px">Selesai</th>
                <th width="150px">Catatan HRD</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($data as $index => $row)
                <tr>
                    <td class="text-center">{{ $index + 1 }}</td>
                    <td><strong>{{ $row->user->name ?? '-' }}</strong></td>
                    <td>{{ $row->reason }}</td>
                    <td class="text-center">{{ \Carbon\Carbon::parse($row->start_date)->format('d/m/Y') }}</td>
                    <td class="text-center">{{ \Carbon\Carbon::parse($row->end_date)->format('d/m/Y') }}</td>
                    <td>{{ $row->note ?? '-' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        Dokumen ini dihasilkan secara otomatis oleh Sistem Manajemen Absensi PT Intiboga Mandiri.
    </div>
</body>

</html>
