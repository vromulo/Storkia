<?php

namespace App\Livewire\Admin;

use App\Models\ProductApproval;
use App\Notifications\ProductComplianceNotification;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.admin')]
class SellerCompliance extends Component
{
    use WithPagination;

    public string $activeTab = 'pending'; // 'pending', 'approved', 'violations', 'warnings'
    public string $search = '';

    // Modals
    public ?int $selectedApprovalId = null;
    public bool $detailModalOpen = false;
    public bool $disapproveModalOpen = false;

    // Disapproval Form
    public string $disapproval_type = 'Violation';
    public string $remarks = '';

    // Image viewer modal inside review
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

        if ($approval->seller) {
            $approval->seller->notify(new ProductComplianceNotification($approval, 'approved'));
        }

        $this->detailModalOpen = false;
        session()->flash('success', "Product \"{$approval->product?->name}\" approved and is now active.");
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
        $query = ProductApproval::with(['product', 'seller.sellerProfile', 'reviewer']);

        match ($this->activeTab) {
            'approved'   => $query->where('status', 'Approved'),
            'violations' => $query->where('status', 'Disapproved')->where('disapproval_type', 'Violation'),
            'warnings'   => $query->where('status', 'Disapproved')->where('disapproval_type', 'Warning'),
            default      => $query->where('status', 'Pending'),
        };

        if ($this->search) {
            $query->where(function ($q) {
                $q->whereHas('product', fn ($p) => $p->where('name', 'like', "%{$this->search}%"))
                  ->orWhereHas('seller.sellerProfile', fn ($s) => $s->where('business_name', 'like', "%{$this->search}%"));
            });
        }

        $counts = [
            'pending'    => ProductApproval::where('status', 'Pending')->count(),
            'approved'   => ProductApproval::where('status', 'Approved')->count(),
            'violations' => ProductApproval::where('status', 'Disapproved')->where('disapproval_type', 'Violation')->count(),
            'warnings'   => ProductApproval::where('status', 'Disapproved')->where('disapproval_type', 'Warning')->count(),
        ];

        $currentApproval = $this->selectedApprovalId
            ? ProductApproval::with(['product', 'seller.sellerProfile', 'reviewer'])->find($this->selectedApprovalId)
            : null;

        return view('livewire.admin.seller-compliance', [
            'approvals'       => $query->latest('id')->paginate(10),
            'counts'          => $counts,
            'currentApproval' => $currentApproval,
        ]);
    }
}