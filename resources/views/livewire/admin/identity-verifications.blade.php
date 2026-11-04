@extends('layouts.admin', ['title' => 'Storkia Admin - Buyer Identity Verifications'])

@section('content')
@php
    $activeTab = $activeTab ?? request()->query('status', 'pending');
    $search = $search ?? request()->query('search', '');
@endphp

<div x-data="{
        detailModalOpen: false,
        docModalOpen: false,
        rejectModalOpen: false,
        selectedItem: null,
        docModalTitle: '',
        docModalUrl: '',
        rejectionReason: '',
        openReviewModal(item) {
            this.selectedItem = item;
            this.detailModalOpen = true;
        },
        openDocViewer(title, url) {
            this.docModalTitle = title;
            this.docModalUrl = url;
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
            <h1 class="text-2xl sm:text-3xl font-bold text-primary-dark">Buyer Identity Verifications</h1>
            <p class="text-text-muted text-xs sm:text-sm mt-1">Review government ID proofs and verify customer identities.</p>
        </div>
        <form method="GET" action="{{ route('admin.applications.identity') }}" class="flex items-center gap-2 w-full md:w-auto">
            <input type="hidden" name="status" value="{{ $activeTab }}">
            <input type="text"
                   name="search"
                   value="{{ $search }}"
                   placeholder="Search user by name or email..."
                   class="w-full md:w-72 py-2.5 px-3.5 text-xs bg-surface border border-border-subtle rounded-xl outline-none focus:border-primary shadow-xs">
            <button type="submit" class="px-4 py-2.5 rounded-xl bg-primary-dark hover:bg-text-main text-white text-xs font-bold cursor-pointer">
                Search
            </button>
        </form>
    </div>

    @if(session('success'))
        <div class="p-4 rounded-2xl bg-green-50 border border-green-200 text-green-700 text-xs sm:text-sm font-bold">
            {{ session('success') }}
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
        @foreach (['pending' => 'Pending', 'verified' => 'Verified', 'failed' => 'Failed'] as $tab => $label)
            <a href="{{ route('admin.applications.identity', array_filter(['status' => $tab, 'search' => $search])) }}"
               class="pb-3 px-2 text-sm font-bold border-b-2 transition-colors cursor-pointer flex items-center gap-2 {{ $activeTab === $tab ? 'border-primary text-primary' : 'border-transparent text-text-muted hover:text-text-main' }}">
                <span>{{ $label }}</span>
                <span class="text-xs px-2 py-0.5 rounded-full {{ $activeTab === $tab ? 'bg-primary text-white' : 'bg-surface-subtle text-text-muted' }}">
                    {{ $counts[$tab] ?? 0 }}
                </span>
            </a>
        @endforeach
    </div>

    <div class="bg-surface rounded-3xl border border-border-subtle shadow-xs overflow-hidden">
        <table class="w-full text-left text-xs sm:text-sm">
            <thead class="bg-surface-subtle border-b border-border-subtle text-text-muted text-xs uppercase font-bold tracking-wider">
                <tr>
                    <th class="py-3.5 px-5">User</th>
                    <th class="py-3.5 px-4">Document Type</th>
                    <th class="py-3.5 px-4">Capture Method</th>
                    <th class="py-3.5 px-4">Date Submitted</th>
                    <th class="py-3.5 px-5 text-right">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-border-subtle/70">
                @forelse($verifications as $v)
                    @php
                        $payload = [
                            'id'               => $v->id,
                            'full_name'        => trim(($v->user->first_name ?? '') . ' ' . ($v->user->last_name ?? '')),
                            'email'            => $v->user->email ?? '',
                            'id_type'          => ucwords(str_replace('_', ' ', (string) $v->id_type)),
                            'method'           => ucfirst((string) $v->method),
                            'status'           => $v->status,
                            'rejection_reason' => $v->rejection_reason,
                            'submitted_at'     => $v->created_at?->format('M d, Y h:i A'),
                            'front_url'        => route('admin.applications.identity.document', [$v->id, 'front']),
                            'back_url'         => $v->back_image_path ? route('admin.applications.identity.document', [$v->id, 'back']) : null,
                            'approve_url'      => route('admin.applications.identity.approve', $v->id),
                            'reject_url'       => route('admin.applications.identity.reject', $v->id),
                        ];
                    @endphp
                    <tr class="hover:bg-brand-light/10 transition-colors">
                        <td class="py-3.5 px-5">
                            <p class="font-bold text-text-main">{{ $v->user?->first_name }} {{ $v->user?->last_name }}</p>
                            <p class="text-xs text-text-muted font-mono">{{ $v->user?->email }}</p>
                        </td>
                        <td class="py-3.5 px-4 capitalize font-semibold">{{ str_replace('_', ' ', $v->id_type) }}</td>
                        <td class="py-3.5 px-4 capitalize text-text-muted">{{ $v->method }}</td>
                        <td class="py-3.5 px-4 text-xs text-text-muted">{{ $v->created_at?->format('M d, Y h:i A') }}</td>
                        <td class="py-3.5 px-5 text-right">
                            <button type="button"
                                    @click="openReviewModal(@js($payload))"
                                    class="px-3.5 py-1.5 bg-primary/10 text-primary hover:bg-primary hover:text-white font-bold text-xs rounded-xl transition-colors cursor-pointer">
                                Review ID
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="p-10 text-center text-text-muted text-xs sm:text-sm">
                            No {{ $activeTab }} identity verifications found.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $verifications->links() }}</div>

    <!-- Review Modal -->
    <template x-teleport="body">
        <div x-show="detailModalOpen && selectedItem"
             x-cloak
             class="fixed inset-0 z-[190] flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs">
            <div @click.away="detailModalOpen = false"
                 class="bg-surface rounded-3xl max-w-2xl w-full p-6 sm:p-8 shadow-2xl border border-border-subtle space-y-5">
                <div class="flex items-center justify-between border-b border-border-subtle pb-3">
                    <div>
                        <h3 class="font-bold text-base sm:text-lg text-primary-dark" x-text="'Identity Verification — ' + (selectedItem?.full_name || '')"></h3>
                        <p class="text-xs text-text-muted" x-text="'Submitted ' + (selectedItem?.submitted_at || '')"></p>
                    </div>
                    <button type="button" @click="detailModalOpen = false" class="text-text-muted hover:text-danger text-lg font-bold cursor-pointer">&times;</button>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 text-xs bg-surface-subtle p-4 rounded-2xl border border-border-subtle">
                    <div><span class="text-text-muted block">Email:</span> <strong class="text-text-main font-mono" x-text="selectedItem?.email"></strong></div>
                    <div><span class="text-text-muted block">Document Type:</span> <strong class="text-text-main" x-text="selectedItem?.id_type"></strong></div>
                    <div><span class="text-text-muted block">Capture Method:</span> <strong class="text-text-main" x-text="selectedItem?.method"></strong></div>
                </div>

                <template x-if="selectedItem?.rejection_reason">
                    <div class="p-3.5 rounded-2xl bg-red-50 border border-red-200 text-xs text-danger font-semibold">
                        Previous Rejection Reason: "<span x-text="selectedItem.rejection_reason"></span>"
                    </div>
                </template>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-1">
                    <div class="border border-border-subtle rounded-2xl p-4 flex items-center justify-between bg-surface">
                        <span class="text-xs font-bold text-text-main">Front ID Image</span>
                        <button type="button"
                                @click="openDocViewer('Front ID — ' + selectedItem.full_name, selectedItem.front_url)"
                                class="px-3 py-1.5 rounded-xl bg-primary/10 text-primary hover:bg-primary hover:text-white text-xs font-bold transition-colors cursor-pointer">
                            Inspect Photo
                        </button>
                    </div>
                    <template x-if="selectedItem?.back_url">
                        <div class="border border-border-subtle rounded-2xl p-4 flex items-center justify-between bg-surface">
                            <span class="text-xs font-bold text-text-main">Back ID Image</span>
                            <button type="button"
                                    @click="openDocViewer('Back ID — ' + selectedItem.full_name, selectedItem.back_url)"
                                    class="px-3 py-1.5 rounded-xl bg-primary/10 text-primary hover:bg-primary hover:text-white text-xs font-bold transition-colors cursor-pointer">
                                Inspect Photo
                            </button>
                        </div>
                    </template>
                </div>

                <div class="flex justify-end gap-3 pt-4 border-t border-border-subtle">
                    <button type="button" @click="detailModalOpen = false" class="px-4 py-2 text-xs font-bold text-text-muted hover:bg-surface-subtle rounded-xl cursor-pointer">
                        Close
                    </button>
                    <template x-if="selectedItem?.status === 'pending'">
                        <div class="flex items-center gap-2.5">
                            <button type="button"
                                    @click="openRejectModal()"
                                    class="px-5 py-2.5 text-xs font-bold text-white bg-danger hover:bg-red-700 rounded-xl cursor-pointer">
                                Reject / Fail
                            </button>
                            <form :action="selectedItem?.approve_url" method="POST" class="m-0">
                                @csrf
                                @method('PATCH')
                                <button type="submit" class="px-5 py-2.5 text-xs font-bold text-white bg-green-600 hover:bg-green-700 rounded-xl cursor-pointer">
                                    Approve Identity
                                </button>
                            </form>
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </template>

    <!-- Document Viewer Modal -->
    <template x-teleport="body">
        <div x-show="docModalOpen"
             x-cloak
             class="fixed inset-0 z-[205] flex items-center justify-center p-4 bg-black/75 backdrop-blur-xs">
            <div @click.away="docModalOpen = false"
                 class="bg-surface rounded-3xl max-w-3xl w-full p-5 flex flex-col h-[82vh] border border-border-subtle shadow-2xl">
                <div class="flex justify-between items-center pb-3 border-b border-border-subtle">
                    <h4 class="font-bold text-sm text-primary-dark" x-text="docModalTitle"></h4>
                    <div class="flex items-center gap-3">
                        <a :href="docModalUrl" target="_blank" class="text-xs font-bold text-primary hover:underline">Open in New Tab &nearr;</a>
                        <button type="button" @click="docModalOpen = false" class="text-text-muted hover:text-danger text-lg font-bold cursor-pointer">&times;</button>
                    </div>
                </div>
                <div class="flex-1 flex items-center justify-center p-3 overflow-auto bg-surface-subtle rounded-2xl mt-3">
                    <img :src="docModalUrl" class="max-h-full max-w-full object-contain rounded-xl shadow-sm">
                </div>
            </div>
        </div>
    </template>

    <!-- Reject Modal -->
    <template x-teleport="body">
        <div x-show="rejectModalOpen && selectedItem"
             x-cloak
             class="fixed inset-0 z-[210] flex items-center justify-center p-4 bg-black/65 backdrop-blur-xs">
            <div @click.away="rejectModalOpen = false"
                 class="bg-surface rounded-3xl max-w-md w-full p-6 shadow-2xl border border-border-subtle space-y-4">
                <h3 class="text-base font-bold text-danger">Fail Identity Verification</h3>
                <form :action="selectedItem?.reject_url" method="POST" class="space-y-4 text-xs">
                    @csrf
                    @method('PATCH')
                    <textarea name="rejection_reason"
                              x-model="rejectionReason"
                              rows="3"
                              required
                              minlength="5"
                              placeholder="State reason (e.g., Image is too blurry to read name)..."
                              class="w-full p-3 border border-border-subtle rounded-xl outline-none focus:border-danger"></textarea>
                    <div class="flex justify-end gap-2">
                        <button type="button" @click="rejectModalOpen = false" class="px-4 py-2 font-bold text-text-muted cursor-pointer">Cancel</button>
                        <button type="submit" class="px-5 py-2 font-bold text-white bg-danger hover:bg-red-700 rounded-xl cursor-pointer">Confirm Failure</button>
                    </div>
                </form>
            </div>
        </div>
    </template>
</div>
@endsection