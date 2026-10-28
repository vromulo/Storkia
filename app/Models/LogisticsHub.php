<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LogisticsHub extends Model
{
    protected $table = 'logistics_hubs';

    protected $fillable = [
        'parent_logistics_profile_id',
        'branch_user_id',
        'hub_code',
        'hub_name',
        'is_main_hub',
        'email_prefix',
        'contact_no',
        'province_code',
        'province',
        'municipality_code',
        'municipality',
        'barangay_code',
        'barangay',
        'street',
        'house_details',
        'status',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'is_main_hub' => 'boolean',
        ];
    }

    public function parentLogisticsProfile(): BelongsTo
    {
        return $this->belongsTo(LogisticsProfile::class, 'parent_logistics_profile_id');
    }

    public function branchUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'branch_user_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function employeeAssignments(): HasMany
    {
        return $this->hasMany(LogisticsEmployeeHubAssignment::class, 'logistics_hub_id');
    }

    public function activeCourierAssignments(): HasMany
    {
        return $this->hasMany(LogisticsEmployeeHubAssignment::class, 'logistics_hub_id')
            ->where('employee_type', 'courier')
            ->where('is_current', true);
    }

    public function activeRiderAssignments(): HasMany
    {
        return $this->hasMany(LogisticsEmployeeHubAssignment::class, 'logistics_hub_id')
            ->where('employee_type', 'rider')
            ->where('is_current', true);
    }

    public function riderBarangayCoverages(): HasMany
    {
        return $this->hasMany(RiderBarangayCoverage::class, 'logistics_hub_id')
            ->where('is_active', true);
    }

    public function shipmentPackages(): HasMany
    {
        return $this->hasMany(ShipmentPackage::class, 'logistics_hub_id');
    }

    public function getFullAddressAttribute(): string
    {
        return collect([
            $this->house_details,
            $this->street,
            $this->barangay,
            $this->municipality,
            $this->province,
        ])->filter()->implode(', ');
    }

    public function getDisplayTypeAttribute(): string
    {
        return $this->is_main_hub ? 'Main Hub' : 'Branch Hub';
    }
}