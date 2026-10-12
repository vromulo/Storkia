<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use App\Models\SellerProfile;
use App\Models\User;

class Product extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'name',
        'category', 
        'subcategory',
        'description',
        'additional_descriptions',
        'price',
        'discount',
        'pictures',
        'variants',
        'weight',
        'stock_quantity'
    ];

    protected $casts = [
        'pictures' => 'array',
        'variants' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function approval(): HasOne
    {
        return $this->hasOne(ProductApproval::class);
    }

    public function seller()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function sellerProfile(): \Illuminate\Database\Eloquent\Relations\HasOneThrough
    {
        // Links Product -> User -> SellerProfile
        return $this->hasOneThrough(
            SellerProfile::class,
            User::class,
            'id',        // Foreign key on users table (users.id)
            'user_id',   // Foreign key on seller_profiles table (seller_profiles.user_id)
            'user_id',   // Local key on products table (products.user_id)
            'id'         // Local key on users table
        );
    }
}