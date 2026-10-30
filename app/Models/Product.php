<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;

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
        'has_pending_variants',
        'weight',
        'stock_quantity',
        'publish_status',
        'per_buyer_limit',
        'low_stock_alerted',
    ];

    protected $casts = [
        'pictures'             => 'array',
        'variants'             => 'array',
        'has_pending_variants' => 'boolean',
        'per_buyer_limit'      => 'integer',
        'low_stock_alerted'    => 'boolean',
    ];

    public function getRawVariantsAttribute(): ?array
    {
        $raw = $this->attributes['variants'] ?? null;
        if (is_null($raw)) {
            return null;
        }
        return is_string($raw) ? json_decode($raw, true) : (array) $raw;
    }

    public function getVariantsAttribute($value): ?array
    {
        $variants = is_string($value) ? json_decode($value, true) : $value;

        if (!is_array($variants) || !isset($variants['items']) || !is_array($variants['items'])) {
            return $variants;
        }

        if ($this->shouldShowAllVariants()) {
            return $variants;
        }

        $isProductApproved = $this->relationLoaded('approval')
            ? ($this->approval?->status === 'Approved')
            : $this->approval()->where('status', 'Approved')->exists();

        if (!$isProductApproved) {
            $variants['items'] = [];
            return $variants;
        }

        $priceDependency = $variants['price_dependency'] ?? 'main';
        $filteredItems = [];

        foreach ($variants['items'] as $item) {
            if (!empty($item['is_archived']) || ($item['status'] ?? 'Approved') !== 'Approved') {
                continue;
            }

            if (isset($item['subs']) && is_array($item['subs'])) {
                $filteredSubs = [];
                foreach ($item['subs'] as $sub) {
                    if (!empty($sub['is_archived']) || ($sub['status'] ?? 'Approved') !== 'Approved') {
                        continue;
                    }
                    $filteredSubs[] = $sub;
                }
                $item['subs'] = $filteredSubs;

                if ($priceDependency === 'sub' && count($item['subs']) === 0) {
                    continue;
                }
            }

            $filteredItems[] = $item;
        }

        $variants['items'] = $filteredItems;
        return $variants;
    }

    protected function shouldShowAllVariants(): bool
    {
        if (app()->runningInConsole()) {
            return true;
        }

        $request = request();

        if ($request->routeIs('seller.*', 'admin.*') || $request->is('seller/*', 'admin/*')) {
            return true;
        }

        $referer = $request->header('referer', '');
        if (str_contains($referer, '/seller') || str_contains($referer, '/admin')) {
            return true;
        }

        return false;
    }

    public function hasPendingVariantItems(): bool
    {
        $variants = $this->raw_variants;
        if (!is_array($variants) || empty($variants['items']) || !is_array($variants['items'])) {
            return false;
        }

        foreach ($variants['items'] as $item) {
            if (!empty($item['is_archived'])) {
                continue;
            }
            if (($item['status'] ?? '') === 'Pending') {
                return true;
            }
            if (!empty($item['subs']) && is_array($item['subs'])) {
                foreach ($item['subs'] as $sub) {
                    if (!empty($sub['is_archived'])) {
                        continue;
                    }
                    if (($sub['status'] ?? '') === 'Pending') {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    public function specifications(): HasMany
    {
        return $this->hasMany(ProductSpecification::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function approval(): HasOne
    {
        return $this->hasOne(ProductApproval::class);
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function sellerProfile(): HasOneThrough
    {
        return $this->hasOneThrough(
            SellerProfile::class,
            User::class,
            'id',
            'user_id',
            'user_id',
            'id'
        );
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }
}