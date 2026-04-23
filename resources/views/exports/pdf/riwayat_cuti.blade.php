<!DOCTYPE html>
<html>

<head>
    <title>Laporan Riwayat Cuti</title>
    <style>
        body {
            font-family: 'Arial Narrow', sans-serif;
            font-size: 10px;
            color: #333;
            margin: 0;
            padding: 10px;
        }

        .header {
            text-align: center;
            margin-bottom: 20px;
            border-bottom: 2px solid #444;
            padding-bottom: 10px;
        }

        .header h2 {
            margin: 0;
            text-transform: uppercase;
            color: #000;
            font-size: 14px;
        }

        .header p {
            margin: 5px 0 0;
            font-size: 9px;
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
        }

        th,
        td {
            border: 1px solid #444;
            padding: 6px 8px;
            text-align: left;
        }

        th {
            background-color: #f2f2f2;
            font-weight: bold;
            text-align: center;
            text-transform: uppercase;
            font-size: 9px;
        }

        .text-center {
            text-align: center;
        }

        .footer {
            margin-top: 20px;
            text-align: center;
            font-style: italic;
            font-size: 8px;
            color: #999;
            border-top: 1px solid #ddd;
            padding-top: 5px;
        }

        .badge-approved {
            background-color: #d4edda;
            color: #155724;
            padding: 2px 8px;
            border-radius: 10px;
            font-weight: bold;
        }

        .badge-rejected {
            background-color: #f8d7da;
            color: #721c24;
            padding: 2px 8px;
            border-radius: 10px;
            font-weight: bold;
        }

        .badge-pending {
            background-color: #fff3cd;
            color: #856404;
            padding: 2px 8px;
            border-radius: 10px;
            font-weight: bold;
        }
    </style>
</head>

<body>
    @php
        $totalLeave = $data->count();
        $totalDays = $data->sum('duration');
        $totalUsers = $data->unique('user_id')->count();
        $avgDays = $totalLeave > 0 ? round($totalDays / $totalLeave, 1) : 0;

        $byCategory = $data->groupBy('category')->map(fn($g) => $g->count());
        $byStatus = $data->groupBy('status')->map(fn($g) => $g->count());

        $startDate = $data->min('start_date');
        $endDate = $data->max('end_date');
    @endphp

    <div class="header">
        <h2>{{ $title }}</h2>
        <p>PT Intiboga Mandiri - Laporan Operasional HRD</p>
        <p>Periode: {{ $startDate ? \Carbon\Carbon::parse($startDate)->format('d M Y') : '-' }} -
            {{ $endDate ? \Carbon\Carbon::parse($endDate)->format('d M Y') : '-' }}</p>
        <p>Dicetak pada: {{ now()->format('d F Y H:i:s') }} | Oleh: {{ auth()->user()->name }}</p>
    </div>

    <div class="summary">
        <table>
            <tr>
                <td width="25%"><strong>Total Pengajuan:</strong></td>
                <td width="25%">{{ number_format($totalLeave) }} kali</td>
                <td width="25%"><strong>Total Hari Cuti:</strong></td>
                <td width="25%">{{ number_format($totalDays) }} hari</td>
            </tr>
            <tr>
                <td><strong>Total Karyawan:</strong></td>
                <td>{{ number_format($totalUsers) }} orang</td>
                <td><strong>Rata-rata per Pengajuan:</strong></td>
                <td>{{ $avgDays }} hari</td>
            </tr>
            <tr>
                <td><strong>Berdasarkan Jenis:</strong></td>
                <td colspan="3">
                    @foreach ($byCategory as $cat => $count)
                        <span style="margin-right: 15px;">
                            {{ ucfirst(str_replace('_', ' ', $cat)) }}: {{ $count }}
                        </span>
                    @endforeach
                </td>
            </tr>
            <tr>
                <td><strong>Berdasarkan Status:</strong></td>
                <td colspan="3">
                    @foreach ($byStatus as $stat => $count)
                        <span style="margin-right: 15px;">
                            {{ ucfirst($stat) }}: {{ $count }}
                        </span>
                    @endforeach
                </td>
            </tr>
        </table>
    </div>

    <table>
        <thead>
            <tr>
                <th width="30px">No</th>
                <th>Nama Karyawan</th>
                <th>Jabatan</th>
                <th width="80px">Jenis</th>
                <th>Keterangan / Alasan</th>
                <th width="80px">Mulai</th>
                <th width="80px">Selesai</th>
                <th width="60px">Durasi</th>
                <th width="80px">Status</th>
                <th width="120px">Catatan HRD</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($data as $index => $row)
                <tr>
                    <td class="text-center">{{ $index + 1 }}</td>
                    <td><strong>{{ $row->user->name ?? '-' }}</strong></td>
                    <td>{{ $row->user->position?->name ?? '-' }}</td>
                    <td class="text-center">{{ $row->category_label }}</td>
                    <td>{{ Str::limit($row->reason, 50) }}</td>
                    <td class="text-center">{{ \Carbon\Carbon::parse($row->start_date)->format('d/m/Y') }}</td>
                    <td class="text-center">{{ \Carbon\Carbon::parse($row->end_date)->format('d/m/Y') }}</td>
                    <td class="text-center">{{ $row->duration }} hari</td>
                    <td class="text-center">
                        <span class="badge-{{ strtolower($row->status) }}">
                            {{ $row->status_label }}
                        </span>
                    </td>
                    <td>{{ $row->note ?? '-' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="10" class="text-center">Tidak ada data cuti/izin</td>
                </tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr style="background-color: #f2f2f2; font-weight: bold;">
                <td colspan="8" style="text-align: right;">TOTAL:</td>
                <td class="text-center">{{ $totalLeave }} pengajuan</td>
                <td class="text-center">{{ number_format($totalDays) }} hari</td>
            </tr>
        </tfoot>
    </table>

    <div class="footer">
        Dokumen ini dihasilkan secara otomatis oleh Sistem Manajemen Absensi PT Intiboga Mandiri.
        | *Data hanya menampilkan pengajuan cuti/izin yang sudah diproses
    </div>
</body>

</html>
