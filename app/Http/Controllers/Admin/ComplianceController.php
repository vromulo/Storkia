<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductApproval;
use App\Notifications\Seller\ProductComplianceNotification;
use App\Services\AuditLoggerService;
use App\Services\InventoryStockService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ComplianceController extends Controller
{
    public function __construct(
        protected InventoryStockService $inventoryStockService
    ) {}

    public function index(Request $request)
    {
        $status = (string) $request->query('status', $request->query('activeTab', 'pending'));
        $activeTab = strtolower($status);
        if (! in_array($activeTab, ['pending', 'approved', 'violations', 'warnings'], true)) {
            $activeTab = 'pending';
        }
        $search = trim((string) $request->query('search', ''));

        $pendingCount = ProductApproval::whereHas('product')->where(function ($q) {
            $q->where('status', 'Pending')
              ->orWhere(function ($subQ) {
                  $subQ->where('status', 'Approved')
                       ->whereHas('product', fn ($p) => $p->where('has_pending_variants', true));
              });
        })->count();

        $approvedCount = ProductApproval::whereHas('product')->where('status', 'Approved')->count();
        $violationsCount = ProductApproval::whereHas('product')->where('status', 'Disapproved')->where('disapproval_type', 'Violation')->count();
        $warningsCount = ProductApproval::whereHas('product')->where('status', 'Disapproved')->where('disapproval_type', 'Warning')->count();

        $counts = [
            'all'              => Product::count(),
            'Pending'          => Product::whereHas('approval', fn ($q) => $q->where('status', 'Pending'))->count(),
            'Approved'         => $approvedCount,
            'Disapproved'      => $violationsCount + $warningsCount,
            'pending_variants' => Schema::hasColumn('products', 'has_pending_variants')
                ? Product::where('has_pending_variants', true)->count()
                : 0,
            'pending'          => $pendingCount,
            'approved'         => $approvedCount,
            'violations'       => $violationsCount,
            'warnings'         => $warningsCount,
        ];

        $approvalsQuery = ProductApproval::with(['product.specifications', 'product.user.sellerProfile', 'seller.sellerProfile', 'reviewer'])
            ->whereHas('product');

        match ($activeTab) {
            'approved'   => $approvalsQuery->where('status', 'Approved'),
            'violations' => $approvalsQuery->where('status', 'Disapproved')->where('disapproval_type', 'Violation'),
            'warnings'   => $approvalsQuery->where('status', 'Disapproved')->where('disapproval_type', 'Warning'),
            default      => $approvalsQuery->where(function ($q) {
                $q->where('status', 'Pending')
                  ->orWhere(function ($subQ) {
                      $subQ->where('status', 'Approved')
                           ->whereHas('product', fn ($p) => $p->where('has_pending_variants', true));
                  });
            }),
        };

        if ($search !== '') {
            $approvalsQuery->where(function ($q) use ($search) {
                $q->whereHas('product', fn ($p) => $p->where('name', 'like', "%{$search}%")
                    ->orWhere('category', 'like', "%{$search}%")
                    ->orWhere('subcategory', 'like', "%{$search}%"))
                  ->orWhereHas('seller.sellerProfile', fn ($s) => $s->where('business_name', 'like', "%{$search}%"));
            });
        }

        $approvals = $approvalsQuery->latest('updated_at')->paginate(20)->withQueryString();

        return view('livewire.admin.seller-compliance', compact(
            'approvals',
            'status',
            'activeTab',
            'search',
            'counts'
        ));
    }

    protected function recalculateProductMetrics(Product $product, array $variants): void
    {
        $priceDependency = $variants['price_dependency'] ?? 'main';
        $totalStock = 0;
        $minPrice = null;
        $baseWeight = null;
        $hasPending = false;

        foreach ($variants['items'] ?? [] as $item) {
            if (! empty($item['is_archived'])) {
                continue;
            }

            $itemStatus = $item['status'] ?? 'Approved';
            if ($itemStatus === 'Pending') {
                $hasPending = true;
            }
            if ($itemStatus !== 'Approved') {
                continue;
            }

            if ($priceDependency === 'main') {
                $totalStock += (int) ($item['stock'] ?? 0);
                if ($minPrice === null || (float) ($item['price'] ?? 0) < $minPrice) {
                    $minPrice = (float) ($item['price'] ?? 0);
                }
                if ($baseWeight === null) {
                    $baseWeight = $item['weight'] ?? '0';
                }
            }

            foreach ($item['subs'] ?? [] as $sub) {
                if (! empty($sub['is_archived'])) {
                    continue;
                }

                $subStatus = $sub['status'] ?? 'Approved';
                if ($subStatus === 'Pending') {
                    $hasPending = true;
                }
                if ($subStatus !== 'Approved') {
                    continue;
                }

                if ($priceDependency === 'sub') {
                    $totalStock += (int) ($sub['stock'] ?? 0);
                    if ($minPrice === null || (float) ($sub['price'] ?? 0) < $minPrice) {
                        $minPrice = (float) ($sub['price'] ?? 0);
                    }
                    if ($baseWeight === null) {
                        $baseWeight = $sub['weight'] ?? '0';
                    }
                }
            }
        }

        $updates = [
            'variants'       => $variants,
            'stock_quantity' => $totalStock,
        ];

        if ($minPrice !== null) {
            $updates['price'] = $minPrice;
        }
        if ($baseWeight !== null) {
            $updates['weight'] = $baseWeight;
        }
        if (Schema::hasColumn('products', 'has_pending_variants')) {
            $updates['has_pending_variants'] = $hasPending;
        }

        $product->update($updates);
    }

    public function approve(Request $request, Product $product)
    {
        $adminId = Auth::guard('admin')->id();

        DB::transaction(function () use ($product, $adminId) {
            $approval = ProductApproval::updateOrCreate(
                ['product_id' => $product->id],
                [
                    'user_id'          => $product->user_id,
                    'reviewed_by'      => $adminId,
                    'status'           => 'Approved',
                    'disapproval_type' => null,
                    'remarks'          => null,
                ]
            );

            $rawVariants = $product->raw_variants ?? $product->variants;
            if (is_array($rawVariants) && ! empty($rawVariants['items'])) {
                foreach ($rawVariants['items'] as &$vItem) {
                    if (empty($vItem['is_archived']) && ($vItem['status'] ?? 'Pending') === 'Pending') {
                        $vItem['status'] = 'Approved';
                    }
                    if (! empty($vItem['subs']) && is_array($vItem['subs'])) {
                        foreach ($vItem['subs'] as &$sItem) {
                            if (empty($sItem['is_archived']) && ($sItem['status'] ?? 'Pending') === 'Pending') {
                                $sItem['status'] = 'Approved';
                            }
                        }
                    }
                }
                unset($vItem, $sItem);

                $this->recalculateProductMetrics($product, $rawVariants);
            }

            try {
                $product->user?->notify(new ProductComplianceNotification($approval, 'approved'));
            } catch (\Throwable $e) {
            }
        });

        AuditLoggerService::log(
            action: 'product_approved',
            targetType: 'Product',
            targetId: (int) $product->id,
            targetReference: $product->name
        );

        return back()->with('success', "Product \"{$product->name}\" approved and published to the storefront!");
    }

    public function disapprove(Request $request, Product $product)
    {
        $validated = $request->validate([
            'disapproval_type' => ['nullable', 'string', 'max:40'],
            'remarks'          => ['required', 'string', 'min:3', 'max:500'],
        ]);

        $type = $validated['disapproval_type'] ?? 'Warning';
        $remarks = trim($validated['remarks']);

        $approval = ProductApproval::updateOrCreate(
            ['product_id' => $product->id],
            [
                'user_id'          => $product->user_id,
                'reviewed_by'      => Auth::guard('admin')->id(),
                'status'           => 'Disapproved',
                'disapproval_type' => $type,
                'remarks'          => $remarks,
            ]
        );

        try {
            $product->user?->notify(new ProductComplianceNotification($approval, 'disapproved', $type, $remarks));
        } catch (\Throwable $e) {
        }

        AuditLoggerService::log(
            action: 'product_disapproved',
            targetType: 'Product',
            targetId: (int) $product->id,
            targetReference: $product->name,
            reason: $remarks
        );

        return back()->with('success', "Product \"{$product->name}\" marked as Disapproved.");
    }

    public function reviewVariants(Request $request, Product $product)
    {
        $rawVariants = $product->raw_variants ?? $product->variants;
        if (! is_array($rawVariants) || empty($rawVariants['items'])) {
            return back();
        }

        $variantDecisions = (array) $request->input('variant_decisions', []);
        $subDecisions = (array) $request->input('sub_variant_decisions', []);
        $decisionAll = $request->input('decision');

        foreach ($rawVariants['items'] as $vIdx => &$vItem) {
            if (isset($variantDecisions[$vIdx]) && in_array($variantDecisions[$vIdx], ['Approved', 'Disapproved'], true)) {
                $vItem['status'] = $variantDecisions[$vIdx];
            } elseif ($decisionAll && in_array($decisionAll, ['Approved', 'Disapproved'], true) && ($vItem['status'] ?? '') === 'Pending') {
                $vItem['status'] = $decisionAll;
            }

            if (! empty($vItem['subs']) && is_array($vItem['subs'])) {
                foreach ($vItem['subs'] as $sIdx => &$sItem) {
                    if (isset($subDecisions[$vIdx][$sIdx]) && in_array($subDecisions[$vIdx][$sIdx], ['Approved', 'Disapproved'], true)) {
                        $sItem['status'] = $subDecisions[$vIdx][$sIdx];
                    } elseif ($decisionAll && in_array($decisionAll, ['Approved', 'Disapproved'], true) && ($sItem['status'] ?? '') === 'Pending') {
                        $sItem['status'] = $decisionAll;
                    }
                }
            }
        }
        unset($vItem, $sItem);

        $this->recalculateProductMetrics($product, $rawVariants);

        return back()->with('success', "Variant additions for \"{$product->name}\" reviewed and updated.");
    }
}