<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LogisticsProfile extends Model
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
        'id_path',
        'permit_path',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function applications()
    {
        return $this->hasMany(LogisticsApplication::class, 'user_id', 'user_id')->latest('version');
    }
}