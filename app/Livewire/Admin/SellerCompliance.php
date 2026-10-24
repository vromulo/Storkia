<?php

namespace App\Livewire\Admin;

use App\Models\ProductApproval;
use App\Models\Product;
use App\Notifications\Seller\ProductComplianceNotification;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.admin')]
class SellerCompliance extends Component
{
    use WithPagination;

    public string $activeTab = 'pending';
    public string $search = '';

    public ?int $selectedApprovalId = null;
    public bool $detailModalOpen = false;
    public bool $disapproveModalOpen = false;

    public string $disapproval_type = 'Violation';
    public string $remarks = '';

    public string $activePreviewImage = '';

    protected $queryString = [
        'activeTab' => ['except' => 'pending'],
        'search'    => ['except' => ''],
    ];

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function setTab(string $tab): void
    {
        $this->activeTab = $tab;
        $this->resetPage();
    }

    public function viewDetails(int $id): void
    {
        $this->selectedApprovalId = $id;
        $approval = ProductApproval::with('product')->find($id);
        if ($approval && $approval->product && !empty($approval->product->pictures)) {
            $this->activePreviewImage = asset('storage/' . $approval->product->pictures[0]);
        } else {
            $this->activePreviewImage = '';
        }
        $this->detailModalOpen = true;
    }

    public function selectPreviewImage(string $url): void
    {
        $this->activePreviewImage = $url;
    }

    private function recalculateAndSaveProduct(Product $product, array $variants): void
    {
        $priceDependency = $variants['price_dependency'] ?? 'main';
        $totalStock = 0;
        $minPrice = null;
        $baseWeight = null;
        $hasPending = false;

        if (isset($variants['items']) && is_array($variants['items'])) {
            foreach ($variants['items'] as $item) {
                if (!empty($item['is_archived'])) {
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
                    if (is_null($minPrice) || (float) ($item['price'] ?? 0) < $minPrice) {
                        $minPrice = (float) ($item['price'] ?? 0);
                    }
                    if (is_null($baseWeight)) {
                        $baseWeight = $item['weight'] ?? '0';
                    }
                }

                if (!empty($item['subs']) && is_array($item['subs'])) {
                    foreach ($item['subs'] as $sub) {
                        if (!empty($sub['is_archived'])) {
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
                            if (is_null($minPrice) || (float) ($sub['price'] ?? 0) < $minPrice) {
                                $minPrice = (float) ($sub['price'] ?? 0);
                            }
                            if (is_null($baseWeight)) {
                                $baseWeight = $sub['weight'] ?? '0';
                            }
                        }
                    }
                }
            }
        }

        $product->variants = $variants;
        $product->has_pending_variants = $hasPending;
        $product->stock_quantity = $totalStock;
        if (!is_null($minPrice)) {
            $product->price = $minPrice;
        }
        if (!is_null($baseWeight)) {
            $product->weight = $baseWeight;
        }
        $product->save();
    }

    private function syncPendingVariantFlags(): void
    {
        $products = Product::whereHas('approval', fn($q) => $q->where('status', 'Approved'))->get();
        foreach ($products as $product) {
            $actualPending = $product->hasPendingVariantItems();
            if ((bool) $product->has_pending_variants !== $actualPending) {
                $product->has_pending_variants = $actualPending;
                $product->saveQuietly();
            }
        }
    }

    public function approve(int $id): void
    {
        $approval = ProductApproval::with(['product', 'seller'])->findOrFail($id);
        $admin = Auth::guard('admin')->user();

        $approval->update([
            'status'           => 'Approved',
            'disapproval_type' => null,
            'reviewed_by'      => $admin?->id,
            'remarks'          => null,
        ]);

        if ($approval->product) {
            $variants = $approval->product->raw_variants;
            if (is_array($variants) && isset($variants['items'])) {
                foreach ($variants['items'] as &$item) {
                    if (empty($item['is_archived']) && ($item['status'] ?? 'Pending') === 'Pending') {
                        $item['status'] = 'Approved';
                    }
                    if (!empty($item['subs']) && is_array($item['subs'])) {
                        foreach ($item['subs'] as &$sub) {
                            if (empty($sub['is_archived']) && ($sub['status'] ?? 'Pending') === 'Pending') {
                                $sub['status'] = 'Approved';
                            }
                        }
                    }
                }
                $this->recalculateAndSaveProduct($approval->product, $variants);
            }
        }

        if ($approval->seller) {
            $approval->seller->notify(new ProductComplianceNotification($approval, 'approved'));
        }

        $this->detailModalOpen = false;
        session()->flash('success', "Product \"{$approval->product?->name}\" approved and is now active.");
    }

