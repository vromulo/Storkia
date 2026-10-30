<?php

namespace App\Models;

use App\Models\BuyerProfile;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

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
    'role',
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'birthday' => 'date',
            'password_changed_at' => 'datetime',
        ];
    }

    public function setEmailAttribute(string $value): void
    {
        $this->attributes['email'] = strtolower(trim($value));
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

    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    public function buyerProfile()
    {
        return $this->hasOne(BuyerProfile::class);
    }

    public function getUsernameAttribute(): ?string
    {
        return $this->buyerProfile?->username;
    }

    public function getAvatarUrlAttribute(): ?string
    {
        return $this->buyerProfile?->avatar_url;
    }

    public function identityVerification()
    {
        return $this->hasOne(IdentityVerification::class)->latestOfMany();
    }

    public function identityVerifications()
    {
        return $this->hasMany(IdentityVerification::class)->latest('id');
    }

    public function addresses()
    {
        return $this->hasMany(UserAddress::class)->latest('id');
    }

    public function defaultAddress()
    {
        return $this->hasOne(UserAddress::class)->where('is_default', true);
    }

    public function carts()
    {
        return $this->hasMany(Cart::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class, 'buyer_id')->latest('id');
    }

    public function buyerSellerOrders()
    {
        return $this->hasMany(SellerOrder::class, 'buyer_id')->latest('id');
    }

    public function sellerOrders()
    {
        return $this->hasMany(SellerOrder::class, 'seller_id')->latest('id');
    }

    public function sellerProfile()
    {
        return $this->hasOne(SellerProfile::class);
    }

    public function sellerApplications()
    {
        return $this->hasMany(SellerApplication::class)->latest('version');
    }

    public function latestSellerApplication()
    {
        return $this->hasOne(SellerApplication::class)->latestOfMany('version');
    }

    public function logisticsProfile()
    {
        return $this->hasOne(LogisticsProfile::class, 'user_id');
    }

    public function ownedLogisticsProfile()
    {
        return $this->hasOne(LogisticsProfile::class, 'user_id');
    }

    public function branchLogisticsHub()
    {
        return $this->hasOne(LogisticsHub::class, 'branch_user_id');
    }

    public function getLogisticsProfileAttribute(): ?LogisticsProfile
    {
        if (! $this->relationLoaded('ownedLogisticsProfile')) {
            $this->setRelation('ownedLogisticsProfile', $this->ownedLogisticsProfile()->first());
        }

        $owned = $this->getRelation('ownedLogisticsProfile');
        if ($owned) {
            return $owned;
        }

        if (! $this->relationLoaded('branchLogisticsHub')) {
            $this->setRelation(
                'branchLogisticsHub',
                $this->branchLogisticsHub()->with('parentLogisticsProfile')->first()
            );
        }

        $branchHub = $this->getRelation('branchLogisticsHub');
        if ($branchHub && $branchHub->status === 'active') {
            return $branchHub->parentLogisticsProfile;
        }

        return null;
    }

    public function isMainLogisticsAccount(): bool
    {
        if (! $this->relationLoaded('ownedLogisticsProfile')) {
            $this->setRelation('ownedLogisticsProfile', $this->ownedLogisticsProfile()->first());
        }

        return $this->getRelation('ownedLogisticsProfile') !== null;
    }

    public function isBranchLogisticsAccount(): bool
    {
        if (! $this->relationLoaded('branchLogisticsHub')) {
            $this->setRelation('branchLogisticsHub', $this->branchLogisticsHub()->first());
        }

        return $this->getRelation('branchLogisticsHub') !== null;
    }

    public function activeLogisticsHub(): ?LogisticsHub
    {
        if ($this->isBranchLogisticsAccount()) {
            return $this->getRelation('branchLogisticsHub');
        }

        $profile = $this->logisticsProfile;
        if (! $profile) {
            return null;
        }

        return $profile->ensureMainHubExists();
    }

    public function logisticsApplications()
    {
        return $this->hasMany(LogisticsApplication::class)->latest('version');
    }

    public function latestLogisticsApplication()
    {
        return $this->hasOne(LogisticsApplication::class)->latestOfMany('version');
    }

    public function messages()
    {
        return $this->hasMany(Message::class, 'user_id');
    }
}