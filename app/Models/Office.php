<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Office extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'latitude',
        'longitude',
        'radius',
        'supervisor_name',
    ];

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
        'radius' => 'integer',
    ];

    protected $appends = [
        'google_maps_url',
        'radius_display',
        'location_full',
    ];

    // ========== ACCESSORS ==========

    public function getGoogleMapsUrlAttribute()
    {
        return "https://www.google.com/maps/search/?api=1&query={$this->latitude},{$this->longitude}";
    }

    public function getRadiusDisplayAttribute()
    {
        return $this->radius . ' meter';
    }

    public function getLocationFullAttribute()
    {
        return "{$this->latitude}, {$this->longitude}";
    }

    // ========== RELATIONSHIPS ==========

    public function schedules(): HasMany
    {
        return $this->hasMany(Schedule::class);
    }

    // ========== BUSINESS LOGIC ==========

    /**
     * Hitung jarak dari kantor ke koordinat user (dalam meter)
     */
    public function calculateDistance(float $userLatitude, float $userLongitude): float
    {
        $earthRadius = 6371000; // Meter

        $latDelta = deg2rad($userLatitude - $this->latitude);
        $lonDelta = deg2rad($userLongitude - $this->longitude);

        $a = sin($latDelta / 2) * sin($latDelta / 2) +
            cos(deg2rad($this->latitude)) * cos(deg2rad($userLatitude)) *
            sin($lonDelta / 2) * sin($lonDelta / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }

    /**
     * Cek apakah user berada dalam radius kantor
     */
    public function isWithinRadius(float $userLatitude, float $userLongitude, ?int $customRadius = null): bool
    {
        $radius = $customRadius ?? $this->radius;
        $distance = $this->calculateDistance($userLatitude, $userLongitude);

        return $distance <= $radius;
    }

    // ========== SCOPES ==========

    public function scopeActive($query)
    {
        return $query->whereNull('deleted_at');
    }

    /**
     * Scope untuk filter kantor terdekat dalam radius
     */
    public function scopeNearest($query, float $latitude, float $longitude, int $limit = 5)
    {
        return $query->selectRaw('*, (
            6371000 * acos(
                cos(radians(?)) * cos(radians(latitude)) *
                cos(radians(longitude) - radians(?)) +
                sin(radians(?)) * sin(radians(latitude))
            )
        ) AS distance', [$latitude, $longitude, $latitude])
            ->having('distance', '<=', 'radius')
            ->orderBy('distance')
            ->limit($limit);
    }

    // ========== VALIDATION ==========

    public static function rules($id = null)
    {
        return [
            'name' => 'required|string|max:255|unique:offices,name,' . $id,
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'radius' => 'required|integer|min:10|max:1000',
            'supervisor_name' => 'nullable|string|max:255',
        ];
    }
}
