<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Voucher;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class VoucherController extends Controller
{
    public function activeVouchers(Request $request): JsonResponse
    {
        $vouchers = Voucher::where('is_active', true)
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'data' => $vouchers
        ]);
    }
}