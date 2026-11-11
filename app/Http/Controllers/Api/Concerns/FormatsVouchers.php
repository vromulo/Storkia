<?php

namespace App\Http\Controllers\Api\Concerns;

use App\Models\BuyerVoucherClaim;
use App\Models\StorkVoucher;

trait FormatsVouchers
{
    protected function formatVoucher(StorkVoucher $voucher, ?BuyerVoucherClaim $claim = null, ?bool $isClaimed = null): array
    {
        $timesUsed = $claim ? (int) $claim->times_used : 0;
        $effectiveStatus = $voucher->effective_status;
        $claimed = $isClaimed ?? ($claim !== null);

        return [
            'id'                 => (int) $voucher->id,
            'code'               => $voucher->code,
            'title'              => $voucher->title,
            'description'        => $voucher->description,
            'scope'              => $voucher->scope,
            'discount_type'      => $voucher->discount_type,
            'discount_value'     => (float) $voucher->discount_value,
            'max_discount_cap'   => $voucher->max_discount_cap !== null ? (float) $voucher->max_discount_cap : null,
            'min_spend'          => (float) $voucher->min_spend,
            'badge_label'        => $voucher->badge_label,
            'visibility'         => $voucher->visibility,
            'status'             => $voucher->status,
            'effective_status'   => $effectiveStatus,
            'starts_at'          => $voucher->starts_at?->toIso8601String(),
            'ends_at'            => $voucher->ends_at?->toIso8601String(),
            'ends_at_label'      => $voucher->ends_at?->format('M d, Y'),
            'expiration_summary' => $voucher->expiration_summary,
            'used_count'         => (int) $voucher->used_count,
            'total_usage_limit'  => (int) $voucher->total_usage_limit,
            'per_buyer_limit'    => (int) $voucher->per_buyer_limit,
            'times_used'         => $timesUsed,
            'is_claimed'         => $claimed,
            'is_usable'          => $claimed
                && $effectiveStatus === 'ongoing'
                && $timesUsed < (int) $voucher->per_buyer_limit,
            'seller_profile_id'  => $voucher->seller_profile_id ? (int) $voucher->seller_profile_id : null,
            'seller_name'        => $voucher->relationLoaded('sellerProfile')
                ? $voucher->sellerProfile?->business_name
                : null,
        ];
    }
}