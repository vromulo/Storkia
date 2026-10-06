<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 w-full py-6">
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-8">
        <div>
            <h1 class="text-2xl sm:text-3xl font-bold text-primary-dark">Buyer Identity Verifications</h1>
            <p class="text-text-muted text-xs sm:text-sm mt-1">Review government ID proofs and verify customer identities.</p>
        </div>
        <div class="w-full md:w-72">
            <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search user by name or email..." class="w-full py-2 px-3 text-sm bg-surface border border-border-subtle rounded-xl outline-none focus:border-primary shadow-xs">
        </div>
    </div>

    @if(session('success'))
        <div class="mb-6 p-4 rounded-xl bg-green-50 border border-green-200 text-green-700 text-sm font-semibold flex items-center gap-2">
            {{ session('success') }}
        </div>
    @endif

    <div class="flex border-b border-border-subtle mb-6 gap-2 sm:gap-6">
        @foreach (['pending' => 'Pending', 'verified' => 'Verified', 'failed' => 'Failed'] as $tab => $label)
            <button type="button" wire:click="setTab('{{ $tab }}')" class="pb-3 px-2 text-sm font-bold border-b-2 transition-colors cursor-pointer flex items-center gap-2 {{ $activeTab === $tab ? 'border-primary text-primary' : 'border-transparent text-text-muted' }}">
                <span>{{ $label }}</span>
                <span class="text-xs px-2 py-0.5 rounded-full {{ $activeTab === $tab ? 'bg-primary text-white' : 'bg-surface-subtle text-text-muted' }}">{{ $counts[$tab] }}</span>
            </button>
        @endforeach
    </div>

    <div class="bg-surface rounded-2xl border border-border-subtle shadow-xs overflow-hidden">
        <table class="w-full text-left text-sm">
            <thead class="bg-surface-subtle border-b border-border-subtle text-text-muted text-xs uppercase font-bold tracking-wider">
                <tr>
                    <th class="py-3.5 px-4">User</th>
                    <th class="py-3.5 px-4">Document Type</th>
                    <th class="py-3.5 px-4">Method</th>
                    <th class="py-3.5 px-4">Date Submitted</th>
                    <th class="py-3.5 px-4 text-right">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-border-subtle/70">
                @forelse($verifications as $v)
                    <tr class="hover:bg-brand-light/10 transition-colors">
                        <td class="py-3.5 px-4">
                            <p class="font-bold text-text-main">{{ $v->user->first_name }} {{ $v->user->last_name }}</p>
                            <p class="text-xs text-text-muted">{{ $v->user->email }}</p>
                        </td>
                        <td class="py-3.5 px-4 capitalize">{{ str_replace('_', ' ', $v->id_type) }}</td>
                        <td class="py-3.5 px-4 capitalize">{{ $v->method }}</td>
                        <td class="py-3.5 px-4 text-xs text-text-muted">{{ $v->created_at->format('M d, Y h:i A') }}</td>
                        <td class="py-3.5 px-4 text-right">
                            <button type="button" wire:click="viewDetails({{ $v->id }})" class="px-3 py-1.5 bg-primary/10 text-primary hover:bg-primary hover:text-white font-bold text-xs rounded-lg transition-colors cursor-pointer">
                                Review ID
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="p-8 text-center text-text-muted text-sm">No {{ $activeTab }} verifications found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $verifications->links() }}</div>

    {{-- Review Modal --}}
    @if ($detailModalOpen && $currentItem)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-xs">
            <div class="bg-surface rounded-3xl max-w-2xl w-full p-6 shadow-2xl border border-border-subtle space-y-5">
                <div class="flex items-center justify-between border-b border-border-subtle pb-3">
                    <h3 class="font-bold text-base text-primary-dark">Identity Verification - {{ $currentItem->user->first_name }} {{ $currentItem->user->last_name }}</h3>
                    <button type="button" wire:click="$set('detailModalOpen', false)" class="text-text-muted hover:text-danger">&times;</button>
                </div>
                <div class="grid grid-cols-2 gap-4 text-xs">
                    <div>Email: <strong class="text-text-main">{{ $currentItem->user->email }}</strong></div>
                    <div>Type: <strong class="text-text-main capitalize">{{ str_replace('_', ' ', $currentItem->id_type) }}</strong></div>
                </div>
                <div class="grid grid-cols-2 gap-4 pt-2">
                    <div class="border border-border-subtle rounded-xl p-3 flex items-center justify-between">
                        <span class="text-xs font-semibold">Front Image</span>
                        <button type="button" wire:click="inspectDoc('front', {{ $currentItem->id }})" class="text-xs font-bold text-primary hover:underline cursor-pointer">Inspect</button>
                    </div>
                    @if ($currentItem->back_image_path)
                        <div class="border border-border-subtle rounded-xl p-3 flex items-center justify-between">
                            <span class="text-xs font-semibold">Back Image</span>
                            <button type="button" wire:click="inspectDoc('back', {{ $currentItem->id }})" class="text-xs font-bold text-primary hover:underline cursor-pointer">Inspect</button>
                        </div>
                    @endif
                </div>
                <div class="flex justify-end gap-3 pt-4 border-t border-border-subtle">
                    <button type="button" wire:click="$set('detailModalOpen', false)" class="px-4 py-2 text-xs font-bold text-text-muted hover:bg-surface-subtle rounded-xl">Close</button>
                    @if ($currentItem->status === 'pending')
                        <button type="button" wire:click="promptReject({{ $currentItem->id }})" class="px-5 py-2 text-xs font-bold text-white bg-danger rounded-xl">Reject / Fail</button>
                        <button type="button" wire:click="approve({{ $currentItem->id }})" class="px-5 py-2 text-xs font-bold text-white bg-green-600 rounded-xl">Approve</button>
                    @endif
                </div>
            </div>
        </div>
    @endif

    {{-- Doc Viewer Modal --}}
    @if ($docModalOpen)
        <div class="fixed inset-0 z-[60] flex items-center justify-center p-4 bg-black/70 backdrop-blur-xs">
            <div class="bg-surface rounded-3xl max-w-3xl w-full p-4 flex flex-col h-[80vh]">
                <div class="flex justify-between items-center pb-2 border-b border-border-subtle">
                    <h4 class="font-bold text-sm">{{ $docModalTitle }}</h4>
                    <button type="button" wire:click="$set('docModalOpen', false)" class="text-text-muted">&times;</button>
                </div>
                <div class="flex-1 flex items-center justify-center p-2 overflow-auto bg-surface-subtle rounded-xl mt-2">
                    <img src="{{ $docModalUrl }}" class="max-h-full max-w-full object-contain rounded-lg">
                </div>
            </div>
        </div>
    @endif

    {{-- Rejection Modal --}}
    @if ($rejectModalOpen)
        <div class="fixed inset-0 z-[70] flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs">
            <div class="bg-surface rounded-3xl max-w-md w-full p-6 shadow-2xl border border-border-subtle space-y-4">
                <h3 class="text-base font-bold text-danger">Fail Identity Verification</h3>
                <textarea wire:model="rejectionReason" rows="3" placeholder="State reason (e.g., Image is too blurry to read name)..." class="w-full p-3 text-xs border border-border-subtle rounded-xl outline-none"></textarea>
                @error('rejectionReason') <p class="text-danger text-xs">{{ $message }}</p> @enderror
                <div class="flex justify-end gap-2">
                    <button type="button" wire:click="$set('rejectModalOpen', false)" class="px-4 py-2 text-xs font-bold text-text-muted">Cancel</button>
                    <button type="button" wire:click="confirmReject" class="px-4 py-2 text-xs font-bold text-white bg-danger rounded-xl">Confirm Failure</button>
                </div>
            </div>
        </div>
    @endif
</div>