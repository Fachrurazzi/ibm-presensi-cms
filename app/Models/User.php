<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasRoles, HasApiTokens;

    protected $fillable = [
        'name',
        'email',
        'password',
        'image',
        'position_id',
        'leave_quota',
        'join_date',
        'cashable_leave',
        'is_default_password',
        'face_model_path',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $appends = ['is_face_registered'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'join_date' => 'date',
        ];
    }

    // Accessor untuk mempermudah pemanggilan foto profil
    public function getAvatarUrlAttribute()
    {
        return $this->image
            ? asset('storage/' . $this->image)
            : 'https://ui-avatars.com/api/?name=' . urlencode($this->name) . '&background=f97316&color=fff';
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(Schedule::class);
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    public function leaves(): HasMany
    {
        return $this->hasMany(Leave::class);
    }

    public function getImageUrlAttribute()
    {
        return $this->image ? url('storage/' . $this->image) : null;
    }

    public function schedule()
    {
        return $this->hasOne(Schedule::class, 'user_id');
    }

    public function getIsFaceRegisteredAttribute()
    {
        return $this->face_model_path !== null;
    }
}
