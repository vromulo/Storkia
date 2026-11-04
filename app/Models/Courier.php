<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\HasApiTokens;

class Courier extends Authenticatable
{
    use HasApiTokens, Notifiable;

    protected $table = 'couriers';

    protected $fillable = [
        'logistics_profile_id',
        'first_name',
        'last_name',
        'birthday',
        'email',
        'email_verified_at',
        'contact_no',
        'avatar_path',
        'license_no',
        'license_type',
        'dl_codes',
        'license_expiry_date',
        'vehicle_type',
        'plate_number',
        'load_capacity',
        'home_hub',
        'license_path',
        'or_cr_path',
        'nbi_clearance_path',
        'password',
        'status',
        'is_online',
        'last_seen_at',
        'sla_strikes',
        'current_latitude',
        'current_longitude',
        'location_accuracy',
        'location_updated_at',
        'vehicle_verification_status',
        'last_checklist_passed_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'birthday'                 => 'date',
            'license_expiry_date'      => 'date',
            'email_verified_at'        => 'datetime',
            'dl_codes'                 => 'array',
            'load_capacity'            => 'integer',
            'password'                 => 'hashed',
            'is_online'                => 'boolean',
            'last_seen_at'             => 'datetime',
            'sla_strikes'              => 'integer',
            'current_latitude'         => 'decimal:7',
            'current_longitude'        => 'decimal:7',
            'location_accuracy'        => 'decimal:2',
            'location_updated_at'      => 'datetime',
            'last_checklist_passed_at' => 'datetime',
        ];
    }

    public function logisticsProfile()
    {
        return $this->belongsTo(LogisticsProfile::class, 'logistics_profile_id');
    }

    public function hubAssignments()
    {
        return $this->hasMany(LogisticsEmployeeHubAssignment::class, 'courier_id')->latest('id');
    }

    public function currentHubAssignment()
    {
        return $this->hasOne(LogisticsEmployeeHubAssignment::class, 'courier_id')
            ->where('is_current', true)
            ->latestOfMany();
    }

    public function pickupAssignments()
    {
        return $this->hasMany(PickupAssignment::class, 'courier_id')->latest('id');
    }

    public function locations()
    {
        return $this->hasMany(CourierLocation::class, 'courier_id')->latest('recorded_at');
    }

    public function latestLocation()
    {
        return $this->hasOne(CourierLocation::class, 'courier_id')->latestOfMany('recorded_at');
    }

    public function activeShipments()
    {
        return $this->hasMany(Shipment::class, 'current_courier_id');
    }

    public function getAssignedHubAttribute(): ?LogisticsHub
    {
        $assignment = $this->relationLoaded('currentHubAssignment')
            ? $this->currentHubAssignment
            : $this->currentHubAssignment()->with('hub')->first();

        if ($assignment && $assignment->hub) {
            return $assignment->hub;
        }

        return $this->logisticsProfile?->ensureMainHubExists();
    }

    public function getAssignedMunicipalityAttribute(): string
    {
        return $this->assigned_hub?->municipality
            ?? $this->logisticsProfile?->municipality
            ?? '';
    }

    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    public function getFormattedDlCodesAttribute(): string
    {
        return is_array($this->dl_codes) ? implode(', ', $this->dl_codes) : (string) $this->dl_codes;
    }

    public function getAvatarUrlAttribute(): ?string
    {
        if ($this->avatar_path && Storage::disk('public')->exists($this->avatar_path)) {
            return asset('storage/' . ltrim($this->avatar_path, '/'));
        }

        return null;
    }
}