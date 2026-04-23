<?php
// app/Models/Schedule.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Carbon\Carbon;

class Schedule extends Model
{
    use HasFactory;

    protected $table = 'schedules';

    protected $fillable = [
        'user_id',
        'shift_id',
        'office_id',
        'start_date',
        'end_date',
        'is_wfa',
        'is_banned',
        'banned_reason',
    ];

    protected $casts = [
        'start_date' => 'date:Y-m-d',
        'end_date' => 'date:Y-m-d',
        'is_wfa' => 'boolean',
        'is_banned' => 'boolean',
    ];

    protected $appends = [
        'shift_time_display',
        'is_active',
        'date_range_display',
    ];

    // ========== ACCESSORS ==========

    public function getShiftTimeDisplayAttribute()
    {
        if (!$this->shift) {
            return '--:-- - --:--';
        }

        return $this->shift->start_time_display . ' - ' . $this->shift->end_time_display;
    }

    public function getIsActiveAttribute()
    {
        $today = Carbon::today();
        return !$this->is_banned
            && $this->start_date <= $today
            && ($this->end_date === null || $this->end_date >= $today);
    }

    public function getDateRangeDisplayAttribute()
    {
        $start = Carbon::parse($this->start_date)->format('d M Y');
        if ($this->end_date) {
            return $start . ' → ' . Carbon::parse($this->end_date)->format('d M Y');
        }
        return $start . ' → Sekarang';
    }

    // ========== RELATIONSHIPS ==========

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    public function office(): BelongsTo
    {
        return $this->belongsTo(Office::class);
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    // ========== BUSINESS LOGIC ==========

    /**
     * Ambil schedule yang aktif pada tanggal tertentu
     */
    public static function getActiveSchedule($userId, $date = null)
    {
        $date = $date ?: Carbon::today()->toDateString();

        return static::with(['shift', 'office'])
            ->where('user_id', $userId)
            ->where('start_date', '<=', $date)
            ->where(function ($q) use ($date) {
                $q->whereNull('end_date')->orWhere('end_date', '>=', $date);
            })
            ->first();
    }

    /**
     * Cek apakah user memiliki schedule aktif hari ini
     */
    public static function hasActiveScheduleToday($userId): bool
    {
        return self::getActiveSchedule($userId) !== null;
    }

    /**
     * Akhiri schedule (set end_date = kemarin)
     */
    public function endSchedule()
    {
        $this->end_date = Carbon::yesterday();
        $this->save();
    }

    /**
     * Perpanjang schedule (hilangkan end_date)
     */
    public function extendSchedule()
    {
        $this->end_date = null;
        $this->save();
    }

    // ========== SCOPES ==========

    /**
     * Scope untuk schedule yang aktif saat ini
     */
    public function scopeActive($query)
    {
        $today = Carbon::today()->toDateString();
        return $query->where('is_banned', false)
            ->where('start_date', '<=', $today)
            ->where(function ($q) use ($today) {
                $q->whereNull('end_date')->orWhere('end_date', '>=', $today);
            });
    }

    /**
     * Scope untuk schedule yang aktif pada tanggal tertentu
     */
    public function scopeActiveOnDate($query, $date)
    {
        return $query->where('start_date', '<=', $date)
            ->where(function ($q) use ($date) {
                $q->whereNull('end_date')->orWhere('end_date', '>=', $date);
            });
    }

    /**
     * Scope untuk schedule yang sudah berakhir
     */
    public function scopeExpired($query)
    {
        $today = Carbon::today()->toDateString();
        return $query->whereNotNull('end_date')->where('end_date', '<', $today);
    }

    /**
     * Scope untuk schedule yang masih berlaku (belum berakhir)
     */
    public function scopeCurrent($query)
    {
        $today = Carbon::today()->toDateString();
        return $query->where(function ($q) use ($today) {
            $q->whereNull('end_date')->orWhere('end_date', '>=', $today);
        });
    }

    public function scopeForUser($query, $userId)
    {
        return $query->with(['shift', 'office', 'user.position'])
            ->where('user_id', $userId);
    }

    // ========== VALIDATION ==========

    public static function rules($id = null)
    {
        return [
            'user_id' => 'required|exists:users,id',
            'shift_id' => 'required|exists:shifts,id',
            'office_id' => 'required|exists:offices,id',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'is_wfa' => 'boolean',
            'is_banned' => 'boolean',
            'banned_reason' => 'nullable|string|max:255|required_if:is_banned,true',
        ];
    }

    // ========== EVENT LISTENERS ==========

    protected static function booted()
    {
        static::creating(function ($schedule) {
            // Cek apakah ada schedule yang overlap
            $overlap = static::where('user_id', $schedule->user_id)
                ->where(function ($q) use ($schedule) {
                    $q->whereBetween('start_date', [$schedule->start_date, $schedule->end_date ?? '9999-12-31'])
                        ->orWhereBetween('end_date', [$schedule->start_date, $schedule->end_date ?? '9999-12-31'])
                        ->orWhere(function ($sub) use ($schedule) {
                            $sub->where('start_date', '<=', $schedule->start_date)
                                ->where(function ($q2) use ($schedule) {
                                    $q2->whereNull('end_date')->orWhere('end_date', '>=', $schedule->start_date);
                                });
                        });
                })
                ->exists();

            if ($overlap) {
                throw new \Exception('Jadwal overlap dengan schedule yang sudah ada untuk periode ini.');
            }
        });
    }
}
