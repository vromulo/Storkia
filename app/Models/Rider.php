<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
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
        'avatar_path',
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
        'current_latitude',
        'current_longitude',
        'location_accuracy',
        'location_updated_at',
        'vehicle_verification_status',
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
            'location_updated_at' => 'datetime',
            'sla_strikes'         => 'integer',
            'current_latitude'    => 'decimal:7',
            'current_longitude'   => 'decimal:7',
            'location_accuracy'   => 'decimal:2',
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
        return $this->hasMany(RiderBarangayCoverage::class, 'rider_id');
    }

    public function activeBarangayCoverages()
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

    public function locations()
    {
        return $this->hasMany(RiderLocation::class, 'rider_id')->latest('recorded_at');
    }

    public function latestLocation()
    {
        return $this->hasOne(RiderLocation::class, 'rider_id')->latestOfMany('recorded_at');
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
        if ($this->relationLoaded('activeBarangayCoverages')) {
            $coverages = $this->activeBarangayCoverages;
        } elseif ($this->relationLoaded('barangayCoverages')) {
            $coverages = $this->barangayCoverages->where('is_active', true);
        } else {
            $coverages = $this->activeBarangayCoverages()->get();
        }

        return $coverages->pluck('barangay_name')->filter()->unique()->values()->all();
    }

    public function getFormattedBarangayCoverageAttribute(): string
    {
        $names = $this->covered_barangay_names;

        return ! empty($names) ? implode(', ', $names) : 'All Hub Barangays';
    }

    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    public function getAvatarUrlAttribute(): ?string
    {
        if (! empty($this->avatar_path) && Storage::disk('public')->exists($this->avatar_path)) {
            return asset('storage/' . ltrim((string) $this->avatar_path, '/'));
        }

        return null;
    }
}