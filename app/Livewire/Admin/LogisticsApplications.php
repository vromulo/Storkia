<?php

namespace App\Livewire\Admin;

use App\Mail\LogisticsApplicationDecisionMail;
use App\Models\LogisticsApplication;
use App\Models\LogisticsProfile;
use App\Notifications\OrderLifecycleNotification;
use App\Services\AuditLoggerService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function setTab(string $tab): void
    {
        $this->activeTab = $tab;
        $this->resetPage();
    }

    public function viewApplication(int $appId): void
    {
        $this->selectedAppId = $appId;
        $this->detailModalOpen = true;
    }

    public function approve(int $appId): void
    {
        $app = LogisticsApplication::with('user')->findOrFail($appId);
        $admin = Auth::guard('admin')->user();

        DB::beginTransaction();
        try {
            $app->update([
                'status'           => 'approved',
                'rejection_reason' => null,
                'reviewed_by'      => $admin?->id,
                'reviewed_at'      => now(),
            ]);

            $profile = LogisticsProfile::updateOrCreate(
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

            $app->user?->update(['role' => 'Logistics']);

            if (method_exists($profile, 'ensureMainHubExists')) {
                $profile->ensureMainHubExists();
            }

            DB::commit();

            AuditLoggerService::log(
                action: 'logistics_application_approved',
                targetType: 'LogisticsApplication',
                targetId: (int) $app->id,
                targetReference: $app->business_name
            );

            try {
                if ($app->user && class_exists(LogisticsApplicationDecisionMail::class)) {
                    Mail::to($app->user->email)->send(new LogisticsApplicationDecisionMail($app, 'approved'));
                }
                $app->user?->notify(new OrderLifecycleNotification(
                    title: 'Logistics Sorting Hub Approved!',
                    message: "Your logistics hub application for {$app->business_name} has been approved! Your Main Hub is now active.",
                    type: 'success',
                    url: route('logistics.logistics-dashboard')
                ));
            } catch (\Throwable $e) {
                Log::warning("Failed sending approval notification to {$app->user?->email}: " . $e->getMessage());
            }

            $this->detailModalOpen = false;
            session()->flash('success', "Logistics sorting center for {$app->business_name} approved successfully.");
        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error("Logistics approval failed for application ID {$appId}: " . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            session()->flash('error', 'Failed to approve logistics application: ' . $e->getMessage());
        }
    }

    public function promptReject(int $appId): void
    {
        $this->selectedAppId = $appId;
        $this->rejectionReason = '';
        $this->rejectModalOpen = true;
    }

    public function confirmReject(): void
    {
        $this->validate([
            'rejectionReason' => ['required', 'string', 'min:10', 'max:1000'],
        ]);

        $app = LogisticsApplication::with('user')->findOrFail($this->selectedAppId);
        $admin = Auth::guard('admin')->user();

        $app->update([
            'status'           => 'rejected',
            'rejection_reason' => $this->rejectionReason,
            'reviewed_by'      => $admin?->id,
            'reviewed_at'      => now(),
        ]);

        AuditLoggerService::log(
            action: 'logistics_application_rejected',
            targetType: 'LogisticsApplication',
            targetId: (int) $app->id,
            targetReference: $app->business_name,
            reason: $this->rejectionReason
        );

        try {
            if ($app->user && class_exists(LogisticsApplicationDecisionMail::class)) {
                Mail::to($app->user->email)->send(new LogisticsApplicationDecisionMail($app, 'rejected', $this->rejectionReason));
            }
            $app->user?->notify(new OrderLifecycleNotification(
                title: 'Logistics Hub Application Requires Updates',
                message: "Your logistics application for {$app->business_name} was not approved. Reason: {$this->rejectionReason}",
                type: 'danger',
                url: route('logistics.reapply')
            ));
        } catch (\Throwable $e) {
        }

        $this->rejectModalOpen = false;
        $this->detailModalOpen = false;
        session()->flash('success', 'Logistics application has been rejected.');
    }

    public function inspectDoc(string $type, int $appId): void
    {
        $app = LogisticsApplication::findOrFail($appId);
        $this->selectedAppId = $appId;

        $path = $type === 'id' ? $app->id_path : $app->permit_path;

        $ext = strtolower(pathinfo((string) $path, PATHINFO_EXTENSION));
        $this->docModalType = in_array($ext, ['pdf'], true) ? 'pdf' : 'image';
        $this->docModalTitle = ($type === 'id' ? 'Valid ID' : 'Business Permit') . " - {$app->business_name} (v{$app->version})";

        $this->docModalUrl = route('admin.applications.document', [
            'entity' => 'logistics',
            'id'     => $app->id,
            'type'   => $type,
        ]);

        $this->docModalOpen = true;
    }

    public function closeDocModal(): void
    {
        $this->docModalOpen = false;
        $this->docModalUrl = '';
    }

    public function render()
    {
        $query = LogisticsApplication::with(['user', 'reviewer'])
            ->where('status', $this->activeTab)
            ->when($this->search, function ($q) {
                $q->where(function ($sub) {
                    $sub->where('business_name', 'like', "%{$this->search}%")
                        ->orWhere('contact_no', 'like', "%{$this->search}%")
                        ->orWhere('municipality', 'like', "%{$this->search}%")
                        ->orWhereHas('user', function ($u) {
                            $u->where('first_name', 'like', "%{$this->search}%")
                              ->orWhere('last_name', 'like', "%{$this->search}%")
                              ->orWhere('email', 'like', "%{$this->search}%");
                        });
                });
            })
            ->latest('id');

        $currentApp = $this->selectedAppId
            ? LogisticsApplication::with(['user', 'reviewer'])->find($this->selectedAppId)
            : null;

        $history = $currentApp
            ? LogisticsApplication::where('user_id', $currentApp->user_id)->orderByDesc('version')->get()
            : collect();

        return view('livewire.admin.logistics-applications', [
            'applications' => $query->paginate(10),
            'counts'       => [
                'pending'  => LogisticsApplication::where('status', 'pending')->count(),
                'approved' => LogisticsApplication::where('status', 'approved')->count(),
                'rejected' => LogisticsApplication::where('status', 'rejected')->count(),
            ],
            'currentApp'   => $currentApp,
            'history'      => $history,
        ]);
    }
}