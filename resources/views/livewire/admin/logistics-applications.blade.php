@extends('layouts.admin', ['title' => 'Storkia Admin - Logistics Applications'])

@section('content')
@php
    $activeTab = $activeTab ?? request()->query('status', 'pending');
    $search = $search ?? request()->query('search', '');
    $allUserIds = $applications->pluck('user_id')->filter()->unique()->all();
    $historyByUser = !empty($allUserIds)
        ? \App\Models\LogisticsApplication::whereIn('user_id', $allUserIds)->orderByDesc('version')->get()->groupBy('user_id')
        : collect();
@endphp

<div x-data="{
        detailModalOpen: false,
        docModalOpen: false,
        rejectModalOpen: false,
        selectedApp: null,
        docModalTitle: '',
        docModalUrl: '',
        docModalType: 'image',
        rejectionReason: '',
        openApplication(appData) {
            this.selectedApp = appData;
            this.detailModalOpen = true;
        },
        openDocModal(title, url, ext) {
            this.docModalTitle = title;
            this.docModalUrl = url;
            this.docModalType = (ext === 'pdf') ? 'pdf' : 'image';
            this.docModalOpen = true;
        },
        openRejectModal() {
            this.rejectionReason = '';
            this.rejectModalOpen = true;
        }
     }"
     class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 w-full py-4 select-none space-y-6">

    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl sm:text-3xl font-bold text-primary-dark">Logistics Applications</h1>
            <p class="text-text-muted text-xs sm:text-sm mt-1">Review hub documentation, facility locations, and onboarding requests.</p>
        </div>
        <form method="GET" action="{{ route('admin.applications.logistics') }}" class="flex items-center gap-2 w-full md:w-auto">
            <input type="hidden" name="status" value="{{ $activeTab }}">
            <input type="text"
                   name="search"
                   value="{{ $search }}"
                   placeholder="Search hub name or contact..."
                   class="w-full md:w-72 py-2.5 px-3.5 text-xs bg-surface border border-border-subtle rounded-xl outline-none focus:border-primary shadow-xs">
            <button type="submit" class="px-4 py-2.5 rounded-xl bg-primary-dark hover:bg-text-main text-white text-xs font-bold cursor-pointer">
                Search
            </button>
        </form>
    </div>

    @if(session('success'))
        <div class="p-4 rounded-2xl bg-green-50 border border-green-200 text-green-700 text-xs sm:text-sm font-bold flex items-center gap-2">
            <svg class="w-5 h-5 text-green-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            <span>{{ session('success') }}</span>
        </div>
    @endif

    @if($errors->any())
        <div class="p-4 rounded-2xl bg-red-50 border border-red-200 text-red-700 text-xs sm:text-sm space-y-1">
            @foreach($errors->all() as $err)
                <p class="font-semibold">{{ $err }}</p>
            @endforeach
        </div>
    @endif

    <div class="flex border-b border-border-subtle gap-2 sm:gap-6">
        @foreach(['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected'] as $tabKey => $tabLabel)
            <a href="{{ route('admin.applications.logistics', array_filter(['status' => $tabKey, 'search' => $search])) }}"
               class="pb-3 px-2 text-sm font-bold border-b-2 transition-colors cursor-pointer flex items-center gap-2 {{ $activeTab === $tabKey ? 'border-primary text-primary' : 'border-transparent text-text-muted hover:text-text-main' }}">
                <span>{{ $tabLabel }}</span>
                <span class="text-xs px-2 py-0.5 rounded-full {{ $activeTab === $tabKey ? 'bg-primary text-white' : 'bg-surface-subtle text-text-muted' }}">
                    {{ $counts[$tabKey] ?? 0 }}
                </span>
            </a>
        @endforeach
    </div>

    <div class="bg-surface rounded-3xl border border-border-subtle shadow-xs overflow-hidden">
        <table class="w-full text-left text-xs sm:text-sm">
            <thead class="bg-surface-subtle border-b border-border-subtle text-text-muted text-xs uppercase font-bold tracking-wider">
                <tr>
                    <th class="py-3.5 px-5">Hub / Center Name</th>
                    <th class="py-3.5 px-4">Manager / Contact</th>
                    <th class="py-3.5 px-4">Location</th>
                    <th class="py-3.5 px-4 text-center">Version</th>
                    <th class="py-3.5 px-4">Submitted</th>
                    <th class="py-3.5 px-5 text-right">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-border-subtle/70">
                @forelse($applications as $app)
                    @php
                        $idExt = strtolower(pathinfo($app->id_path ?? '', PATHINFO_EXTENSION));
                        $permitExt = strtolower(pathinfo($app->permit_path ?? '', PATHINFO_EXTENSION));
                        $userHistory = ($historyByUser->get($app->user_id) ?? collect())->map(fn ($h) => [
                            'id'               => $h->id,
                            'version'          => $h->version,
                            'status'           => $h->status,
                            'created_at'       => $h->created_at?->format('M d, Y h:i A'),
                            'rejection_reason' => $h->rejection_reason,
                        ])->values()->all();

                        $appPayload = [
                            'id'               => $app->id,
                            'business_name'    => $app->business_name,
                            'version'          => $app->version,
                            'status'           => $app->status,
                            'submitted_at'     => $app->created_at?->format('M d, Y h:i A'),
                            'manager_name'     => trim(($app->user->first_name ?? '') . ' ' . ($app->user->last_name ?? '')),
                            'email'            => $app->user->email ?? '',
                            'contact_no'       => $app->contact_no,
                            'address'          => collect([$app->house_details, $app->street, $app->barangay, $app->municipality, $app->province])->filter()->implode(', '),
                            'rejection_reason' => $app->rejection_reason,
                            'id_url'           => route('admin.applications.logistics.document', [$app->id, 'id']),
                            'id_ext'           => $idExt,
                            'permit_url'       => route('admin.applications.logistics.document', [$app->id, 'permit']),
                            'permit_ext'       => $permitExt,
                            'approve_url'      => route('admin.applications.logistics.approve', $app->id),
                            'reject_url'       => route('admin.applications.logistics.reject', $app->id),
                            'history'          => $userHistory,
                        ];
                    @endphp
                    <tr class="hover:bg-brand-light/10 transition-colors">
                        <td class="py-3.5 px-5">
                            <p class="font-bold text-text-main">{{ $app->business_name }}</p>
                        </td>
                        <td class="py-3.5 px-4">
                            <p class="font-medium text-text-main">{{ $app->user?->first_name }} {{ $app->user?->last_name }}</p>
                            <p class="text-xs text-text-muted">{{ $app->contact_no }}</p>
                        </td>
                        <td class="py-3.5 px-4 text-text-muted">{{ $app->municipality }}, {{ $app->province }}</td>
                        <td class="py-3.5 px-4 text-center">
                            <span class="px-2 py-0.5 bg-surface-subtle border border-border-subtle text-text-main font-bold rounded-md text-xs">v{{ $app->version }}</span>
                        </td>
                        <td class="py-3.5 px-4 text-text-muted text-xs">{{ $app->created_at?->format('M d, Y h:i A') }}</td>
                        <td class="py-3.5 px-5 text-right">
                            <button type="button"
                                    @click="openApplication(@js($appPayload))"
                                    class="px-3.5 py-1.5 bg-primary/10 text-primary hover:bg-primary hover:text-white font-bold text-xs rounded-xl transition-colors cursor-pointer">
                                Review Details
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="p-10 text-center text-text-muted text-xs sm:text-sm">
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
    <template x-teleport="body">
        <div x-show="detailModalOpen && selectedApp"
             x-cloak
             class="fixed inset-0 z-[190] flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs overflow-y-auto">
            <div @click.away="detailModalOpen = false"
                 class="bg-surface rounded-3xl max-w-3xl w-full p-6 sm:p-8 shadow-2xl border border-border-subtle max-h-[90vh] overflow-y-auto space-y-6 my-auto">
                <div class="flex items-center justify-between border-b border-border-subtle pb-4">
                    <div>
                        <h2 class="text-xl font-bold text-primary-dark" x-text="selectedApp?.business_name"></h2>
                        <p class="text-xs text-text-muted" x-text="'Application Revision v' + (selectedApp?.version || 1) + ' • Submitted ' + (selectedApp?.submitted_at || '')"></p>
                    </div>
                    <button type="button" @click="detailModalOpen = false" class="text-text-muted hover:text-danger p-1 cursor-pointer">
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-xs bg-surface-subtle p-4 rounded-2xl border border-border-subtle">
                    <div><span class="text-text-muted">Hub Manager:</span> <strong class="text-text-main" x-text="selectedApp?.manager_name"></strong></div>
                    <div><span class="text-text-muted">Email:</span> <strong class="text-text-main font-mono" x-text="selectedApp?.email"></strong></div>
                    <div><span class="text-text-muted">Contact:</span> <strong class="text-text-main" x-text="selectedApp?.contact_no"></strong></div>
                    <div><span class="text-text-muted">Status:</span> <strong class="capitalize text-text-main" x-text="selectedApp?.status"></strong></div>
                    <div class="md:col-span-2"><span class="text-text-muted">Hub Facility Address:</span> <strong class="text-text-main" x-text="selectedApp?.address"></strong></div>
                </div>

                <div>
                    <h3 class="text-xs font-bold uppercase tracking-wider text-text-muted mb-2">Facility &amp; Identity Documents</h3>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div class="border border-border-subtle rounded-2xl p-3.5 flex items-center justify-between bg-surface">
                            <span class="text-xs font-bold text-text-main truncate">Valid Government ID</span>
                            <button type="button"
                                    @click="openDocModal('Valid Government ID — ' + selectedApp.business_name, selectedApp.id_url, selectedApp.id_ext)"
                                    class="px-3 py-1.5 rounded-xl bg-primary/10 text-primary hover:bg-primary hover:text-white text-xs font-bold transition-colors cursor-pointer">
                                Inspect Document
                            </button>
                        </div>
                        <div class="border border-border-subtle rounded-2xl p-3.5 flex items-center justify-between bg-surface">
                            <span class="text-xs font-bold text-text-main truncate">Hub Business Permit</span>
                            <button type="button"
                                    @click="openDocModal('Hub Business Permit — ' + selectedApp.business_name, selectedApp.permit_url, selectedApp.permit_ext)"
                                    class="px-3 py-1.5 rounded-xl bg-primary/10 text-primary hover:bg-primary hover:text-white text-xs font-bold transition-colors cursor-pointer">
                                Inspect Document
                            </button>
                        </div>
                    </div>
                </div>

                <template x-if="selectedApp?.history && selectedApp.history.length > 1">
                    <div class="border-t border-border-subtle pt-4">
                        <h3 class="text-xs font-bold uppercase tracking-wider text-text-muted mb-3" x-text="'Submission History & Revisions (' + selectedApp.history.length + ')'"></h3>
                        <div class="space-y-2 max-h-40 overflow-y-auto">
                            <template x-for="past in selectedApp.history" :key="past.id">
                                <div class="p-3 rounded-xl border border-border-subtle text-xs flex items-center justify-between bg-surface-subtle">
                                    <div>
                                        <span class="font-bold" x-text="'v' + past.version"></span>
                                        <span class="ml-2 font-semibold capitalize" x-text="past.status"></span>
                                        <span class="text-text-muted text-[10px] ml-2" x-text="past.created_at"></span>
                                        <template x-if="past.rejection_reason">
                                            <p class="text-[11px] text-danger mt-1 italic font-medium" x-text="'\"' + past.rejection_reason + '\"'"></p>
                                        </template>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>
                </template>

                <div class="flex justify-end gap-3 border-t border-border-subtle pt-4">
                    <button type="button" @click="detailModalOpen = false" class="px-4 py-2 text-xs font-bold text-text-muted hover:bg-surface-subtle rounded-xl cursor-pointer">
                        Close
                    </button>
                    <template x-if="selectedApp?.status === 'pending'">
                        <div class="flex items-center gap-2.5">
                            <button type="button"
                                    @click="openRejectModal()"
                                    class="px-5 py-2.5 text-xs font-bold text-white bg-danger hover:bg-red-700 rounded-xl transition-colors cursor-pointer">
                                Reject Application
                            </button>
                            <form :action="selectedApp?.approve_url" method="POST" class="m-0">
                                @csrf
                                @method('PATCH')
                                <button type="submit" class="px-5 py-2.5 text-xs font-bold text-white bg-green-600 hover:bg-green-700 rounded-xl transition-colors cursor-pointer">
                                    Approve Application
                                </button>
                            </form>
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </template>

    <!-- Document Inspection Modal -->
    <template x-teleport="body">
        <div x-show="docModalOpen"
             x-cloak
             class="fixed inset-0 z-[205] flex items-center justify-center p-4 bg-black/75 backdrop-blur-xs">
            <div @click.away="docModalOpen = false"
                 class="bg-surface rounded-3xl max-w-4xl w-full p-4 sm:p-6 shadow-2xl border border-border-subtle flex flex-col h-[85vh]">
                <div class="flex items-center justify-between pb-3 border-b border-border-subtle">
                    <h3 class="font-bold text-sm text-primary-dark truncate" x-text="docModalTitle"></h3>
                    <div class="flex items-center gap-3">
                        <a :href="docModalUrl" target="_blank" class="text-xs font-bold text-primary hover:underline">Open in New Tab &nearr;</a>
                        <button type="button" @click="docModalOpen = false" class="p-1 rounded-lg hover:bg-surface-subtle text-text-muted hover:text-text-main cursor-pointer">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>
                </div>
                <div class="flex-1 overflow-auto p-2 flex items-center justify-center bg-surface-subtle rounded-2xl mt-3">
                    <template x-if="docModalType === 'image'">
                        <img :src="docModalUrl" alt="Document" class="max-h-full max-w-full object-contain rounded-lg shadow-sm">
                    </template>
                    <template x-if="docModalType === 'pdf'">
                        <iframe :src="docModalUrl" class="w-full h-full rounded-lg border-0"></iframe>
                    </template>
                </div>
            </div>
        </div>
    </template>

    <!-- Reject Reason Modal -->
    <template x-teleport="body">
        <div x-show="rejectModalOpen && selectedApp"
             x-cloak
             class="fixed inset-0 z-[210] flex items-center justify-center p-4 bg-black/65 backdrop-blur-xs">
            <div @click.away="rejectModalOpen = false"
                 class="bg-surface rounded-3xl max-w-md w-full p-6 shadow-2xl border border-border-subtle space-y-4">
                <h3 class="text-base font-bold text-danger">Reject Logistics Application</h3>
                <p class="text-xs text-text-muted">Specify why this hub application is rejected. The reason will appear on the hub manager's dashboard for correction.</p>
                <form :action="selectedApp?.reject_url" method="POST" class="space-y-4 text-xs">
                    @csrf
                    @method('PATCH')
                    <textarea name="rejection_reason"
                              x-model="rejectionReason"
                              rows="4"
                              required
                              minlength="5"
                              placeholder="e.g. Invalid or expired business permit. Please upload an updated permit."
                              class="w-full p-3 border border-border-subtle rounded-xl outline-none focus:border-danger"></textarea>
                    <div class="flex justify-end gap-2">
                        <button type="button" @click="rejectModalOpen = false" class="px-4 py-2 font-bold text-text-muted cursor-pointer">Cancel</button>
                        <button type="submit" class="px-5 py-2 font-bold text-white bg-danger hover:bg-red-700 rounded-xl cursor-pointer">Submit Rejection</button>
                    </div>
                </form>
            </div>
        </div>
    </template>
</div>
@endsection