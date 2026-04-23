<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class UserTemplateExport implements WithMultipleSheets, WithStyles
{
    public function sheets(): array
    {
        return [
            new TemplateSheet('Karyawan', $this->getKaryawanTemplate()),
            new TemplateSheet('Jabatan', $this->getJabatanTemplate()),
            new TemplateSheet('Kantor', $this->getKantorTemplate()),
            new TemplateSheet('Shift', $this->getShiftTemplate()),
            new TemplateSheet('Petunjuk', $this->getPetunjukTemplate()),
        ];
    }

    private function getKaryawanTemplate(): array
    {
        return [
            ['nama', 'email', 'jabatan', 'kantor', 'shift', 'tanggal_bergabung', 'sisa_cuti_awal'],
            ['Contoh: Budi Santoso', 'budi@intiboga.com', 'Karyawan Lapangan', 'Kantor Pusat Banjarmasin', 'Shift Kalimantan Selatan', '2024-01-01', 12],
            ['', '', '', '', '', '', ''],
        ];
    }

    private function getJabatanTemplate(): array
    {
        return [
            ['nama_jabatan'],
            ['Direktur Utama'],
            ['General Manager'],
            ['Manager Operasional'],
            ['Supervisor'],
            ['Staff Administrasi'],
            ['Staff HRD'],
            ['Staff Keuangan'],
            ['Staff IT'],
            ['Staff Marketing'],
            ['Karyawan Lapangan'],
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

    private function getShiftTemplate(): array
    {
        return [
            ['nama_shift', 'jam_mulai', 'jam_selesai'],
            ['Shift Kalimantan Selatan', '08:30:00', '17:30:00'],
            ['Shift Kalimantan Tengah', '07:30:00', '16:30:00'],
            ['Shift Pagi', '08:00:00', '17:00:00'],
        ];
    }

    private function getPetunjukTemplate(): array
    {
        return [
            ['PETUNJUK IMPORT DATA KARYAWAN', ''],
            ['', ''],
            ['1. JANGAN mengubah nama kolom pada sheet "Karyawan"!', ''],
            ['2. Isi data karyawan pada sheet "Karyawan" mulai baris ke-2', ''],
            ['3. Kolom "jabatan" harus sesuai dengan data di sheet "Jabatan"', ''],
            ['4. Kolom "kantor" harus sesuai dengan data di sheet "Kantor"', ''],
            ['5. Kolom "shift" harus sesuai dengan data di sheet "Shift"', ''],
            ['6. Format tanggal: YYYY-MM-DD (contoh: 2024-01-01)', ''],
            ['7. "sisa_cuti_awal" diisi angka (default 12 jika kosong)', ''],
            ['8. Email harus UNIK dan valid', ''],
            ['9. Password default untuk karyawan baru adalah "password"', ''],
            ['10. Karyawan akan diassign role "karyawan" secara otomatis', ''],
            ['', ''],
            ['⚠️ PERINGATAN:', ''],
            ['- Pastikan data sudah benar sebelum import', ''],
            ['- Data yang sudah ada akan dilewati (kecuali mode timpa aktif)', ''],
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->getStyle('A1:G1')->getFont()->setBold(true);
        $sheet->getStyle('A1:G1')->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FFE0E0E0');
        $sheet->getStyle('A1:G1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    }
}

// Sheet Class
class TemplateSheet implements \Maatwebsite\Excel\Concerns\FromArray, \Maatwebsite\Excel\Concerns\WithTitle
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
