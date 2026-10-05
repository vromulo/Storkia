<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 w-full py-6">
    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-8">
        <div>
            <div class="flex items-center gap-3">
                <h1 class="text-2xl sm:text-3xl font-bold text-primary-dark">Logistics Applications</h1>
            </div>
            <p class="text-text-muted text-xs sm:text-sm mt-1">Review hub documentation, facility locations, and onboarding requests.</p>
        </div>
        <div class="w-full md:w-72">
            <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search hub name or contact..." class="w-full py-2 px-3 text-sm bg-surface border border-border-subtle rounded-xl outline-none focus:border-primary focus:ring-1 focus:ring-primary shadow-xs">
        </div>
    </div>

    @if(session('success'))
        <div class="mb-6 p-4 rounded-xl bg-green-50 border border-green-200 text-green-700 text-sm font-semibold flex items-center gap-2">
            <svg class="w-5 h-5 text-green-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            {{ session('success') }}
        </div>
    @endif

    <!-- Status Tabs -->
    <div class="flex border-b border-border-subtle mb-6 gap-2 sm:gap-6">
        <button type="button" wire:click="setTab('pending')" class="pb-3 px-2 text-sm font-bold border-b-2 transition-colors cursor-pointer flex items-center gap-2 {{ $activeTab === 'pending' ? 'border-primary text-primary' : 'border-transparent text-text-muted hover:text-text-main' }}">
            <span>Pending</span>
            <span class="text-xs px-2 py-0.5 rounded-full {{ $activeTab === 'pending' ? 'bg-primary text-white' : 'bg-surface-subtle text-text-muted' }}">{{ $counts['pending'] }}</span>
        </button>
        <button type="button" wire:click="setTab('approved')" class="pb-3 px-2 text-sm font-bold border-b-2 transition-colors cursor-pointer flex items-center gap-2 {{ $activeTab === 'approved' ? 'border-primary text-primary' : 'border-transparent text-text-muted hover:text-text-main' }}">
            <span>Approved</span>
            <span class="text-xs px-2 py-0.5 rounded-full {{ $activeTab === 'approved' ? 'bg-primary text-white' : 'bg-surface-subtle text-text-muted' }}">{{ $counts['approved'] }}</span>
        </button>
        <button type="button" wire:click="setTab('rejected')" class="pb-3 px-2 text-sm font-bold border-b-2 transition-colors cursor-pointer flex items-center gap-2 {{ $activeTab === 'rejected' ? 'border-primary text-primary' : 'border-transparent text-text-muted hover:text-text-main' }}">
            <span>Rejected</span>
            <span class="text-xs px-2 py-0.5 rounded-full {{ $activeTab === 'rejected' ? 'bg-primary text-white' : 'bg-surface-subtle text-text-muted' }}">{{ $counts['rejected'] }}</span>
        </button>
    </div>

    <!-- Applications Table -->
    <div class="bg-surface rounded-2xl border border-border-subtle shadow-xs overflow-hidden">
        <table class="w-full text-left text-sm">
            <thead class="bg-surface-subtle border-b border-border-subtle text-text-muted text-xs uppercase font-bold tracking-wider">
                <tr>
                    <th class="py-3.5 px-4">Hub / Center Name</th>
                    <th class="py-3.5 px-4">Manager / Contact</th>
                    <th class="py-3.5 px-4">Location</th>
                    <th class="py-3.5 px-4 text-center">Version</th>
                    <th class="py-3.5 px-4">Submitted</th>
                    <th class="py-3.5 px-4 text-right">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-border-subtle/70">
                @forelse($applications as $app)
                    <tr class="hover:bg-brand-light/10 transition-colors">
                        <td class="py-3.5 px-4">
                            <p class="font-bold text-text-main">{{ $app->business_name }}</p>
                        </td>
                        <td class="py-3.5 px-4">
                            <p class="font-medium text-text-main">{{ $app->user->first_name }} {{ $app->user->last_name }}</p>
                            <p class="text-xs text-text-muted">{{ $app->contact_no }}</p>
                        </td>
                        <td class="py-3.5 px-4 text-text-muted">{{ $app->municipality }}, {{ $app->province }}</td>
                        <td class="py-3.5 px-4 text-center">
                            <span class="px-2 py-0.5 bg-surface-subtle border border-border-subtle text-text-main font-bold rounded-md text-xs">v{{ $app->version }}</span>
                        </td>
                        <td class="py-3.5 px-4 text-text-muted text-xs">{{ $app->created_at->format('M d, Y h:i A') }}</td>
                        <td class="py-3.5 px-4 text-right">
                            <button type="button" wire:click="viewApplication({{ $app->id }})" class="px-3 py-1.5 bg-primary/10 text-primary hover:bg-primary hover:text-white font-bold text-xs rounded-lg transition-colors cursor-pointer">
                                Review Details
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="p-8 text-center text-text-muted text-sm">
                            No {{ $activeTab }} logistics applications found.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $applications->links() }}
    </div>

    <!-- Review Modal -->
    @if($detailModalOpen && $currentApp)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-xs overflow-y-auto">
            <div class="bg-surface rounded-3xl max-w-3xl w-full p-6 sm:p-8 shadow-2xl border border-border-subtle max-h-[90vh] overflow-y-auto space-y-6 my-auto">
                <div class="flex items-center justify-between border-b border-border-subtle pb-4">
                    <div>
                        <h2 class="text-xl font-bold text-primary-dark">{{ $currentApp->business_name }}</h2>
                        <p class="text-xs text-text-muted">Application Revision v{{ $currentApp->version }} • Submitted {{ $currentApp->created_at->format('M d, Y h:i A') }}</p>
                    </div>
                    <button type="button" wire:click="$set('detailModalOpen', false)" class="text-text-muted hover:text-danger p-1">
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-xs bg-surface-subtle p-4 rounded-2xl border border-border-subtle">
                    <div><span class="text-text-muted">Hub Manager:</span> <strong class="text-text-main">{{ $currentApp->user->first_name }} {{ $currentApp->user->last_name }}</strong></div>
                    <div><span class="text-text-muted">Email:</span> <strong class="text-text-main">{{ $currentApp->user->email }}</strong></div>
                    <div><span class="text-text-muted">Contact:</span> <strong class="text-text-main">{{ $currentApp->contact_no }}</strong></div>
                    <div><span class="text-text-muted">Status:</span> <strong class="capitalize text-text-main">{{ $currentApp->status }}</strong></div>
                    <div class="md:col-span-2"><span class="text-text-muted">Hub Facility Address:</span> <strong class="text-text-main">{{ $currentApp->house_details ? $currentApp->house_details.', ' : '' }}{{ $currentApp->street ? $currentApp->street.', ' : '' }}{{ $currentApp->barangay }}, {{ $currentApp->municipality }}, {{ $currentApp->province }}</strong></div>
                </div>

                <!-- Documents Inspection -->
                <div>
                    <h3 class="text-xs font-bold uppercase tracking-wider text-text-muted mb-2">Facility & Identity Documents</h3>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div class="border border-border-subtle rounded-xl p-3 flex items-center justify-between bg-surface">
                            <span class="text-xs font-semibold text-text-main truncate">Valid Government ID</span>
                            <button type="button" wire:click="inspectDoc('id', {{ $currentApp->id }})" class="text-xs font-bold text-primary hover:underline px-2 py-1 cursor-pointer">
                                Inspect Document
                            </button>
                        </div>
                        <div class="border border-border-subtle rounded-xl p-3 flex items-center justify-between bg-surface">
                            <span class="text-xs font-semibold text-text-main truncate">Hub Business Permit</span>
                            <button type="button" wire:click="inspectDoc('permit', {{ $currentApp->id }})" class="text-xs font-bold text-primary hover:underline px-2 py-1 cursor-pointer">
                                Inspect Document
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Revision History -->
                @if($history->count() > 1)
                    <div class="border-t border-border-subtle pt-4">
                        <h3 class="text-xs font-bold uppercase tracking-wider text-text-muted mb-3">Submission History & Revisions ({{ $history->count() }})</h3>
                        <div class="space-y-2 max-h-40 overflow-y-auto">
                            @foreach($history as $past)
                                <div class="p-3 rounded-xl border border-border-subtle text-xs flex items-center justify-between {{ $past->id === $currentApp->id ? 'bg-brand-light/20 border-primary/40' : 'bg-surface-subtle' }}">
                                    <div>
                                        <span class="font-bold">v{{ $past->version }}</span>
                                        <span class="ml-2 font-semibold capitalize {{ $past->status === 'approved' ? 'text-green-600' : ($past->status === 'rejected' ? 'text-danger' : 'text-yellow-600') }}">{{ $past->status }}</span>
                                        <span class="text-text-muted text-[10px] ml-2">{{ $past->created_at->format('M d, Y h:i A') }}</span>
                                        @if($past->rejection_reason)
                                            <p class="text-[11px] text-danger mt-1 italic font-medium">"{{ $past->rejection_reason }}"</p>
                                        @endif
                                    </div>
                                    @if($past->id !== $currentApp->id)
                                        <button type="button" wire:click="viewApplication({{ $past->id }})" class="text-primary text-[11px] font-bold hover:underline cursor-pointer">
                                            View Snapshot
                                        </button>
                                    @else
                                        <span class="text-[10px] text-primary font-bold">Viewing</span>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                <!-- Modal Actions -->
                <div class="flex justify-end gap-3 border-t border-border-subtle pt-4">
                    <button type="button" wire:click="$set('detailModalOpen', false)" class="px-4 py-2 text-xs font-bold text-text-muted hover:bg-surface-subtle rounded-xl cursor-pointer">
                        Close
                    </button>
                    @if($currentApp->status === 'pending')
                        <button type="button" wire:click="promptReject({{ $currentApp->id }})" class="px-5 py-2 text-xs font-bold text-white bg-danger hover:bg-red-700 rounded-xl transition-colors cursor-pointer">
                            Reject Application
                        </button>
                        <button type="button" wire:click="approve({{ $currentApp->id }})" class="px-5 py-2 text-xs font-bold text-white bg-green-600 hover:bg-green-700 rounded-xl transition-colors cursor-pointer">
                            Approve Application
                        </button>
                    @endif
                </div>
            </div>
        </div>
    @endif

    <!-- Document Inspection Modal -->
    @if($docModalOpen)
        <div class="fixed inset-0 z-[60] flex items-center justify-center p-4 bg-black/70 backdrop-blur-xs">
            <div class="bg-surface rounded-3xl max-w-4xl w-full p-4 sm:p-6 shadow-2xl border border-border-subtle flex flex-col h-[85vh]">
                <div class="flex items-center justify-between pb-3 border-b border-border-subtle">
                    <h3 class="font-bold text-sm text-primary-dark truncate">{{ $docModalTitle }}</h3>
                    <div class="flex items-center gap-2">
                        <a href="{{ $docModalUrl }}" target="_blank" class="text-xs font-bold text-primary hover:underline px-2 py-1">Open in Tab</a>
                        <button type="button" wire:click="closeDocModal" class="p-1 rounded-lg hover:bg-surface-subtle text-text-muted hover:text-text-main cursor-pointer">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>
                </div>
                <div class="flex-1 overflow-auto p-2 flex items-center justify-center bg-surface-subtle rounded-2xl mt-3">
                    @if($docModalType === 'image')
                        <img src="{{ $docModalUrl }}" alt="Document" class="max-h-full max-w-full object-contain rounded-lg shadow-sm">
                    @else
                        <iframe src="{{ $docModalUrl }}" class="w-full h-full rounded-lg border-0"></iframe>
                    @endif
                </div>
                <div class="pt-3 flex justify-end">
                    <button type="button" wire:click="closeDocModal" class="px-4 py-2 bg-primary text-white text-xs font-bold rounded-xl hover:bg-primary-dark cursor-pointer">
                        Return to Application
                    </button>
                </div>
            </div>
        </div>
    @endif

    <!-- Rejection Reason Modal -->
    @if($rejectModalOpen)
        <div class="fixed inset-0 z-[70] flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs">
            <div class="bg-surface rounded-3xl max-w-md w-full p-6 shadow-2xl border border-border-subtle space-y-4">
                <h3 class="text-base font-bold text-danger">Reject Logistics Application</h3>
                <p class="text-xs text-text-muted">Specify why this hub application is rejected. The reason will appear on the hub manager's dashboard for correction.</p>
                <div>
                    <textarea wire:model="rejectionReason" rows="4" placeholder="e.g. Invalid or expired business permit. Please upload an updated permit." class="w-full p-3 text-xs border-2 rounded-xl outline-none border-border-subtle focus:border-danger"></textarea>
                    @error('rejectionReason') <p class="text-danger text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" wire:click="$set('rejectModalOpen', false)" class="px-4 py-2 text-xs font-bold text-text-muted hover:bg-surface-subtle rounded-xl cursor-pointer">Cancel</button>
                    <button type="button" wire:click="confirmReject" class="px-4 py-2 text-xs font-bold text-white bg-danger hover:bg-red-700 rounded-xl cursor-pointer">Submit Rejection</button>
                </div>
            </div>
        </div>
    @endif
</div>