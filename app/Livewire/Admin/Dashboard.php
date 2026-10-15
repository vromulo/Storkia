<?php

namespace App\Livewire\Admin;

use App\Models\IdentityVerification;
use App\Models\LogisticsApplication;
use App\Models\Product;
use App\Models\ProductApproval;
use App\Models\SellerApplication;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.admin')]
class Dashboard extends Component
{
    // Filter options: 'all', 'today', '7days', '30days', 'year'
    public string $period = 'all';

    public function setPeriod(string $period): void
    {
        $this->period = in_array($period, ['all', 'today', '7days', '30days', 'year']) ? $period : 'all';
    }

    protected function getDateRange(): ?array
    {
        $now = Carbon::now();

        return match ($this->period) {
            'today'  => [$now->copy()->startOfDay(), $now->copy()->endOfDay()],
            '7days'  => [$now->copy()->subDays(6)->startOfDay(), $now->copy()->endOfDay()],
            '30days' => [$now->copy()->subDays(29)->startOfDay(), $now->copy()->endOfDay()],
            'year'   => [$now->copy()->startOfYear(), $now->copy()->endOfDay()],
            default  => null,
        };
    }

    public function render()
    {
        $dateRange = $this->getDateRange();

        // 1. Core Platform Volume (Users & Roles)
        $usersQuery = User::query();
        if ($dateRange) {
            $usersQuery->whereBetween('created_at', $dateRange);
        }
        $totalUsers = $usersQuery->count();

        // Role breakdowns (guarded against missing role column)
        $roleBreakdown = [
            'buyers'    => 0,
            'sellers'   => 0,
            'logistics' => 0,
        ];
        if (Schema::hasColumn('users', 'role')) {
            $roleBreakdown['buyers'] = (clone $usersQuery)->where('role', 'Buyer')->count();
            $roleBreakdown['sellers'] = (clone $usersQuery)->where('role', 'Seller')->count();
            $roleBreakdown['logistics'] = (clone $usersQuery)->where('role', 'Logistics')->count();
        }

        // 2. Action Required / Pending Queues (Real-time operational alerts)
        $pendingSellerApps = class_exists(SellerApplication::class) 
            ? SellerApplication::where('status', 'pending')->count() 
            : 0;

        $pendingLogisticsApps = class_exists(LogisticsApplication::class) 
            ? LogisticsApplication::where('status', 'pending')->count() 
            : 0;

        $pendingIdentityVerifs = class_exists(IdentityVerification::class) 
            ? IdentityVerification::where('status', 'pending')->count() 
            : 0;

        $pendingComplianceReviews = class_exists(ProductApproval::class) 
            ? ProductApproval::where('status', 'Pending')->count() 
            : 0;

        // 3. Product Catalog Metrics
        $totalProducts = class_exists(Product::class) ? Product::count() : 0;
        $productsInPeriod = 0;
        if (class_exists(Product::class)) {
            $pQuery = Product::query();
            if ($dateRange) {
                $pQuery->whereBetween('created_at', $dateRange);
            }
            $productsInPeriod = $pQuery->count();
        }

        // 4. Orders / GMV Safe Fallback
        $orderMetrics = [
            'total_orders' => 0,
            'gross_volume' => 0.00,
            'has_orders'   => false,
        ];

        // Checks for Order model dynamically if present in your migrations
        if (class_exists('App\Models\Order') && Schema::hasTable('orders')) {
            $orderModel = 'App\Models\Order';
            $orderQuery = $orderModel::query();
            if ($dateRange) {
                $orderQuery->whereBetween('created_at', $dateRange);
            }
            $orderMetrics['has_orders'] = true;
            $orderMetrics['total_orders'] = (clone $orderQuery)->count();
            $orderMetrics['gross_volume'] = (float) ((clone $orderQuery)->where('status', '!=', 'cancelled')->sum('total_amount') ?? 0);
        }

        // 5. Recent Platform Activity Log (Latest pending submissions needing admin attention)
        $recentSellerApps = class_exists(SellerApplication::class)
            ? SellerApplication::with('user')->latest('id')->take(4)->get()
            : collect();

        $recentCompliance = class_exists(ProductApproval::class)
            ? ProductApproval::with(['product', 'seller'])->latest('id')->take(4)->get()
            : collect();

        $recentIdentity = class_exists(IdentityVerification::class)
            ? IdentityVerification::with('user')->latest('id')->take(4)->get()
            : collect();

        return view('livewire.admin.dashboard', [
            'totalUsers'               => $totalUsers,
            'roleBreakdown'            => $roleBreakdown,
            'pendingSellerApps'        => $pendingSellerApps,
            'pendingLogisticsApps'     => $pendingLogisticsApps,
            'pendingIdentityVerifs'    => $pendingIdentityVerifs,
            'pendingComplianceReviews' => $pendingComplianceReviews,
            'totalProducts'            => $totalProducts,
            'productsInPeriod'         => $productsInPeriod,
            'orderMetrics'             => $orderMetrics,
            'recentSellerApps'         => $recentSellerApps,
            'recentCompliance'         => $recentCompliance,
            'recentIdentity'           => $recentIdentity,
        ]);
    }
}