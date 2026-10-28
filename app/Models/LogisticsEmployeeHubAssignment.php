<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LogisticsEmployeeHubAssignment extends Model
{
    protected $table = 'logistics_employee_hub_assignments';

    protected $fillable = [
        'logistics_profile_id',
        'logistics_hub_id',
        'employee_type',
        'courier_id',
        'rider_id',
        'municipality',
        'is_current',
        'assigned_by',
        'assigned_at',
        'unassigned_at',
        'remarks',
    ];

    protected function casts(): array
    {
        return [
            'is_current'    => 'boolean',
            'assigned_at'   => 'datetime',
            'unassigned_at' => 'datetime',
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

    public function courier(): BelongsTo
    {
        return $this->belongsTo(Courier::class, 'courier_id');
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