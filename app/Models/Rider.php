<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class Rider extends Authenticatable
{
    use Notifiable;

    protected $table = 'riders';

    protected $fillable = [
        'logistics_profile_id',
        'first_name',
        'last_name',
        'birthday',
        'email',
        'email_verified_at',
        'contact_no',
        'license_no',
        'license_expiry_date',
        'vehicle_type',
        'plate_number',
        'license_path',
        'or_cr_path',
        'password',
        'status',
        'approved_at',
        'rejected_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'birthday' => 'date',
            'license_expiry_date' => 'date',
            'email_verified_at' => 'datetime',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function logisticsProfile()
    {
        return $this->belongsTo(LogisticsProfile::class, 'logistics_profile_id');
    }

    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }
}