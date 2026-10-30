<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\CommissionRecord;
use App\Models\Product;
use App\Models\SellerApplication;
use App\Models\SellerOrder;
use App\Models\SellerProfile;
use App\Services\OrderCompletionService;
use App\Services\RoleWalletService;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function __construct(
        protected OrderCompletionService $orderCompletionService,
        protected RoleWalletService $roleWalletService
    ) {}

    public function index()
    {
        $userId = Auth::id();
        $profile = SellerProfile::where('user_id', $userId)->first();
        $latestApp = SellerApplication::where('user_id', $userId)->latest('version')->first();

        $totalSales = 0.0;
        $netEarnings = 0.0;
        $walletAvailable = 0.0;
        $walletPendingEscrow = 0.0;
        $activeProductsCount = 0;
        $pendingOrdersCount = 0;
        $totalOrdersCount = 0;
        $recentOrders = collect();

        if ($profile) {
            $this->orderCompletionService->runLifecycleMaintenance(sellerId: (int) $userId);

            // Backfill E-Wallet for any completed orders that were confirmed prior to the wallet service
            $completedOrders = SellerOrder::where('seller_id', $userId)
                ->where('status', 'completed')
                ->get();

            foreach ($completedOrders as $compOrder) {
                $this->roleWalletService->releaseCompletedOrderEarnings($compOrder);
            }

            $sellerWallet = $this->roleWalletService->getOrCreateWallet(
                ownerType: 'Seller',
                ownerId: (int) $profile->id,
                ownerName: $profile->business_name
            );

            $walletAvailable = (float) $sellerWallet->available_balance;
            $walletPendingEscrow = (float) $sellerWallet->pending_balance;

            $totalSales = (float) SellerOrder::where('seller_id', $userId)
                ->whereIn('status', ['delivered', 'completed'])
                ->sum('subtotal_amount');

            $netEarnings = (float) CommissionRecord::where('seller_profile_id', $profile->id)
                ->sum('seller_net_earning');

            if ($netEarnings <= 0 && $walletAvailable > 0) {
                $netEarnings = $walletAvailable;
            }

            $activeProductsCount = Product::where('user_id', $userId)
                ->whereHas('approval', fn ($q) => $q->where('status', 'Approved'))
                ->count();

            $pendingOrdersCount = SellerOrder::where('seller_id', $userId)
                ->whereIn('status', ['pending_seller_acceptance', 'pending_buyer_response', 'accepted', 'preparing'])
                ->count();

            $totalOrdersCount = SellerOrder::where('seller_id', $userId)->count();

            // Paginate (10 per page) in strict FIFO order so Dashboard and Order Management counts always match!
            $recentOrders = SellerOrder::with(['buyer', 'items', 'shipment', 'payment'])
                ->where('seller_id', $userId)
                ->orderBy('created_at', 'asc')
                ->orderBy('id', 'asc')
                ->paginate(10);
        }

        return view('seller.seller-dashboard', compact(
            'profile',
            'latestApp',
            'totalSales',
            'netEarnings',
            'walletAvailable',
            'walletPendingEscrow',
            'activeProductsCount',
            'pendingOrdersCount',
            'totalOrdersCount',
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