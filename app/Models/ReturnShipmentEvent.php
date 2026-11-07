<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One entry in a return shipment's timeline (like shipment_tracking, but for returns).
 * Create these through ReturnShipment::recordEvent().
 */
class ReturnShipmentEvent extends Model
{
    protected $fillable = [
        'return_shipment_id',
        'return_request_id',
        'status',
        'title',
        'description',
        'location',
        'actor_type',
        'actor_id',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
        ];
    }

    public function returnShipment(): BelongsTo
    {
        return $this->belongsTo(ReturnShipment::class, 'return_shipment_id');
    }

    public function returnRequest(): BelongsTo
    {
        return $this->belongsTo(ReturnRequest::class, 'return_request_id');
    }
}