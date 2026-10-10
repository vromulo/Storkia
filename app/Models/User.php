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
    'password_changed_at',
    'role'
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
            'password_changed_at' => 'datetime',
        ];
    }

    public function formattedPasswordLastUpdated(): string
    {
        if (! $this->password_changed_at) {
            return 'Never updated';
        }

        $now = now();
        $diffSeconds = $this->password_changed_at->diffInSeconds($now);

        if ($diffSeconds < 60) {
            return 'Last updated a few seconds ago';
        }

        if ($this->password_changed_at->isToday()) {
            return 'Last updated ' . $this->password_changed_at->diffForHumans();
        }

        if ($this->password_changed_at->isYesterday()) {
            return 'Last updated yesterday';
        }

        return 'Last updated ' . $this->password_changed_at->format('m/d/Y');
    }
    
    // Buyer
    public function identityVerification()
    {
        return $this->hasOne(IdentityVerification::class)->latestOfMany();
    }

    public function addresses()
    {
        return $this->hasMany(UserAddress::class)->latest('id');
    }

    public function defaultAddress()
    {
        return $this->hasOne(UserAddress::class)->where('is_default', true);
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