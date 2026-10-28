<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SellerGcashAccount extends Model
{
    protected $table = 'seller_gcash_accounts';

    protected $fillable = [
        'seller_profile_id',
        'user_id',
        'account_name',
        'gcash_number',
        'qr_image_path',
        'is_active',
        'confirmed_at',
    ];

    protected function casts(): array
    {
        return [
            'is_active'    => 'boolean',
            'confirmed_at' => 'datetime',
        ];
    }

    public function sellerProfile(): BelongsTo
    {
        return $this->belongsTo(SellerProfile::class, 'seller_profile_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function getQrImageUrlAttribute(): ?string
    {
        return $this->qr_image_path ? asset('storage/' . $this->qr_image_path) : null;
    }

    public function isCompliantForCheckout(): bool
    {
        return $this->is_active
            && trim((string) $this->account_name) !== ''
            && trim((string) $this->gcash_number) !== '';
    }
}