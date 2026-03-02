<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Attendance extends Model
{
    protected $casts = [
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'schedule_start_time' => 'datetime',
        'schedule_end_time' => 'datetime',
        'start_latitude' => 'float',
        'start_longitude' => 'float',
        'end_latitude' => 'float',
        'end_longitude' => 'float',
    ];

    protected $fillable = [
        'user_id',
        'schedule_id',
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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(Schedule::class);
    }

    public function isLate(): bool
    {
        if (!$this->start_time || !$this->schedule_start_time) return false;

        // Perbaikan: Hanya bandingkan Jam dan Menit (H:i)
        // Dengan begini, 08:30 tidak akan lebih besar dari 08:30.
        // Status terlambat baru muncul jika sudah masuk 08:31.
        return $this->start_time->format('H:i') > $this->schedule_start_time->format('H:i');
    }

    public function workDuration(): string
    {
        if (!$this->start_time || !$this->end_time) return "0 j, 0 m";

        $diff = $this->start_time->diff($this->end_time);
        return "{$diff->h} j, {$diff->i} m";
    }
}
