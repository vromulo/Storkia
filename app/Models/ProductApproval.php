<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductApproval extends Model
{
    protected $fillable = [
        'product_id',
        'user_id',
        'reviewed_by',
        'status',
        'disapproval_type',
        'remarks'
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function seller()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function reviewer()
    {
        return $this->belongsTo(Admin::class, 'reviewed_by');
    }
}