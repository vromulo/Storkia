<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RiderBarangayCoverage extends Model
{
    protected $table = 'rider_barangay_coverages';

    protected $fillable = [
        'logistics_profile_id',
        'logistics_hub_id',
        'rider_id',
        'municipality_code',
        'municipality',
        'barangay_code',
        'barangay_name',
        'is_active',
        'assigned_by',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function logisticsProfile(): BelongsTo
    {
        return $this->belongsTo(LogisticsProfile::class, 'logistics_profile_id');
    }

    public function hub(): BelongsTo
    {
        return $this->belongsTo(LogisticsHub::class, 'logistics_hub_id');
    }

    public function rider(): BelongsTo
    {
        return $this->belongsTo(Rider::class, 'rider_id');
    }

    public function assigner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }
}