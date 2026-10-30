<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class Rider extends Authenticatable
{
    use HasApiTokens, Notifiable;

    protected $table = 'riders';

    protected $fillable = [
        'logistics_profile_id',
        'first_name',
        'last_name',
        'birthday',
        'email',
        'email_verified_at',
        'contact_no',
        'license_no',
        'license_expiry_date',
        'vehicle_type',
        'plate_number',
        'license_path',
        'or_cr_path',
        'password',
        'status',
        'approved_at',
        'rejected_at',
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
            'approved_at'         => 'datetime',
            'rejected_at'         => 'datetime',
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
        return $this->hasMany(LogisticsEmployeeHubAssignment::class, 'rider_id')->latest('id');
    }

    public function currentHubAssignment()
    {
        return $this->hasOne(LogisticsEmployeeHubAssignment::class, 'rider_id')
            ->where('is_current', true)
            ->latestOfMany();
    }

    public function barangayCoverages()
    {
        return $this->hasMany(RiderBarangayCoverage::class, 'rider_id')
            ->where('is_active', true)
            ->orderBy('barangay_name');
    }

    public function allBarangayCoverages()
    {
        return $this->hasMany(RiderBarangayCoverage::class, 'rider_id')->latest('id');
    }

    public function deliveryAssignments()
    {
        return $this->hasMany(DeliveryAssignment::class, 'rider_id')->latest('id');
    }

    public function deliveryAttempts()
    {
        return $this->hasMany(DeliveryAttempt::class, 'rider_id')->latest('id');
    }

    public function deliveryProofs()
    {
        return $this->hasMany(DeliveryProof::class, 'rider_id')->latest('id');
    }

    public function codCollections()
    {
        return $this->hasMany(CodCollection::class, 'rider_id')->latest('id');
    }

    public function codRemittances()
    {
        return $this->hasMany(CodRemittance::class, 'rider_id')->latest('id');
    }

    public function commissionRecords()
    {
        return $this->hasMany(CommissionRecord::class, 'rider_id')->latest('id');
    }

    public function activeShipments()
    {
        return $this->hasMany(Shipment::class, 'current_rider_id');
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

    public function getCoveredBarangayNamesAttribute(): array
    {
        $coverages = $this->relationLoaded('barangayCoverages')
            ? $this->barangayCoverages
            : $this->barangayCoverages()->get();

        return $coverages->pluck('barangay_name')->filter()->values()->all();
    }

    public function getFormattedBarangayCoverageAttribute(): string
    {
        $names = $this->covered_barangay_names;
        return ! empty($names) ? implode(', ', $names) : 'All Barangays in Hub Municipality';
    }

    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }
}