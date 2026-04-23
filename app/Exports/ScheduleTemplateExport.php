<?php
// app/Exports/ScheduleTemplateExport.php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ScheduleTemplateExport implements WithMultipleSheets, WithStyles
{
    public function sheets(): array
    {
        return [
            new ScheduleTemplateSheet('Jadwal', $this->getJadwalTemplate()),
            new ScheduleTemplateSheet('Shift', $this->getShiftTemplate()),
            new ScheduleTemplateSheet('Kantor', $this->getKantorTemplate()),
            new ScheduleTemplateSheet('Petunjuk', $this->getPetunjukTemplate()),
        ];
    }

    private function getJadwalTemplate(): array
    {
        return [
            ['email_karyawan', 'shift', 'kantor', 'tanggal_mulai', 'tanggal_selesai', 'wfa'],
            ['budi@intiboga.com', 'Shift Kalimantan Selatan', 'Kantor Pusat Banjarmasin', '2024-01-01', '2026-12-31', 'Tidak'],
            ['', '', '', '', '', ''],
        ];
    }

    private function getShiftTemplate(): array
    {
        return [
            ['nama_shift', 'jam_mulai', 'jam_selesai'],
            ['Shift Kalimantan Selatan', '08:30:00', '17:30:00'],
            ['Shift Kalimantan Tengah', '07:30:00', '16:30:00'],
            ['Shift Pagi', '08:00:00', '17:00:00'],
        ];
    }

    private function getKantorTemplate(): array
    {
        return [
            ['nama_kantor'],
            ['Kantor Pusat Banjarmasin'],
            ['Kantor Cabang Palangka Raya'],
            ['Kantor Cabang Balikpapan'],
        ];
    }

    private function getPetunjukTemplate(): array
    {
        return [
            ['PETUNJUK IMPORT JADWAL KERJA', ''],
            ['', ''],
            ['1. Pastikan email karyawan sudah terdaftar di sistem', ''],
            ['2. Nama shift harus sesuai dengan data di sheet "Shift"', ''],
            ['3. Nama kantor harus sesuai dengan data di sheet "Kantor"', ''],
            ['4. Format tanggal: YYYY-MM-DD (contoh: 2024-01-01)', ''],
            ['5. Kolom WFA diisi "Ya" atau "Tidak"', ''],
            ['6. Kosongkan "tanggal_selesai" jika schedule berlaku selamanya', ''],
            ['7. Jika ada schedule overlap, akan ditampilkan error', ''],
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->getStyle('A1:F1')->getFont()->setBold(true);
        $sheet->getStyle('A1:F1')->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FFE0E0E0');
    }
}

class ScheduleTemplateSheet implements \Maatwebsite\Excel\Concerns\FromArray, \Maatwebsite\Excel\Concerns\WithTitle
{
    protected $title;
    protected $data;

    public function __construct($title, $data)
    {
        $this->title = $title;
        $this->data = $data;
    }

    public function array(): array
    {
        return $this->data;
    }

    public function title(): string
    {
        return $this->title;
    }
}
