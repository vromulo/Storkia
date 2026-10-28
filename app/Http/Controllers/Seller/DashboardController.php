<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\CommissionRecord;
use App\Models\Product;
use App\Models\SellerApplication;
use App\Models\SellerOrder;
use App\Models\SellerProfile;
use App\Services\OrderCompletionService;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function __construct(
        protected OrderCompletionService $orderCompletionService
    ) {}

    public function index()
    {
        $userId = Auth::id();
        $profile = SellerProfile::where('user_id', $userId)->first();
        $latestApp = SellerApplication::where('user_id', $userId)->latest('version')->first();

        $totalSales = 0.0;
        $netEarnings = 0.0;
        $activeProductsCount = 0;
        $pendingOrdersCount = 0;
        $recentOrders = collect();

        if ($profile) {
            $this->orderCompletionService->autoCompleteExpiredDeliveredOrders(sellerId: (int) $userId);

            $totalSales = (float) SellerOrder::where('seller_id', $userId)
                ->whereIn('status', ['delivered', 'completed'])
                ->sum('subtotal_amount');

            $netEarnings = (float) CommissionRecord::where('seller_profile_id', $profile->id)
                ->sum('seller_net_earning');

            $activeProductsCount = Product::where('user_id', $userId)
                ->whereHas('approval', fn ($q) => $q->where('status', 'Approved'))
                ->count();

            $pendingOrdersCount = SellerOrder::where('seller_id', $userId)
                ->whereIn('status', ['pending_seller_acceptance', 'accepted', 'preparing'])
                ->count();

            $recentOrders = SellerOrder::with(['buyer', 'items', 'shipment'])
                ->where('seller_id', $userId)
                ->orderBy('created_at', 'asc')
                ->orderBy('id', 'asc')
                ->take(5)
                ->get();
        }

        return view('seller.seller-dashboard', compact(
            'profile',
            'latestApp',
            'totalSales',
            'netEarnings',
            'activeProductsCount',
            'pendingOrdersCount',
            'recentOrders'
        ));
    }

    public function showReapply()
    {
        $latestApp = SellerApplication::where('user_id', Auth::id())->latest('version')->first();

        if (! $latestApp || $latestApp->status !== 'rejected') {
            return redirect()->route('seller.seller-dashboard');
        }

        return view('seller.auth.reapply');
    }
}