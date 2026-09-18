<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LogisticsApplication extends Model
{
    protected $fillable = [
        'user_id',
        'version',
        'contact_no',
        'province',
        'municipality',
        'barangay',
        'street',
        'house_details',
        'business_name',
        'id_path',
        'permit_path',
        'status',
        'rejection_reason',
        'reviewed_by',
        'reviewed_at',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
        'version' => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer()
    {
        return $this->belongsTo(Admin::class, 'reviewed_by');
    }
}