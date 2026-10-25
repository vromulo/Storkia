@extends('layouts.logistics', ['title' => 'Storkia - Rider Applications'])

@section('content')
<div x-data="{
    docModalOpen: false,
    docModalTitle: '',
    docModalUrl: '',
    docModalType: 'image',
    openDocPreview(title, url, ext) {
        this.docModalTitle = title;
        this.docModalUrl = url;
        this.docModalType = (ext === 'pdf') ? 'pdf' : 'image';
        this.docModalOpen = true;
    }
}" class="max-w-7xl mx-auto select-none space-y-6">

    <div class="flex flex-col md:flex-row md:items-end justify-between gap-4">
        <div>
            <div class="flex items-center gap-2.5">
                <h1 class="text-3xl font-bold text-text-main">Rider Applications</h1>
                <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-bold bg-brand-light/60 text-primary-dark border border-border-subtle">
                    {{ $profile->business_name }}
                </span>
            </div>
            <p class="text-sm text-text-muted mt-1">
                Review, verify documents, and manage delivery rider applicants for your logistics hub.
            </p>
        </div>

        <form method="GET" action="{{ route('logistics.rider-applications.index') }}" class="flex items-center gap-2 w-full md:w-auto">
            <input type="hidden" name="status" value="{{ $status }}">
            <div class="relative flex-1 md:w-72">
                <input 
                    type="text" 
                    name="search" 
                    value="{{ $search }}" 
                    placeholder="Search name, email, plate, license..." 
                    class="w-full pl-9 pr-4 py-2.5 bg-surface border border-border-subtle rounded-2xl text-xs text-text-main outline-none focus:border-primary transition-colors"
                >
                <svg class="w-4 h-4 text-text-muted absolute left-3 top-1/2 -translate-y-1/2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
            </div>
            <button type="submit" class="px-4 py-2.5 bg-primary-dark hover:bg-text-main text-surface text-xs font-semibold rounded-2xl transition-colors cursor-pointer">
                Search
            </button>
        </form>
    </div>

    @if (session('success'))
        <div class="p-4 rounded-2xl bg-success/10 border border-success/20 text-success text-xs font-bold flex items-center justify-between">
            <span>{{ session('success') }}</span>
        </div>
    @endif

    <div class="flex flex-wrap items-center gap-2">
        @php
            $tabs = [
                'all' => ['label' => 'All Applicants', 'count' => $counts['all']],
                'pending' => ['label' => 'Pending Review', 'count' => $counts['pending']],
                'approved' => ['label' => 'Approved', 'count' => $counts['approved']],
                'rejected' => ['label' => 'Rejected', 'count' => $counts['rejected']],
            ];
        @endphp

        @foreach ($tabs as $key => $tab)
            <a href="{{ route('logistics.rider-applications.index', ['status' => $key, 'search' => $search]) }}"
               class="inline-flex items-center gap-2 px-4 py-2 rounded-2xl text-xs font-bold border transition-all cursor-pointer {{ $status === $key ? 'bg-primary-dark text-surface border-primary-dark shadow-xs' : 'bg-surface text-text-muted border-border-subtle hover:text-text-main hover:bg-surface-subtle' }}">
                <span>{{ $tab['label'] }}</span>
                <span class="px-2 py-0.5 rounded-full text-[10px] {{ $status === $key ? 'bg-surface/20 text-surface' : 'bg-surface-subtle text-text-main' }}">
                    {{ $tab['count'] }}
                </span>
            </a>
        @endforeach
    </div>

    <div class="bg-surface rounded-3xl border border-border-subtle shadow-xs overflow-hidden">
        @if ($applicants->isEmpty())
            <div class="py-16 px-6 text-center">
                <div class="w-12 h-12 rounded-2xl bg-surface-subtle text-text-muted flex items-center justify-center mx-auto mb-3">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                    </svg>
                </div>
                <h3 class="text-base font-bold text-text-main">No rider applications found</h3>
                <p class="text-xs text-text-muted mt-1">Applicants who select {{ $profile->business_name }} during registration will appear here.</p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="border-b border-border-subtle bg-surface-subtle/60 text-[11px] uppercase tracking-wider font-bold text-text-muted">
                            <th class="py-3.5 px-5">Applicant</th>
                            <th class="py-3.5 px-4">License & Vehicle</th>
                            <th class="py-3.5 px-4">Documents</th>
                            <th class="py-3.5 px-4">Status</th>
                            <th class="py-3.5 px-5 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border-subtle text-xs">
                        @foreach ($applicants as $rider)
                            @php
                                $licenseExt = strtolower(pathinfo($rider->license_path ?? '', PATHINFO_EXTENSION));
                                $orCrExt = strtolower(pathinfo($rider->or_cr_path ?? '', PATHINFO_EXTENSION));
                            @endphp
                            <tr class="hover:bg-surface-subtle/40 transition-colors">
                                <td class="py-4 px-5 align-top">
                                    <div class="font-bold text-sm text-text-main">{{ $rider->full_name }}</div>
                                    <div class="text-text-muted mt-0.5">{{ $rider->email }}</div>
                                    <div class="text-text-muted mt-0.5">{{ $rider->contact_no }}</div>
                                    <div class="text-[11px] text-text-muted/80 mt-1">
                                        DOB: {{ $rider->birthday?->format('M d, Y') }} ({{ $rider->birthday?->age }} yrs old)
                                    </div>
                                </td>

                                <td class="py-4 px-4 align-top space-y-1">
                                    <div>
                                        <span class="text-text-muted">Vehicle:</span>
                                        <span class="font-bold text-text-main">{{ $rider->vehicle_type }}</span>
                                    </div>
                                    <div>
                                        <span class="text-text-muted">Plate No.:</span>
                                        <span class="font-mono font-bold text-primary-dark bg-brand-light/30 px-1.5 py-0.5 rounded">{{ $rider->plate_number }}</span>
                                    </div>
                                    <div>
                                        <span class="text-text-muted">License No.:</span>
                                        <span class="font-mono font-semibold text-text-main">{{ $rider->license_no }}</span>
                                    </div>
                                    <div class="text-[11px] text-text-muted">
                                        Expires: {{ $rider->license_expiry_date?->format('M d, Y') }}
                                    </div>
                                </td>

                                <td class="py-4 px-4 align-top">
                                    <div class="flex flex-col gap-2 items-start">
                                        <button 
                                            type="button"
                                            @click="openDocPreview('Driver\'s License - {{ addslashes($rider->full_name) }}', '{{ route('logistics.rider-applications.document', [$rider->id, 'license']) }}', '{{ $licenseExt }}')"
                                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-surface-subtle hover:bg-brand-light/40 text-text-main font-semibold border border-border-subtle transition-colors cursor-pointer"
                                        >
                                            <svg class="w-3.5 h-3.5 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                            </svg>
                                            Driver's License
                                        </button>

                                        <button 
                                            type="button"
                                            @click="openDocPreview('Vehicle OR/CR - {{ addslashes($rider->full_name) }}', '{{ route('logistics.rider-applications.document', [$rider->id, 'or_cr']) }}', '{{ $orCrExt }}')"
                                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-surface-subtle hover:bg-brand-light/40 text-text-main font-semibold border border-border-subtle transition-colors cursor-pointer"
                                        >
                                            <svg class="w-3.5 h-3.5 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                            </svg>
                                            Vehicle OR/CR
                                        </button>
                                    </div>
                                </td>

                                <td class="py-4 px-4 align-top">
                                    @if ($rider->status === 'approved')
                                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-bold bg-success/10 text-success border border-success/20">
                                            <span class="w-1.5 h-1.5 rounded-full bg-success"></span>
                                            Approved
                                        </span>
                                    @elseif ($rider->status === 'rejected')
                                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-bold bg-danger/10 text-danger border border-danger/20">
                                            <span class="w-1.5 h-1.5 rounded-full bg-danger"></span>
                                            Rejected
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-bold bg-warning/15 text-warning border border-warning/30">
                                            <span class="w-1.5 h-1.5 rounded-full bg-warning"></span>
                                            Pending
                                        </span>
                                    @endif
                                    <div class="text-[10px] text-text-muted mt-1">
                                        Applied {{ $rider->created_at?->diffForHumans() }}
                                    </div>
                                </td>

                                <td class="py-4 px-5 align-top text-right">
                                    <div class="flex flex-wrap items-center justify-end gap-2">
                                        <button 
                                            type="button" 
                                            disabled
                                            title="Interview scheduling coming soon"
                                            class="px-3 py-1.5 rounded-xl border border-border-subtle bg-surface-subtle text-text-muted text-xs font-semibold opacity-50 cursor-not-allowed pointer-events-none select-none"
                                        >
                                            Schedule Interview
                                        </button>

                                        @if ($rider->status !== 'approved')
                                            <form method="POST" action="{{ route('logistics.rider-applications.approve', $rider->id) }}" class="m-0">
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit" class="px-3.5 py-1.5 rounded-xl bg-success hover:bg-success/90 text-surface text-xs font-bold shadow-xs transition-all cursor-pointer active:scale-95">
                                                    Accept
                                                </button>
                                            </form>
                                        @endif

                                        @if ($rider->status !== 'rejected')
                                            <form method="POST" action="{{ route('logistics.rider-applications.reject', $rider->id) }}" class="m-0">
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit" class="px-3.5 py-1.5 rounded-xl bg-danger hover:bg-danger/90 text-surface text-xs font-bold shadow-xs transition-all cursor-pointer active:scale-95">
                                                    Reject
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($applicants->hasPages())
                <div class="px-5 py-4 border-t border-border-subtle">
                    {{ $applicants->links() }}
                </div>
            @endif
        @endif
    </div>

    <template x-teleport="body">
        <div x-show="docModalOpen"
             x-cloak
             x-transition.opacity
             class="fixed inset-0 z-[190] flex items-center justify-center p-4 bg-black/70 backdrop-blur-xs">
            <div @click.away="docModalOpen = false"
                 class="bg-surface rounded-3xl max-w-4xl w-full p-4 sm:p-6 shadow-2xl border border-border-subtle flex flex-col h-[82vh]">
                <div class="flex items-center justify-between pb-3 border-b border-border-subtle">
                    <div class="flex items-center gap-2">
                        <h3 class="font-bold text-sm text-primary-dark truncate" x-text="docModalTitle"></h3>
                    </div>
                    <div class="flex items-center gap-3">
                        <a :href="docModalUrl" target="_blank" class="text-xs font-bold text-primary hover:underline">Open in New Tab</a>
                        <button type="button" @click="docModalOpen = false" class="p-1 rounded-lg hover:bg-surface-subtle text-text-muted hover:text-text-main cursor-pointer">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>
                </div>

                <div class="flex-1 overflow-auto p-2 flex items-center justify-center bg-surface-subtle rounded-2xl mt-3">
                    <template x-if="docModalType === 'image'">
                        <img :src="docModalUrl" alt="Applicant Document" class="max-h-full max-w-full object-contain rounded-lg shadow-sm">
                    </template>
                    <template x-if="docModalType === 'pdf'">
                        <iframe :src="docModalUrl" class="w-full h-full rounded-lg border-0"></iframe>
                    </template>
                </div>
            </div>
        </div>
    </template>
</div>
@endsection