<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
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
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'birthday'            => 'date',
            'license_expiry_date' => 'date',
            'email_verified_at'   => 'datetime',
            'dl_codes'            => 'array',
            'load_capacity'       => 'integer',
            'password'            => 'hashed',
            'is_online'           => 'boolean',
            'last_seen_at'        => 'datetime',
            'sla_strikes'         => 'integer',
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
}