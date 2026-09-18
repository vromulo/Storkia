<?php

namespace App\Models;

// EXISTING
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable([
    'first_name', 
    'last_name', 
    'middle_initial', 
    'sex', 
    'contact_no', 
    'birthday', 
    'email', 
    'password',
    'role' // NEW: Added role to fillable attributes
])]
#[Hidden(['password', 'remember_token'])] // EXISTING
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array // EXISTING
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'birthday' => 'date',
        ];
    }

    /**
     * The seller's current approved profile. Only exists once an
     * application has been approved by an admin.
     */
    public function sellerProfile()
    {
        return $this->hasOne(SellerProfile::class);
    }

    /**
     * Full application history (pending/approved/rejected), newest first.
     */
    public function sellerApplications()
    {
        return $this->hasMany(SellerApplication::class)->latest('version');
    }

    /**
     * The most recent application submitted, regardless of status.
     * This is the source of truth for "what state is this seller's
     * application in right now" — never seller_profiles.
     */
    public function latestSellerApplication()
    {
        return $this->hasOne(SellerApplication::class)->latestOfMany('version');
    }




    

    public function logisticsProfile()
    {
        return $this->hasOne(LogisticsProfile::class);
    }

    public function logisticsApplications()
    {
        return $this->hasMany(LogisticsApplication::class)->latest('version');
    }

    public function latestLogisticsApplication()
    {
        return $this->hasOne(LogisticsApplication::class)->latestOfMany('version');
    }
}