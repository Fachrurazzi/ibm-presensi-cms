<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class AttendancePermission extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'attendance_permissions';

    protected $fillable = [
        'user_id',
        'type',
        'date',
        'reason',
        'image_proof',
        'status',
    ];

    protected $casts = [
        'date' => 'date:Y-m-d',
        'status' => 'string',
    ];

    protected $appends = ['image_proof_url', 'type_label', 'status_label'];

    // ========== ACCESSORS ==========

    public function getImageProofUrlAttribute()
    {
        if ($this->image_proof) {
            return url('storage/' . $this->image_proof);
        }
        return null;
    }

    public function getTypeLabelAttribute()
    {
        return match ($this->type) {
            'LATE' => 'Izin Terlambat',
            'EARLY_LEAVE' => 'Izin Pulang Cepat',
            'BUSINESS_TRIP' => 'Dinas Luar Kota',
            'SICK_WITH_CERT' => 'Sakit (Surat Dokter)',
            default => 'Izin Lainnya',
        };
    }

    public function getStatusLabelAttribute()
    {
        return match ($this->status) {
            'APPROVED' => 'Disetujui',
            'REJECTED' => 'Ditolak',
            default => 'Menunggu Persetujuan',
        };
    }

    // ========== RELATIONSHIPS ==========

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    // ========== BUSINESS LOGIC ==========

    public static function hasPermissionOnDate($userId, $date): bool
    {
        return static::where('user_id', $userId)
            ->whereDate('date', $date)
            ->whereIn('status', ['PENDING', 'APPROVED'])
            ->exists();
    }

    public function isEditable(): bool
    {
        return $this->status === 'PENDING';
    }

    public function canBeUsedForAttendance(): bool
    {
        return $this->status === 'APPROVED' && $this->date === now()->toDateString();
    }

    // ========== SCOPES ==========

    public function scopePending($query)
    {
        return $query->where('status', 'PENDING');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'APPROVED');
    }

    public function scopeRejected($query)
    {
        return $query->where('status', 'REJECTED');
    }

    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }

    public function scopeOnDate($query, $date)
    {
        return $query->whereDate('date', $date);
    }

    public function scopeThisMonth($query)
    {
        return $query->whereMonth('date', now()->month)
            ->whereYear('date', now()->year);
    }

    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    // ========== VALIDATION ==========

    public static function rules($id = null)
    {
        return [
            'user_id' => 'required|exists:users,id',
            'type' => 'required|in:LATE,EARLY_LEAVE,BUSINESS_TRIP,SICK_WITH_CERT',
            'date' => 'required|date',
            'reason' => 'required|string|min:10|max:1000',
            'image_proof' => 'nullable|string|max:255',
            'status' => 'in:PENDING,APPROVED,REJECTED',
        ];
    }

    // ========== EVENT LISTENERS ==========

    protected static function booted()
    {
        static::creating(function ($permission) {
            $exists = static::where('user_id', $permission->user_id)
                ->whereDate('date', $permission->date)
                ->whereIn('status', ['PENDING', 'APPROVED'])
                ->exists();

            if ($exists) {
                throw new \Exception('Sudah ada pengajuan izin untuk tanggal tersebut.');
            }
        });
    }
}
