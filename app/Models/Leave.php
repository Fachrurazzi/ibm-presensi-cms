<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;

class Leave extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'start_date',
        'end_date',
        'reason',
        'category',
        'status',
        'note',
    ];

    protected $casts = [
        'start_date' => 'date:Y-m-d',
        'end_date' => 'date:Y-m-d',
    ];

    protected $appends = ['duration', 'status_label', 'category_label'];

    // ========== ACCESSORS ==========

    public function getDurationAttribute()
    {
        if (!$this->start_date || !$this->end_date) return 0;

        // Tambahkan 1 hari karena jika tgl 1 s/d tgl 1 dihitung 1 hari
        return $this->start_date->diffInDays($this->end_date) + 1;
    }

    public function getStatusLabelAttribute()
    {
        return match ($this->status) {
            'APPROVED' => 'Disetujui',
            'REJECTED' => 'Ditolak',
            default => 'Menunggu Konfirmasi',
        };
    }

    public function getCategoryLabelAttribute()
    {
        return match ($this->category) {
            'annual' => 'Cuti Tahunan',
            'sick' => 'Cuti Sakit',
            'emergency' => 'Cuti Darurat',
            'maternity' => 'Cuti Melahirkan',
            'important' => 'Cuti Penting',
            default => 'Cuti Lainnya',
        };
    }

    // ========== RELATIONSHIPS ==========

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ========== BUSINESS LOGIC ==========

    public function exceedsQuota(): bool
    {
        $user = $this->user;
        if (!$user) return true;

        return $this->duration > $user->getRemainingLeaveQuota();
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

    public function scopeInYear($query, $year)
    {
        return $query->whereYear('start_date', $year);
    }

    public function scopeUpcoming($query)
    {
        return $query->where('end_date', '>=', now()->toDateString());
    }

    public function scopeByCategory($query, $category)
    {
        return $query->where('category', $category);
    }

    // ========== VALIDATION ==========

    public static function rules($id = null)
    {
        return [
            'user_id' => 'required|exists:users,id',
            'start_date' => 'required|date|after_or_equal:today',
            'end_date' => 'required|date|after_or_equal:start_date',
            'reason' => 'required|string|min:10|max:1000',
            'category' => 'required|in:annual,sick,emergency,maternity,important',
            'status' => 'in:PENDING,APPROVED,REJECTED',
            'note' => 'nullable|string|max:500',
        ];
    }

    // ========== EVENT LISTENERS ==========

    protected static function booted()
    {
        static::creating(function ($leave) {
            // Cek duplikasi tanggal
            $exists = static::where('user_id', $leave->user_id)
                ->where(function ($query) use ($leave) {
                    $query->whereBetween('start_date', [$leave->start_date, $leave->end_date])
                        ->orWhereBetween('end_date', [$leave->start_date, $leave->end_date]);
                })
                ->whereIn('status', ['PENDING', 'APPROVED'])
                ->exists();

            if ($exists) {
                throw new \Exception('Sudah ada pengajuan cuti di tanggal tersebut.');
            }
        });

        // static::updated(function ($leave) {
        //     // Jika status berubah jadi APPROVED, kurangi kuota cuti user
        //     if ($leave->isDirty('status') && $leave->status === 'APPROVED') {
        //         $user = $leave->user;
        //         if ($user && $user->leave_quota >= $leave->duration) {
        //             $user->leave_quota -= $leave->duration;
        //             $user->save();
        //         }
        //     }

        //     // Jika status berubah dari APPROVED ke REJECTED, kembalikan kuota
        //     if ($leave->isDirty('status') && $leave->getOriginal('status') === 'APPROVED' && $leave->status === 'REJECTED') {
        //         $user = $leave->user;
        //         if ($user) {
        //             $user->leave_quota += $leave->duration;
        //             $user->save();
        //         }
        //     }
        // });
    }
}
