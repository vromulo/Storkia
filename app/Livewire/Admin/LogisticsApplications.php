<?php

namespace App\Livewire\Admin;

use App\Mail\LogisticsApplicationDecisionMail;
use App\Models\LogisticsApplication;
use App\Models\LogisticsProfile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.admin')]
class LogisticsApplications extends Component
{
    use WithPagination;

    public string $activeTab = 'pending';
    public string $search = '';

    public ?int $selectedAppId = null;
    public bool $detailModalOpen = false;

    public bool $docModalOpen = false;
    public string $docModalUrl = '';
    public string $docModalTitle = '';
    public string $docModalType = '';

    public bool $rejectModalOpen = false;
    public string $rejectionReason = '';

    protected $queryString = ['activeTab' => ['except' => 'pending'], 'search' => ['except' => '']];

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

    public function approve(int $appId)
    {
        $app = LogisticsApplication::with('user')->findOrFail($appId);
        $admin = Auth::guard('admin')->user();

        $app->update([
            'status' => 'approved',
            'rejection_reason' => null,
            'reviewed_by' => $admin->id,
            'reviewed_at' => now(),
        ]);

        // Populates or updates active logistics profile strictly on approval
        LogisticsProfile::updateOrCreate(
            ['user_id' => $app->user_id],
            [
                'contact_no'    => $app->contact_no,
                'province'      => $app->province,
                'municipality'  => $app->municipality,
                'barangay'      => $app->barangay,
                'street'        => $app->street,
                'house_details' => $app->house_details,
                'business_name' => $app->business_name,
                'id_path'       => $app->id_path,
                'permit_path'   => $app->permit_path,
            ]
        );

        $app->user->update(['role' => 'Logistics']);

        try {
            Mail::to($app->user->email)->send(new LogisticsApplicationDecisionMail($app, 'approved'));
        } catch (\Throwable $e) {}

        $this->detailModalOpen = false;
        session()->flash('success', "Logistics sorting center for {$app->business_name} approved successfully.");
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
        ]);

        $app = LogisticsApplication::with('user')->findOrFail($this->selectedAppId);
        $admin = Auth::guard('admin')->user();

        $app->update([
            'status' => 'rejected',
            'rejection_reason' => $this->rejectionReason,
            'reviewed_by' => $admin->id,
            'reviewed_at' => now(),
        ]);

        try {
            Mail::to($app->user->email)->send(new LogisticsApplicationDecisionMail($app, 'rejected', $this->rejectionReason));
        } catch (\Throwable $e) {}

        $this->rejectModalOpen = false;
        $this->detailModalOpen = false;
        session()->flash('success', "Logistics application has been rejected.");
    }

    public function render()
    {
        $query = LogisticsApplication::with(['user', 'reviewer'])
            ->where('status', $this->activeTab)
            ->when($this->search, function ($q) {
                $q->where('business_name', 'like', "%{$this->search}%")
                  ->orWhere('contact_no', 'like', "%{$this->search}%");
            })
            ->latest('id');

        return view('livewire.admin.logistics-applications', [
            'applications' => $query->paginate(10),
            'counts' => [
                'pending'  => LogisticsApplication::where('status', 'pending')->count(),
                'approved' => LogisticsApplication::where('status', 'approved')->count(),
                'rejected' => LogisticsApplication::where('status', 'rejected')->count(),
            ],
            'currentApp' => $this->selectedAppId ? LogisticsApplication::find($this->selectedAppId) : null,
            'history' => $this->selectedAppId ? LogisticsApplication::where('user_id', LogisticsApplication::find($this->selectedAppId)->user_id)->orderByDesc('version')->get() : collect(),
        ]);
    }
}