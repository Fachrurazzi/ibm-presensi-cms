<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Hash;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Builder;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
use Carbon\Carbon;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasFactory, Notifiable, HasRoles, HasApiTokens;

    public const DEFAULT_PASSWORD = 'password123';

    protected $fillable = [
        'name',
        'email',
        'password',
        'image',
        'position_id',
        'leave_quota',
        'cashable_leave',
        'join_date',
        'is_default_password',
        'face_model_path',
        'email_verified_at',
        'fcm_token',          // 👈 Pastikan ada
        'last_latitude',      // 👈 Pastikan ada
        'last_longitude',     // 👈 Pastikan ada
        'last_location_at',   // 👈 Pastikan ada
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'face_model_path',
    ];

    protected $appends = [
        'is_face_registered',
        'avatar_url',
        'remaining_leave_quota',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at'   => 'datetime',
            'password'            => 'hashed',
            'join_date'           => 'date',
            'is_default_password' => 'boolean',
            'cashable_leave'      => 'integer',
            'leave_quota'         => 'integer',
            'last_location_at'    => 'datetime', // 👈 Tambahkan
            'last_latitude'       => 'float',    // 👈 Tambahkan
            'last_longitude'      => 'float',    // 👈 Tambahkan
        ];
    }

    // ========== BOOT ==========

    protected static function booted(): void
    {
        static::creating(function (User $user) {
            if (empty($user->password)) {
                $user->password = self::DEFAULT_PASSWORD;
                $user->is_default_password = true;
            }
        });
    }

    // ========== ACCESSORS ==========

    public function getAvatarUrlAttribute(): string
    {
        if (!empty($this->image)) {
            return asset('storage/' . $this->image);
        }

        return sprintf(
            'https://ui-avatars.com/api/?name=%s&background=1D4ED8&color=fff&bold=true&length=2',
            urlencode($this->name)
        );
    }

    public function getIsFaceRegisteredAttribute(): bool
    {
        return $this->hasFaceRegistered();
    }

    public function getRemainingLeaveQuotaAttribute(): int
    {
        return $this->getRemainingLeaveQuota();
    }

    // ========== RELATIONSHIPS ==========

    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(Schedule::class);
    }

    public function todaySchedule(): HasOne
    {
        $today = Carbon::today()->toDateString();

        return $this->hasOne(Schedule::class)
            ->where('start_date', '<=', $today)
            ->where(function ($q) use ($today) {
                $q->whereNull('end_date')->orWhere('end_date', '>=', $today);
            })
            ->where('is_banned', false)
            ->latest('start_date');
    }

    public function scheduleForDate(string|\DateTimeInterface $date): HasOne
    {
        $dateStr = $date instanceof \DateTimeInterface
            ? $date->format('Y-m-d')
            : $date;

        return $this->hasOne(Schedule::class)
            ->where('start_date', '<=', $dateStr)
            ->where(function ($q) use ($dateStr) {
                $q->whereNull('end_date')->orWhere('end_date', '>=', $dateStr);
            })
            ->where('is_banned', false)
            ->latest('start_date');
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    public function leaves(): HasMany
    {
        return $this->hasMany(Leave::class);
    }

    public function permissions(): HasMany
    {
        return $this->hasMany(AttendancePermission::class);
    }

    // ========== BUSINESS LOGIC METHODS ==========

    public function hasVerifiedEmail(): bool
    {
        return !is_null($this->email_verified_at);
    }

    public function hasFaceRegistered(): bool
    {
        return !empty($this->face_model_path);
    }

    public function registerFace(string $faceModelJson): void
    {
        $this->face_model_path = $faceModelJson;
        $this->save();
    }

    public function updatePassword(string $newPassword): void
    {
        $this->password = Hash::make($newPassword);
        $this->is_default_password = false;
        $this->save();
    }

    public function resetToDefaultPassword(): void
    {
        $this->password = Hash::make(config('auth.default_password', self::DEFAULT_PASSWORD));
        $this->is_default_password = true;
        $this->save();
        $this->tokens()->delete();
    }

    public function getRemainingLeaveQuota(): int
    {
        try {
            $usedLeaves = $this->leaves()
                ->where('status', 'APPROVED')
                ->whereYear('start_date', now()->year)
                ->selectRaw('COALESCE(SUM(DATEDIFF(end_date, start_date) + 1), 0) as total_days')
                ->value('total_days');

            return max(0, (int) $this->leave_quota - (int) $usedLeaves);
        } catch (\Throwable $e) {
            \Log::error('getRemainingLeaveQuota error: ' . $e->getMessage(), [
                'user_id' => $this->id
            ]);
            return (int) $this->leave_quota; // fallback aman
        }
    }

    public function canTakeLeave(int $days): bool
    {
        return $this->getRemainingLeaveQuota() >= $days;
    }

    public function formatForApi(): array
    {
        return [
            'id'                  => $this->id,
            'name'                => $this->name,
            'email'               => $this->email,
            'email_verified_at'   => $this->email_verified_at?->toISOString(),
            'position_id'         => $this->position_id,
            'position_name'       => $this->position?->name,
            'image_url'           => $this->avatar_url,
            'join_date'           => $this->join_date?->format('Y-m-d'),
            'is_default_password' => (bool) $this->is_default_password,
            'is_face_registered'  => $this->is_face_registered,
            'leave_quota'         => (int) $this->leave_quota,
            'remaining_leave'     => $this->remaining_leave_quota,
            'cashable_leave'      => (int) $this->cashable_leave,
        ];
    }

    // ========== SCOPES ==========

    public function scopeVerified(Builder $query): Builder
    {
        return $query->whereNotNull('email_verified_at');
    }

    public function scopeUnverified(Builder $query): Builder
    {
        return $query->whereNull('email_verified_at');
    }

    public function scopeWithoutFace(Builder $query): Builder
    {
        return $query->whereNull('face_model_path')->orWhere('face_model_path', '');
    }

    public function scopeWithDefaultPassword(Builder $query): Builder
    {
        return $query->where('is_default_password', true);
    }

    public function scopeByPosition(Builder $query, int|array $positionId): Builder
    {
        if (is_array($positionId)) {
            return $query->whereIn('position_id', $positionId);
        }
        return $query->where('position_id', $positionId);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereHas('attendances', function ($q) {
            $q->whereDate('start_time', '>=', now()->subDays(30));
        });
    }
}
