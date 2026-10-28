@extends('layouts.logistics', ['title' => 'Storkia - All Couriers'])

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

    <div class="flex flex-col lg:flex-row lg:items-end justify-between gap-4">
        <div>
            <div class="flex items-center gap-2.5">
                <h1 class="text-3xl font-bold text-text-main">All Couriers</h1>
                <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-bold bg-brand-light/60 text-primary-dark border border-border-subtle">
                    {{ $profile->business_name }}
                </span>
            </div>
            <p class="text-sm text-text-muted mt-1">
                Manage registered linehaul couriers, assigned hubs, vehicles, and verification documents.
            </p>
        </div>

        <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2.5 w-full lg:w-auto">
            <form method="GET" action="{{ route('logistics.couriers.index') }}" class="flex items-center gap-2 flex-1 sm:w-auto">
                <input type="hidden" name="status" value="{{ $status }}">
                <div class="relative flex-1 sm:w-72">
                    <input 
                        type="text" 
                        name="search" 
                        value="{{ $search }}" 
                        placeholder="Search name, email, plate, license, hub..." 
                        class="w-full pl-9 pr-4 py-2.5 bg-surface border border-border-subtle rounded-2xl text-xs text-text-main outline-none focus:border-primary transition-colors"
                    >
                    <svg class="w-4 h-4 text-text-muted absolute left-3 top-1/2 -translate-y-1/2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                </div>
                <button type="submit" class="px-4 py-2.5 bg-surface border border-border-subtle hover:border-primary text-text-main text-xs font-semibold rounded-2xl transition-colors cursor-pointer">
                    Search
                </button>
            </form>

            <a href="{{ route('logistics.couriers.create') }}"
               class="inline-flex items-center justify-center gap-2 px-5 py-2.5 bg-primary-dark hover:bg-text-main text-surface text-xs font-bold rounded-2xl shadow-xs transition-all cursor-pointer active:scale-95 shrink-0">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4" />
                </svg>
                Add Courier
            </a>
        </div>
    </div>

    @if (session('success'))
        <div class="p-4 rounded-2xl bg-success/10 border border-success/20 text-success text-xs font-bold flex items-center justify-between">
            <span>{{ session('success') }}</span>
        </div>
    @endif

    <div class="flex flex-wrap items-center gap-2">
        @php
            $tabs = [
                'all' => ['label' => 'All Couriers', 'count' => $counts['all']],
                'active' => ['label' => 'Active', 'count' => $counts['active']],
                'on_delivery' => ['label' => 'On Delivery', 'count' => $counts['on_delivery']],
                'off_duty' => ['label' => 'Off Duty', 'count' => $counts['off_duty']],
                'suspended' => ['label' => 'Suspended', 'count' => $counts['suspended']],
            ];
        @endphp

        @foreach ($tabs as $key => $tab)
            <a href="{{ route('logistics.couriers.index', ['status' => $key, 'search' => $search]) }}"
               class="inline-flex items-center gap-2 px-4 py-2 rounded-2xl text-xs font-bold border transition-all cursor-pointer {{ $status === $key ? 'bg-primary-dark text-surface border-primary-dark shadow-xs' : 'bg-surface text-text-muted border-border-subtle hover:text-text-main hover:bg-surface-subtle' }}">
                <span>{{ $tab['label'] }}</span>
                <span class="px-2 py-0.5 rounded-full text-[10px] {{ $status === $key ? 'bg-surface/20 text-surface' : 'bg-surface-subtle text-text-main' }}">
                    {{ $tab['count'] }}
                </span>
            </a>
        @endforeach
    </div>

    <div class="bg-surface rounded-3xl border border-border-subtle shadow-xs overflow-hidden">
        @if ($couriers->isEmpty())
            <div class="py-16 px-6 text-center">
                <div class="w-12 h-12 rounded-2xl bg-surface-subtle text-text-muted flex items-center justify-center mx-auto mb-3">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1m-6-1a1 1 0 001 1h1M5 17a2 2 0 104 0m-4 0a2 2 0 114 0m6 0a2 2 0 104 0m-4 0a2 2 0 114 0" />
                    </svg>
                </div>
                <h3 class="text-base font-bold text-text-main">No couriers found</h3>
                <p class="text-xs text-text-muted mt-1 mb-5">Couriers added by {{ $profile->business_name }} will appear here.</p>
                <a href="{{ route('logistics.couriers.create') }}"
                   class="inline-flex items-center gap-2 px-5 py-2.5 bg-primary-dark hover:bg-text-main text-surface text-xs font-bold rounded-2xl shadow-xs transition-all cursor-pointer">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4" />
                    </svg>
                    Add Your First Courier
                </a>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="border-b border-border-subtle bg-surface-subtle/60 text-[11px] uppercase tracking-wider font-bold text-text-muted">
                            <th class="py-3.5 px-5">Courier</th>
                            <th class="py-3.5 px-4">License & Vehicle</th>
                            <th class="py-3.5 px-4">Documents</th>
                            <th class="py-3.5 px-4">Status</th>
                            <th class="py-3.5 px-5 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border-subtle text-xs">
                        @foreach ($couriers as $courier)
                            @php
                                $licenseExt = strtolower(pathinfo($courier->license_path ?? '', PATHINFO_EXTENSION));
                                $orCrExt = strtolower(pathinfo($courier->or_cr_path ?? '', PATHINFO_EXTENSION));
                                $nbiExt = strtolower(pathinfo($courier->nbi_clearance_path ?? '', PATHINFO_EXTENSION));
                            @endphp
                            <tr class="hover:bg-surface-subtle/40 transition-colors">
                                <td class="py-4 px-5 align-top">
                                    <div class="font-bold text-sm text-text-main">{{ $courier->full_name }}</div>
                                    <div class="text-text-muted mt-0.5">{{ $courier->email }}</div>
                                    <div class="text-text-muted mt-0.5">{{ $courier->contact_no }}</div>
                                    <div class="text-[11px] text-text-muted/80 mt-1">
                                        DOB: {{ $courier->birthday?->format('M d, Y') }} ({{ $courier->birthday?->age }} yrs old)
                                    </div>
                                    <div class="mt-1.5">
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-lg text-[10px] font-bold bg-brand-light/40 text-primary-dark border border-border-subtle">
                                            Home Hub: {{ $courier->home_hub }}
                                        </span>
                                    </div>
                                </td>

                                <td class="py-4 px-4 align-top space-y-1">
                                    <div>
                                        <span class="text-text-muted">Vehicle:</span>
                                        <span class="font-bold text-text-main">{{ $courier->vehicle_type }}</span>
                                    </div>
                                    <div>
                                        <span class="text-text-muted">Load Capacity:</span>
                                        <span class="font-semibold text-text-main">{{ number_format($courier->load_capacity) }} kg</span>
                                    </div>
                                    <div>
                                        <span class="text-text-muted">Plate No.:</span>
                                        <span class="font-mono font-bold text-primary-dark bg-brand-light/30 px-1.5 py-0.5 rounded">{{ $courier->plate_number }}</span>
                                    </div>
                                    <div>
                                        <span class="text-text-muted">License No.:</span>
                                        <span class="font-mono font-semibold text-text-main">{{ $courier->license_no }}</span>
                                        <span class="text-[10px] font-bold text-primary-dark">({{ $courier->license_type }})</span>
                                    </div>
                                    <div>
                                        <span class="text-text-muted">DL Codes:</span>
                                        <span class="font-mono font-bold text-text-main">{{ $courier->formatted_dl_codes }}</span>
                                    </div>
                                    <div class="text-[11px] text-text-muted">
                                        Expires: {{ $courier->license_expiry_date?->format('M d, Y') }}
                                    </div>
                                </td>

                                <td class="py-4 px-4 align-top">
                                    <div class="flex flex-col gap-2 items-start">
                                        <button 
                                            type="button"
                                            @click="openDocPreview('Professional Driver\'s License - {{ addslashes($courier->full_name) }}', '{{ route('logistics.couriers.document', [$courier->id, 'license']) }}', '{{ $licenseExt }}')"
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
                                            @click="openDocPreview('Vehicle OR/CR - {{ addslashes($courier->full_name) }}', '{{ route('logistics.couriers.document', [$courier->id, 'or_cr']) }}', '{{ $orCrExt }}')"
                                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-surface-subtle hover:bg-brand-light/40 text-text-main font-semibold border border-border-subtle transition-colors cursor-pointer"
                                        >
                                            <svg class="w-3.5 h-3.5 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                            </svg>
                                            Vehicle OR/CR
                                        </button>

                                        <button 
                                            type="button"
                                            @click="openDocPreview('NBI Clearance - {{ addslashes($courier->full_name) }}', '{{ route('logistics.couriers.document', [$courier->id, 'nbi']) }}', '{{ $nbiExt }}')"
                                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-surface-subtle hover:bg-brand-light/40 text-text-main font-semibold border border-border-subtle transition-colors cursor-pointer"
                                        >
                                            <svg class="w-3.5 h-3.5 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                                            </svg>
                                            NBI Clearance
                                        </button>
                                    </div>
                                </td>

                                <td class="py-4 px-4 align-top">
                                    @if ($courier->status === 'active')
                                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-bold bg-success/10 text-success border border-success/20">
                                            <span class="w-1.5 h-1.5 rounded-full bg-success"></span>
                                            Active
                                        </span>
                                    @elseif ($courier->status === 'on_delivery')
                                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-bold bg-primary/10 text-primary border border-primary/20">
                                            <span class="w-1.5 h-1.5 rounded-full bg-primary"></span>
                                            On Delivery
                                        </span>
                                    @elseif ($courier->status === 'suspended')
                                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-bold bg-danger/10 text-danger border border-danger/20">
                                            <span class="w-1.5 h-1.5 rounded-full bg-danger"></span>
                                            Suspended
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-bold bg-warning/15 text-warning border border-warning/30">
                                            <span class="w-1.5 h-1.5 rounded-full bg-warning"></span>
                                            Off Duty
                                        </span>
                                    @endif
                                    <div class="text-[10px] text-text-muted mt-1">
                                        Added {{ $courier->created_at?->diffForHumans() }}
                                    </div>
                                </td>

                                <td class="py-4 px-5 align-top text-right">
                                    <div class="flex flex-wrap items-center justify-end gap-2">
                                        @if ($courier->status !== 'active')
                                            <form method="POST" action="{{ route('logistics.couriers.update-status', $courier->id) }}" class="m-0">
                                                @csrf
                                                @method('PATCH')
                                                <input type="hidden" name="status" value="active">
                                                <button type="submit" class="px-3.5 py-1.5 rounded-xl bg-success hover:bg-success/90 text-surface text-xs font-bold shadow-xs transition-all cursor-pointer active:scale-95">
                                                    Set Active
                                                </button>
                                            </form>
                                        @endif

                                        @if ($courier->status !== 'off_duty')
                                            <form method="POST" action="{{ route('logistics.couriers.update-status', $courier->id) }}" class="m-0">
                                                @csrf
                                                @method('PATCH')
                                                <input type="hidden" name="status" value="off_duty">
                                                <button type="submit" class="px-3.5 py-1.5 rounded-xl border border-border-subtle bg-surface-subtle hover:bg-brand-light/30 text-text-main text-xs font-bold transition-all cursor-pointer active:scale-95">
                                                    Off Duty
                                                </button>
                                            </form>
                                        @endif

                                        @if ($courier->status !== 'suspended')
                                            <form method="POST" action="{{ route('logistics.couriers.update-status', $courier->id) }}" class="m-0">
                                                @csrf
                                                @method('PATCH')
                                                <input type="hidden" name="status" value="suspended">
                                                <button type="submit" class="px-3.5 py-1.5 rounded-xl bg-danger hover:bg-danger/90 text-surface text-xs font-bold shadow-xs transition-all cursor-pointer active:scale-95">
                                                    Suspend
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

            @if ($couriers->hasPages())
                <div class="px-5 py-4 border-t border-border-subtle">
                    {{ $couriers->links() }}
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
                        <img :src="docModalUrl" alt="Courier Document" class="max-h-full max-w-full object-contain rounded-lg shadow-sm">
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