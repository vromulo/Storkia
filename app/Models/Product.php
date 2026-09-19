<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use SoftDeletes;

    protected $fillable = [
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
}