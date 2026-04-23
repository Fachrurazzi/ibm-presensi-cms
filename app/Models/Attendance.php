<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Attendance extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'attendances';

    protected $appends = [
        'start_time_format',
        'end_time_format',
        'is_late_bool',
        'lunch_money_label',
        'work_duration_text'
    ];

    protected $casts = [
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'schedule_start_time' => 'datetime',
        'schedule_end_time' => 'datetime',
        'start_latitude' => 'float',
        'start_longitude' => 'float',
        'end_latitude' => 'float',
        'end_longitude' => 'float',
        'schedule_latitude' => 'float',
        'schedule_longitude' => 'float',
    ];

    protected $fillable = [
        'user_id',
        'schedule_id',
        'attendance_permission_id',
        'schedule_latitude',
        'schedule_longitude',
        'schedule_start_time',
        'schedule_end_time',
        'start_latitude',
        'start_longitude',
        'end_latitude',
        'end_longitude',
        'start_time',
        'end_time',
    ];

    // ========== ACCESSORS ==========

    public function getStartTimeFormatAttribute()
    {
        return $this->start_time?->format('H:i') ?? '--:--';
    }

    public function getEndTimeFormatAttribute()
    {
        return $this->end_time?->format('H:i') ?? '--:--';
    }

    public function getIsLateBoolAttribute()
    {
        return $this->isLate();
    }

    /**
     * Uang makan berdasarkan aturan:
     * - BUSINESS_TRIP: tetap dapat Rp 15.000
     * - Izin lainnya (sakit/cuti/dll): Rp 0
     * - Terlambat (tanpa izin): Rp 0
     * - Normal (tepat waktu): Rp 15.000
     */
    public function getLunchMoneyLabelAttribute()
    {
        // Kasus: Ada izin
        if ($this->attendance_permission_id) {
            $permissionType = $this->permission?->type;

            // BUSINESS_TRIP tetap dapat uang makan
            if ($permissionType === 'BUSINESS_TRIP') {
                return 'Rp 15.000 (Dinas Luar Kota)';
            }

            return 'Rp 0 (Izin)';
        }

        // Kasus: Terlambat (tanpa izin)
        if ($this->isLate()) {
            return 'Rp 0 (Terlambat)';
        }

        // Kasus: Normal
        return 'Rp 15.000';
    }

    public function getWorkDurationTextAttribute()
    {
        return $this->workDuration();
    }

    // ========== RELATIONSHIPS ==========

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(Schedule::class);
    }

    public function permission(): BelongsTo
    {
        return $this->belongsTo(AttendancePermission::class, 'attendance_permission_id');
    }

    // ========== BUSINESS LOGIC ==========

    /**
     * Cek keterlambatan
     * Tidak ada toleransi - lewat 1 detik pun terlambat
     */
    public function isLate(): bool
    {
        if (!$this->start_time || !$this->schedule_start_time) {
            return false;
        }

        return $this->start_time->gt($this->schedule_start_time);
    }

    /**
     * Hitung durasi kerja
     */
    public function workDuration(): string
    {
        if (!$this->start_time || !$this->end_time) {
            return "0 jam 0 menit";
        }

        $diff = $this->start_time->diff($this->end_time);
        $hours = $diff->h;
        $minutes = $diff->i;

        return "{$hours} jam {$minutes} menit";
    }

    /**
     * Validasi lokasi absen
     */
    public function isValidLocation(float $userLatitude, float $userLongitude, int $radiusInMeters = 100): bool
    {
        if (!$this->schedule_latitude || !$this->schedule_longitude) {
            return false;
        }

        $distance = $this->calculateDistance(
            $userLatitude,
            $userLongitude,
            (float) $this->schedule_latitude,
            (float) $this->schedule_longitude
        );

        return $distance <= $radiusInMeters;
    }

    /**
     * Hitung jarak antara dua koordinat (Haversine formula)
     */
    private function calculateDistance(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371000; // Meter

        $latDelta = deg2rad($lat2 - $lat1);
        $lonDelta = deg2rad($lon2 - $lon1);

        $a = sin($latDelta / 2) * sin($latDelta / 2) +
            cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
            sin($lonDelta / 2) * sin($lonDelta / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }

    // ========== SCOPES ==========

    public function scopeToday($query)
    {
        return $query->whereDate('start_time', today());
    }

    public function scopeThisMonth($query)
    {
        return $query->whereMonth('start_time', now()->month)
            ->whereYear('start_time', now()->year);
    }

    public function scopeLate($query)
    {
        return $query->whereRaw('TIME(start_time) > TIME(schedule_start_time)');
    }

    public function scopeOnTime($query)
    {
        return $query->whereRaw('TIME(start_time) <= TIME(schedule_start_time)');
    }

    public function scopeDateBetween($query, $startDate, $endDate)
    {
        return $query->whereBetween('start_time', [$startDate, $endDate]);
    }

    public function scopeHasNotPulang($query)
    {
        return $query->whereNull('end_time');
    }

    // ========== VALIDATION ==========

    public static function rules($id = null)
    {
        return [
            'user_id' => 'required|exists:users,id',
            'schedule_id' => 'nullable|exists:schedules,id',
            'attendance_permission_id' => 'nullable|exists:attendance_permissions,id',
            'start_latitude' => 'required|numeric|between:-90,90',
            'start_longitude' => 'required|numeric|between:-180,180',
            'start_time' => 'required|date',
            'end_latitude' => 'nullable|numeric|between:-90,90',
            'end_longitude' => 'nullable|numeric|between:-180,180',
            'end_time' => 'nullable|date|after:start_time',
        ];
    }
}
