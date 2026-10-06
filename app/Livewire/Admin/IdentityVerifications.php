<?php

namespace App\Livewire\Admin;

use App\Models\IdentityVerification;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.admin')]
class IdentityVerifications extends Component
{
    use WithPagination;

    public string $activeTab = 'pending';
    public string $search = '';

    public ?int $selectedId = null;
    public bool $detailModalOpen = false;
    public bool $rejectModalOpen = false;
    public string $rejectionReason = '';

    public bool $docModalOpen = false;
    public string $docModalUrl = '';
    public string $docModalTitle = '';

    public function setTab(string $tab): void
    {
        $this->activeTab = $tab;
        $this->resetPage();
    }

    public function viewDetails(int $id): void
    {
        $this->selectedId = $id;
        $this->detailModalOpen = true;
    }

    public function inspectDoc(string $type, int $id): void
    {
        $item = IdentityVerification::findOrFail($id);
        $this->docModalTitle = strtoupper($type) . ' - ' . $item->user->first_name . ' ' . $item->user->last_name;
        $this->docModalUrl = route('admin.applications.document', [
            'entity' => 'identity',
            'id'     => $item->id,
            'type'   => $type,
        ]);
        $this->docModalOpen = true;
    }

    public function approve(int $id): void
    {
        $item = IdentityVerification::findOrFail($id);
        $item->update([
            'status'           => 'verified',
            'rejection_reason' => null,
            'verified_at'      => now(),
        ]);

        $this->detailModalOpen = false;
        session()->flash('success', "Identity verification approved.");
    }

    public function promptReject(int $id): void
    {
        $this->selectedId = $id;
        $this->rejectionReason = '';
        $this->rejectModalOpen = true;
    }

    public function confirmReject(): void
    {
        $this->validate([
            'rejectionReason' => ['required', 'string', 'min:5', 'max:500'],
        ]);

        $item = IdentityVerification::findOrFail($this->selectedId);
        $item->update([
            'status'           => 'failed',
            'rejection_reason' => $this->rejectionReason,
            'verified_at'      => null,
        ]);

        $this->rejectModalOpen = false;
        $this->detailModalOpen = false;
        session()->flash('success', "Identity marked as failed.");
    }

    public function render()
    {
        $query = IdentityVerification::with('user')
            ->where('status', $this->activeTab)
            ->when($this->search, function ($q) {
                $q->whereHas('user', function ($u) {
                    $u->where('first_name', 'like', "%{$this->search}%")
                      ->orWhere('last_name', 'like', "%{$this->search}%")
                      ->orWhere('email', 'like', "%{$this->search}%");
                });
            })
            ->latest();

        return view('livewire.admin.identity-verifications', [
            'verifications' => $query->paginate(10),
            'counts' => [
                'pending'  => IdentityVerification::where('status', 'pending')->count(),
                'verified' => IdentityVerification::where('status', 'verified')->count(),
                'failed'   => IdentityVerification::where('status', 'failed')->count(),
            ],
            'currentItem' => $this->selectedId ? IdentityVerification::find($this->selectedId) : null,
        ]);
    }
}