<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SellerProfile extends Model
{
    protected $fillable = [
        'user_id',
        'contact_no',
        'province',
        'municipality',
        'barangay',
        'street',
        'house_details',
        'business_name',
        'line_of_business',
        'logo_path',
        'id_path',
        'permit_path',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function applications()
    {
        return $this->hasMany(SellerApplication::class, 'user_id', 'user_id')->latest('version');
    }

    public function otherCategories()
    {
        return $this->hasMany(SellerCategory::class, 'seller_profile_id');
    }
}