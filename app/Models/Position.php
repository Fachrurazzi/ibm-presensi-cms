<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Position extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['name'];

    protected $appends = ['user_count'];

    // ========== RELATIONSHIPS ==========

    /**
     * Relasi ke User
     * Satu jabatan bisa dimiliki oleh banyak karyawan
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    // ========== ACCESSORS ==========

    /**
     * Hitung jumlah karyawan per jabatan
     */
    public function getUserCountAttribute()
    {
        return $this->users()->count();
    }

    // ========== SCOPES ==========

    /**
     * Scope untuk position yang masih aktif (tidak di-soft delete)
     */
    public function scopeActive($query)
    {
        return $query->whereNull('deleted_at');
    }

    // ========== VALIDATION ==========

    public static function rules($id = null)
    {
        return [
            'name' => 'required|string|max:255|unique:positions,name,' . $id,
        ];
    }

    // ========== EVENT LISTENERS ==========

    protected static function booted()
    {
        static::deleting(function ($position) {
            // Cegah penghapusan position yang masih memiliki user
            if ($position->users()->count() > 0) {
                throw new \Exception('Tidak dapat menghapus jabatan yang masih memiliki karyawan.');
            }
        });
    }
}
