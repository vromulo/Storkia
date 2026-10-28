<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CodRemittance extends Model
{
    protected $fillable = [
        'remittance_reference',
        'logistics_profile_id',
        'rider_id',
        'total_collected_amount',
        'total_rider_commission',
        'net_remitted_amount',
        'status',
        'submitted_at',
        'verified_at',
        'verified_by',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'total_collected_amount' => 'decimal:2',
            'total_rider_commission' => 'decimal:2',
            'net_remitted_amount'    => 'decimal:2',
            'submitted_at'           => 'datetime',
            'verified_at'            => 'datetime',
        ];
    }

    public function logisticsProfile(): BelongsTo
    {
        return $this->belongsTo(LogisticsProfile::class, 'logistics_profile_id');
    }

    public function rider(): BelongsTo
    {
        return $this->belongsTo(Rider::class, 'rider_id');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(CodRemittanceItem::class, 'cod_remittance_id');
    }
}