<?php

namespace App\Livewire\Admin;

use App\Mail\SellerApplicationDecisionMail;
use App\Models\SellerApplication;
use App\Models\SellerProfile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.admin')]
class SellerApplications extends Component
{
    use WithPagination;

    public string $activeTab = 'pending';
    public string $search = '';

    // Review Modal & Inspection
    public ?int $selectedAppId = null;
    public bool $detailModalOpen = false;

    // Document Inspection Modal
    public bool $docModalOpen = false;
    public string $docModalUrl = '';
    public string $docModalTitle = '';
    public string $docModalType = ''; // 'image' or 'pdf'

    // Rejection Action State
    public bool $rejectModalOpen = false;
    public string $rejectionReason = '';

    protected $queryString = ['activeTab' => ['except' => 'pending'], 'search' => ['except' => '']];

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function setTab(string $tab)
    {
        $this->activeTab = $tab;
        $this->resetPage();
    }

    public function viewApplication(int $appId)
    {
        $this->selectedAppId = $appId;
        $this->detailModalOpen = true;
    }

    public function inspectDoc(string $type, int $appId)
    {
        $app = SellerApplication::findOrFail($appId);
        $this->selectedAppId = $appId;
        $path = $type === 'id' ? $app->id_path : $app->permit_path;
        
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $this->docModalType = in_array($ext, ['pdf']) ? 'pdf' : 'image';
        $this->docModalTitle = ($type === 'id' ? 'Valid ID' : 'Business Permit') . " - {$app->business_name} (v{$app->version})";
        
        // FIXED: Updated route name and provided the required parameters (entity, id, type)
        $this->docModalUrl = route('admin.applications.document', [
            'entity' => 'seller', 
            'id' => $app->id, 
            'type' => $type
        ]);
        
        $this->docModalOpen = true;
    }

    public function closeDocModal()
    {
        $this->docModalOpen = false;
        $this->docModalUrl = '';
    }

    public function approve(int $appId)
    {
        $app = SellerApplication::with('user')->findOrFail($appId);
        $admin = Auth::guard('admin')->user();

        $app->update([
            'status' => 'approved',
            'rejection_reason' => null,
            'reviewed_by' => $admin->id,
            'reviewed_at' => now(),
        ]);

        // Create or update the seller's active approved profile from this approved application.
        // seller_profiles has no status column: a row existing here already means "approved".
        SellerProfile::updateOrCreate(
            ['user_id' => $app->user_id],
            [
                'contact_no' => $app->contact_no,
                'province' => $app->province,
                'municipality' => $app->municipality,
                'barangay' => $app->barangay,
                'street' => $app->street,
                'house_details' => $app->house_details,
                'business_name' => $app->business_name,
                'line_of_business' => $app->line_of_business,
                'id_path' => $app->id_path,
                'permit_path' => $app->permit_path,
            ]
        );

        // Grant active seller role
        $app->user->update(['role' => 'Seller']);

        // Send confirmation email
        try {
            Mail::to($app->user->email)->send(new SellerApplicationDecisionMail($app, 'approved'));
        } catch (\Throwable $e) {}

        $this->detailModalOpen = false;
        session()->flash('success', "Application for {$app->business_name} approved successfully.");
    }

    public function promptReject(int $appId)
    {
        $this->selectedAppId = $appId;
        $this->rejectionReason = '';
        $this->rejectModalOpen = true;
    }

    public function confirmReject()
    {
        $this->validate([
            'rejectionReason' => ['required', 'string', 'min:10', 'max:1000'],
        ], [
            'rejectionReason.required' => 'Please provide a clear reason for rejecting this application.',
            'rejectionReason.min' => 'The rejection explanation must be at least 10 characters.',
        ]);

        $app = SellerApplication::with('user')->findOrFail($this->selectedAppId);
        $admin = Auth::guard('admin')->user();

        $app->update([
            'status' => 'rejected',
            'rejection_reason' => $this->rejectionReason,
            'reviewed_by' => $admin->id,
            'reviewed_at' => now(),
        ]);

        // Note: seller_profiles is intentionally left untouched here. It holds
        // the seller's current *approved* information only. Rejecting a new
        // application (even one submitted by an already-approved seller)
        // never overwrites their existing approved profile — it only lives
        // as history on this seller_applications row.

        // Dispatch decision email with rejection reason
        try {
            Mail::to($app->user->email)->send(new SellerApplicationDecisionMail($app, 'rejected', $this->rejectionReason));
        } catch (\Throwable $e) {}

        $this->rejectModalOpen = false;
        $this->detailModalOpen = false;
        session()->flash('success', "Application for {$app->business_name} has been rejected.");
    }

    public function render()
    {
        $query = SellerApplication::with(['user', 'reviewer'])
            ->where('status', $this->activeTab)
            ->when($this->search, function ($q) {
                $q->where(function ($sub) {
                    $sub->where('business_name', 'like', "%{$this->search}%")
                        ->orWhere('contact_no', 'like', "%{$this->search}%")
                        ->orWhereHas('user', function ($u) {
                            $u->where('first_name', 'like', "%{$this->search}%")
                              ->orWhere('last_name', 'like', "%{$this->search}%")
                              ->orWhere('email', 'like', "%{$this->search}%");
                        });
                });
            })
            ->latest('id');

        $counts = [
            'pending' => SellerApplication::where('status', 'pending')->count(),
            'approved' => SellerApplication::where('status', 'approved')->count(),
            'rejected' => SellerApplication::where('status', 'rejected')->count(),
        ];

        $currentApp = $this->selectedAppId 
            ? SellerApplication::with(['user', 'reviewer'])->find($this->selectedAppId) 
            : null;

        $history = $currentApp 
            ? SellerApplication::where('user_id', $currentApp->user_id)->orderByDesc('version')->get() 
            : collect();

        return view('livewire.admin.seller-applications', [
            'applications' => $query->paginate(10),
            'counts' => $counts,
            'currentApp' => $currentApp,
            'history' => $history,
        ]);
    }
}