<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

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
}