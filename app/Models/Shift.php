<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;

class Shift extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'start_time',
        'end_time',
        'description',
    ];

    protected $appends = [
        'start_time_display',
        'end_time_display',
        'duration_hours',
        'is_overnight',
    ];

    // ========== ACCESSORS ==========

    public function getStartTimeDisplayAttribute()
    {
        return $this->start_time
            ? Carbon::parse($this->start_time)->format('H:i')
            : '--:--';
    }

    public function getEndTimeDisplayAttribute()
    {
        return $this->end_time
            ? Carbon::parse($this->end_time)->format('H:i')
            : '--:--';
    }

    public function getDurationHoursAttribute()
    {
        return $this->getDurationInHours();
    }

    public function getIsOvernightAttribute()
    {
        return $this->isOvernight();
    }

    // ========== BUSINESS LOGIC ==========

    /**
     * Cek apakah shift melewati tengah malam
     */
    public function isOvernight(): bool
    {
        if (!$this->start_time || !$this->end_time) {
            return false;
        }

        return $this->start_time > $this->end_time;
    }

    /**
     * Hitung durasi shift dalam jam
     */
    public function getDurationInHours(): float
    {
        if (!$this->start_time || !$this->end_time) {
            return 0;
        }

        $start = Carbon::parse($this->start_time);
        $end = Carbon::parse($this->end_time);

        if ($this->isOvernight()) {
            $end->addDay();
        }

        return $start->diffInHours($end);
    }

    /**
     * Get end time dengan adjustment untuk overnight shift
     */
    public function getAdjustedEndTime()
    {
        if (!$this->isOvernight()) {
            return Carbon::parse($this->end_time);
        }

        return Carbon::parse($this->end_time)->addDay();
    }

    // ========== RELATIONSHIPS ==========

    public function schedules(): HasMany
    {
        return $this->hasMany(Schedule::class);
    }

    // ========== SCOPES ==========

    public function scopeActive($query)
    {
        return $query->whereNull('deleted_at');
    }



    public function scopeRegular($query)
    {
        return $query->whereRaw('start_time < end_time');
    }

    public function scopeOvernight($query)
    {
        return $query->whereRaw('start_time > end_time');
    }

    // ========== VALIDATION ==========

    public static function rules($id = null)
    {
        return [
            'name' => 'required|string|max:255|unique:shifts,name,' . $id,
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i',
        ];
    }

    // ========== EVENT LISTENERS ==========

    protected static function booted()
    {
        static::saving(function ($shift) {
            // Validasi start_time < end_time untuk non-overnight shift
            if ($shift->start_time >= $shift->end_time && !$shift->isOvernight()) {
                throw new \Exception('Jam mulai harus lebih awal dari jam selesai untuk shift reguler.');
            }
        });

        static::deleting(function ($shift) {
            // Cegah penghapusan shift yang masih memiliki schedule
            if ($shift->schedules()->count() > 0) {
                throw new \Exception('Tidak dapat menghapus shift yang masih memiliki jadwal.');
            }
        });
    }
}