    public function approvePendingVariants(int $productId): void
    {
        $product = Product::findOrFail($productId);
        $variants = $product->raw_variants;
        $modified = false;

        if (isset($variants['items']) && is_array($variants['items'])) {
            foreach ($variants['items'] as &$item) {
                if (!empty($item['is_archived'])) {
                    continue;
                }
                if (($item['status'] ?? '') === 'Pending') {
                    $item['status'] = 'Approved';
                    $modified = true;
                }
                if (isset($item['subs']) && is_array($item['subs'])) {
                    foreach ($item['subs'] as &$sub) {
                        if (!empty($sub['is_archived'])) {
                            continue;
                        }
                        if (($sub['status'] ?? '') === 'Pending') {
                            $sub['status'] = 'Approved';
                            $modified = true;
                        }
                    }
                }
            }
        }

        if ($modified) {
            $this->recalculateAndSaveProduct($product, $variants);
            session()->flash('success', 'All pending variants and subvariants have been approved.');
        }

        $this->detailModalOpen = false;
    }

    public function disapprovePendingVariants(int $productId): void
    {
        $product = Product::findOrFail($productId);
        $variants = $product->raw_variants;
        $modified = false;

        if (isset($variants['items']) && is_array($variants['items'])) {
            foreach ($variants['items'] as &$item) {
                if (!empty($item['is_archived'])) {
                    continue;
                }
                if (($item['status'] ?? '') === 'Pending') {
                    $item['status'] = 'Disapproved';
                    $modified = true;
                }
                if (isset($item['subs']) && is_array($item['subs'])) {
                    foreach ($item['subs'] as &$sub) {
                        if (!empty($sub['is_archived'])) {
                            continue;
                        }
                        if (($sub['status'] ?? '') === 'Pending') {
                            $sub['status'] = 'Disapproved';
                            $modified = true;
                        }
                    }
                }
            }
        }

        if ($modified) {
            $this->recalculateAndSaveProduct($product, $variants);
            session()->flash('success', 'All pending variants and subvariants have been rejected.');
        }

        $this->detailModalOpen = false;
    }

    public function approveSingleVariant(int $productId, int $vIdx): void
    {
        $product = Product::findOrFail($productId);
        $variants = $product->raw_variants;

        if (isset($variants['items'][$vIdx])) {
            $variants['items'][$vIdx]['status'] = 'Approved';
            if (!empty($variants['items'][$vIdx]['subs']) && is_array($variants['items'][$vIdx]['subs'])) {
                foreach ($variants['items'][$vIdx]['subs'] as &$sub) {
                    if (($sub['status'] ?? '') === 'Pending') {
                        $sub['status'] = 'Approved';
                    }
                }
            }
            $this->recalculateAndSaveProduct($product, $variants);
            session()->flash('success', "Variant \"{$variants['items'][$vIdx]['name']}\" approved.");
        }
    }

    public function disapproveSingleVariant(int $productId, int $vIdx): void
    {
        $product = Product::findOrFail($productId);
        $variants = $product->raw_variants;

        if (isset($variants['items'][$vIdx])) {
            $variants['items'][$vIdx]['status'] = 'Disapproved';
            if (!empty($variants['items'][$vIdx]['subs']) && is_array($variants['items'][$vIdx]['subs'])) {
                foreach ($variants['items'][$vIdx]['subs'] as &$sub) {
                    if (($sub['status'] ?? '') === 'Pending') {
                        $sub['status'] = 'Disapproved';
                    }
                }
            }
            $this->recalculateAndSaveProduct($product, $variants);
            session()->flash('success', "Variant \"{$variants['items'][$vIdx]['name']}\" rejected.");
        }
    }

    public function approveSingleSubVariant(int $productId, int $vIdx, int $sIdx): void
    {
        $product = Product::findOrFail($productId);
        $variants = $product->raw_variants;

        if (isset($variants['items'][$vIdx]['subs'][$sIdx])) {
            $variants['items'][$vIdx]['subs'][$sIdx]['status'] = 'Approved';
            $this->recalculateAndSaveProduct($product, $variants);
            session()->flash('success', "Subvariant \"{$variants['items'][$vIdx]['subs'][$sIdx]['name']}\" approved.");
        }
    }

