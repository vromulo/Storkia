<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserAddress extends Model
{
    protected $fillable = [
        'user_id',
        'country',
        'first_name',
        'last_name',
        'phone_number',
        'province_code',
        'province',
        'municipality_code',
        'municipality',
        'barangay_code',
        'barangay',
        'postcode',
        'street_address',
        'building_details',
        'is_default',
    ];

    protected $casts = [
        'is_default' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}