    public function disapproveSingleSubVariant(int $productId, int $vIdx, int $sIdx): void
    {
        $product = Product::findOrFail($productId);
        $variants = $product->raw_variants;

        if (isset($variants['items'][$vIdx]['subs'][$sIdx])) {
            $variants['items'][$vIdx]['subs'][$sIdx]['status'] = 'Disapproved';
            $this->recalculateAndSaveProduct($product, $variants);
            session()->flash('success', "Subvariant \"{$variants['items'][$vIdx]['subs'][$sIdx]['name']}\" rejected.");
        }
    }

    public function promptDisapprove(int $id): void
    {
        $this->selectedApprovalId = $id;
        $this->disapproval_type = 'Violation';
        $this->remarks = '';
        $this->disapproveModalOpen = true;
    }

    public function confirmDisapprove(): void
    {
        $this->validate([
            'disapproval_type' => ['required', 'in:Violation,Warning'],
            'remarks'          => ['required', 'string', 'min:5', 'max:500'],
        ]);

        $approval = ProductApproval::with(['product', 'seller'])->findOrFail($this->selectedApprovalId);
        $admin = Auth::guard('admin')->user();

        $approval->update([
            'status'           => 'Disapproved',
            'disapproval_type' => $this->disapproval_type,
            'reviewed_by'      => $admin?->id,
            'remarks'          => $this->remarks,
        ]);

        if ($approval->product) {
            $variants = $approval->product->raw_variants;
            if (is_array($variants) && isset($variants['items'])) {
                foreach ($variants['items'] as &$item) {
                    if (($item['status'] ?? '') === 'Pending') {
                        $item['status'] = 'Disapproved';
                    }
                    if (!empty($item['subs']) && is_array($item['subs'])) {
                        foreach ($item['subs'] as &$sub) {
                            if (($sub['status'] ?? '') === 'Pending') {
                                $sub['status'] = 'Disapproved';
                            }
                        }
                    }
                }
                $approval->product->variants = $variants;
                $approval->product->has_pending_variants = false;
                $approval->product->save();
            }
        }

        if ($approval->seller) {
            $approval->seller->notify(new ProductComplianceNotification(
                $approval,
                'disapproved',
                $this->disapproval_type,
                $this->remarks
            ));
        }

        $this->disapproveModalOpen = false;
        $this->detailModalOpen = false;
        session()->flash('success', "Product has been rejected under {$this->disapproval_type}s.");
    }

    public function render()
    {
        $this->syncPendingVariantFlags();

        $query = ProductApproval::with(['product', 'seller.sellerProfile', 'reviewer'])
            ->whereHas('product');

        match ($this->activeTab) {
            'approved'   => $query->where('status', 'Approved'),
            'violations' => $query->where('status', 'Disapproved')->where('disapproval_type', 'Violation'),
            'warnings'   => $query->where('status', 'Disapproved')->where('disapproval_type', 'Warning'),
            default      => $query->where(function ($q) {
                $q->where('status', 'Pending')
                  ->orWhere(function ($subQ) {
                      $subQ->where('status', 'Approved')
                           ->whereHas('product', fn($p) => $p->where('has_pending_variants', true));
                  });
            }),
        };

        if ($this->search) {
            $query->where(function ($q) {
                $q->whereHas('product', fn($p) => $p->where('name', 'like', "%{$this->search}%"))
                  ->orWhereHas('seller.sellerProfile', fn($s) => $s->where('business_name', 'like', "%{$this->search}%"));
            });
        }

        $counts = [
            'pending'    => ProductApproval::whereHas('product')->where(function ($q) {
                                $q->where('status', 'Pending')
                                  ->orWhere(function ($subQ) {
                                      $subQ->where('status', 'Approved')
                                           ->whereHas('product', fn($p) => $p->where('has_pending_variants', true));
                                  });
                            })->count(),
            'approved'   => ProductApproval::whereHas('product')->where('status', 'Approved')->count(),
            'violations' => ProductApproval::whereHas('product')->where('status', 'Disapproved')->where('disapproval_type', 'Violation')->count(),
            'warnings'   => ProductApproval::whereHas('product')->where('status', 'Disapproved')->where('disapproval_type', 'Warning')->count(),
        ];

        $currentApproval = $this->selectedApprovalId
            ? ProductApproval::with(['product', 'seller.sellerProfile', 'reviewer'])->find($this->selectedApprovalId)
            : null;

        return view('livewire.admin.seller-compliance', [
            'approvals'       => $query->latest('updated_at')->paginate(10),
            'counts'          => $counts,
            'currentApproval' => $currentApproval,
        ]);
    }
}