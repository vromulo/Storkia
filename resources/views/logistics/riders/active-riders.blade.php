@extends('layouts.logistics', ['title' => 'Storkia - Active Last-Mile Riders'])

@section('content')
@php
    $filter = $filter ?? request()->query('filter', 'all');
    $search = $search ?? request()->query('search', '');
@endphp

<div x-data="{
    docModalOpen: false,
    docModalTitle: '',
    docModalUrl: '',
    docModalType: 'image',
    editRiderModalOpen: false,
    editingRider: null,
    avatarPreview: '',
    avatarFileName: '',
    licenseFileName: '',
    orCrFileName: '',
    isSubmittingEdit: false,
    suspendModalOpen: false,
    suspendingRider: null,
    suspendReason: '',
    isSubmittingSuspend: false,
    openDocPreview(title, url, ext) {
        this.docModalTitle = title;
        this.docModalUrl = url;
        this.docModalType = (ext === 'pdf') ? 'pdf' : 'image';
        this.docModalOpen = true;
    },
    openEditRider(riderData) {
        this.editingRider = JSON.parse(JSON.stringify(riderData));
        this.avatarPreview = riderData.avatar_url || '';
        this.avatarFileName = '';
        this.licenseFileName = '';
        this.orCrFileName = '';
        this.isSubmittingEdit = false;
        this.editRiderModalOpen = true;
    },
    openSuspendRider(riderData) {
        this.suspendingRider = riderData;
        this.suspendReason = '';
        this.isSubmittingSuspend = false;
        this.suspendModalOpen = true;
    },
    onAvatarChange(event) {
        const file = event.target.files[0];
        if (!file) return;
        this.avatarFileName = file.name;
        const reader = new FileReader();
        reader.onload = (e) => {
            this.avatarPreview = e.target.result;
        };
        reader.readAsDataURL(file);
    }
}" class="max-w-7xl mx-auto select-none space-y-6">

    <div class="flex flex-col lg:flex-row lg:items-end justify-between gap-4">
        <div>
            <div class="flex items-center gap-2.5">
                <h1 class="text-3xl font-bold text-text-main">Active Last-Mile Riders</h1>
                <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-bold bg-brand-light/60 text-primary-dark border border-border-subtle">
                    {{ $profile->business_name }}
                </span>
            </div>
            <p class="text-sm text-text-muted mt-1">
                Monitor approved delivery riders, dispatch availability, barangay coverage, and update profile photos or vehicle documents.
            </p>
        </div>

        <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2.5 w-full lg:w-auto">
            <form method="GET" action="{{ route('logistics.riders.active') }}" class="flex items-center gap-2 flex-1 sm:w-auto">
                <input type="hidden" name="filter" value="{{ $filter }}">
                <div class="relative flex-1 sm:w-72">
                    <input
                        type="text"
                        name="search"
                        value="{{ $search }}"
                        placeholder="Search rider name, email, plate..."
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

            <a href="{{ route('logistics.riders.create') }}"
               class="inline-flex items-center justify-center gap-2 px-5 py-2.5 bg-primary-dark hover:bg-text-main text-surface text-xs font-bold rounded-2xl shadow-xs transition-all cursor-pointer active:scale-95 shrink-0">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4" />
                </svg>
                Add Rider
            </a>
        </div>
    </div>

    @if (session('success'))
        <div class="p-4 rounded-2xl bg-success/10 border border-success/20 text-success text-xs font-bold flex items-center justify-between">
            <span>{{ session('success') }}</span>
        </div>
    @endif

    @if ($errors->any())
        <div class="p-4 rounded-2xl bg-red-50 border border-red-200 text-red-700 text-xs space-y-1">
            @foreach ($errors->all() as $err)
                <p class="font-semibold">{{ $err }}</p>
            @endforeach
        </div>
    @endif

    <div class="flex flex-wrap items-center justify-between gap-2">
        <div class="flex flex-wrap items-center gap-2">
            @php
                $tabs = [
                    'all'         => 'All Approved Riders',
                    'available'   => 'Available for Dispatch',
                    'on_delivery' => 'Currently On Delivery',
                ];
            @endphp

            @foreach ($tabs as $key => $label)
                <a href="{{ route('logistics.riders.active', ['filter' => $key, 'search' => $search]) }}"
                   class="inline-flex items-center gap-2 px-4 py-2 rounded-2xl text-xs font-bold border transition-all cursor-pointer {{ $filter === $key ? 'bg-primary-dark text-surface border-primary-dark shadow-xs' : 'bg-surface text-text-muted border-border-subtle hover:text-text-main hover:bg-surface-subtle' }}">
                    <span>{{ $label }}</span>
                </a>
            @endforeach
        </div>

        <a href="{{ route('logistics.rider-applications.index') }}"
           class="text-xs font-bold text-primary hover:text-primary-dark underline">
            Open Full Rider Applications &amp; Directory &rarr;
        </a>
    </div>

    <div class="bg-surface rounded-3xl border border-border-subtle shadow-xs overflow-hidden">
        @if ($riders->isEmpty())
            <div class="py-16 px-6 text-center">
                <div class="w-12 h-12 rounded-2xl bg-surface-subtle text-text-muted flex items-center justify-center mx-auto mb-3">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                    </svg>
                </div>
                <h3 class="text-base font-bold text-text-main">No active riders found</h3>
                <p class="text-xs text-text-muted mt-1 mb-5">Approved riders matching this filter under {{ $profile->business_name }} will appear here.</p>
                <a href="{{ route('logistics.riders.create') }}"
                   class="inline-flex items-center gap-2 px-5 py-2.5 bg-primary-dark hover:bg-text-main text-surface text-xs font-bold rounded-2xl shadow-xs transition-all cursor-pointer">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4" />
                    </svg>
                    Add Rider Account
                </a>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="border-b border-border-subtle bg-surface-subtle/60 text-[11px] uppercase tracking-wider font-bold text-text-muted">
                            <th class="py-3.5 px-5">Rider &amp; Hub Coverage</th>
                            <th class="py-3.5 px-4">License &amp; Vehicle</th>
                            <th class="py-3.5 px-4">Documents</th>
                            <th class="py-3.5 px-4">Dispatch Workload</th>
                            <th class="py-3.5 px-5 text-right w-28">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border-subtle text-xs">
                        @foreach ($riders as $rider)
                            @php
                                $licenseExt = strtolower(pathinfo($rider->license_path ?? '', PATHINFO_EXTENSION));
                                $orCrExt = strtolower(pathinfo($rider->or_cr_path ?? '', PATHINFO_EXTENSION));
                                $assignedHub = $rider->assigned_hub;
                                $coveredBarangays = $rider->covered_barangay_names;
                                $activeDeliveries = (int) ($rider->active_deliveries_count ?? 0);
                                $completedDeliveries = (int) ($rider->completed_deliveries_count ?? 0);

                                $riderEditPayload = [
                                    'id'                  => $rider->id,
                                    'full_name'           => $rider->full_name,
                                    'first_name'          => $rider->first_name,
                                    'last_name'           => $rider->last_name,
                                    'email'               => $rider->email,
                                    'contact_no'          => $rider->contact_no,
                                    'birthday'            => $rider->birthday?->format('M d, Y') ?? 'Not set',
                                    'avatar_url'          => $rider->avatar_url,
                                    'vehicle_type'        => $rider->vehicle_type,
                                    'plate_number'        => $rider->plate_number,
                                    'license_no'          => $rider->license_no,
                                    'license_expiry_date' => $rider->license_expiry_date?->format('Y-m-d') ?? '',
                                ];
                            @endphp
                            <tr class="hover:bg-surface-subtle/40 transition-colors">
                                <td class="py-4 px-5 align-top">
                                    <div class="flex items-start gap-3">
                                        <div class="w-11 h-11 rounded-full bg-brand-light/40 border border-border-subtle overflow-hidden flex items-center justify-center shrink-0 font-serif font-bold text-primary-dark text-sm">
                                            @if($rider->avatar_url)
                                                <img src="{{ $rider->avatar_url }}" alt="{{ $rider->full_name }}" class="w-full h-full object-cover">
                                            @else
                                                {{ strtoupper(substr($rider->first_name ?? 'R', 0, 1)) }}
                                            @endif
                                        </div>
                                        <div class="min-w-0">
                                            <div class="font-bold text-sm text-text-main">{{ $rider->full_name }}</div>
                                            <div class="text-text-muted mt-0.5 font-mono text-[11px]">{{ $rider->email }}</div>
                                            <div class="text-text-muted mt-0.5">{{ $rider->contact_no }}</div>
                                            <div class="text-[11px] text-text-muted/80 mt-1">
                                                DOB: {{ $rider->birthday?->format('M d, Y') }} ({{ $rider->birthday?->age }} yrs old)
                                            </div>
                                            @if($assignedHub)
                                                <div class="mt-1.5">
                                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-lg text-[10px] font-bold bg-brand-light/40 text-primary-dark border border-border-subtle">
                                                        Hub: {{ $assignedHub->municipality }} ({{ $assignedHub->hub_name }})
                                                    </span>
                                                </div>
                                            @endif
                                            @if(!empty($coveredBarangays))
                                                <div class="flex flex-wrap gap-1 mt-1.5 max-w-xs">
                                                    @foreach(array_slice($coveredBarangays, 0, 3) as $brgy)
                                                        <span class="px-2 py-0.5 rounded-md bg-blue-50 text-blue-800 border border-blue-200 text-[10px] font-semibold">
                                                            {{ $brgy }}
                                                        </span>
                                                    @endforeach
                                                    @if(count($coveredBarangays) > 3)
                                                        <span class="px-2 py-0.5 rounded-md bg-surface-subtle text-text-muted border border-border-subtle text-[10px] font-bold">
                                                            +{{ count($coveredBarangays) - 3 }} more
                                                        </span>
                                                    @endif
                                                </div>
                                            @endif
                                        </div>
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
                                        @if(!empty($rider->license_path))
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
                                        @endif

                                        @if(!empty($rider->or_cr_path))
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
                                        @endif
                                    </div>
                                </td>

                                <td class="py-4 px-4 align-top space-y-1.5">
                                    @if ($activeDeliveries > 0)
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-bold bg-primary/10 text-primary border border-primary/20">
                                            <span class="w-1.5 h-1.5 rounded-full bg-primary animate-pulse"></span>
                                            On Delivery ({{ $activeDeliveries }} Active)
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-bold bg-success/10 text-success border border-success/20">
                                            <span class="w-1.5 h-1.5 rounded-full bg-success"></span>
                                            Available for Dispatch
                                        </span>
                                    @endif

                                    <div class="text-[11px] text-text-muted">
                                        Completed Deliveries: <strong class="text-text-main">{{ number_format($completedDeliveries) }}</strong>
                                    </div>

                                    @if((int) ($rider->sla_strikes ?? 0) > 0)
                                        <div class="text-[10px] font-bold text-danger">
                                            SLA Strikes: {{ (int) $rider->sla_strikes }} / 3
                                        </div>
                                    @endif
                                </td>

                                <td class="py-4 px-5 align-top text-right">
                                    <div x-data="{
                                        rowMenuOpen: false,
                                        menuStyle: '',
                                        toggleRowMenu(event) {
                                            if (this.rowMenuOpen) {
                                                this.rowMenuOpen = false;
                                                return;
                                            }
                                            const rect = event.currentTarget.getBoundingClientRect();
                                            const menuWidth = 208;
                                            const left = Math.max(12, rect.right - menuWidth);
                                            const spaceBelow = window.innerHeight - rect.bottom;
                                            if (spaceBelow < 160) {
                                                this.menuStyle = `position: fixed; left: ${left}px; bottom: ${window.innerHeight - rect.top + 6}px; z-index: 9999;`;
                                            } else {
                                                this.menuStyle = `position: fixed; left: ${left}px; top: ${rect.bottom + 6}px; z-index: 9999;`;
                                            }
                                            this.rowMenuOpen = true;
                                        }
                                    }"
                                    @scroll.window="rowMenuOpen = false"
                                    @resize.window="rowMenuOpen = false"
                                    class="inline-block text-left">
                                        <button type="button"
                                                @click.stop="toggleRowMenu($event)"
                                                class="p-2 rounded-full text-text-muted hover:text-primary hover:bg-brand-light/30 transition-colors focus:outline-none cursor-pointer"
                                                title="Rider actions">
                                            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                                <path d="M10 6a2 2 0 110-4 2 2 0 010 4zM10 12a2 2 0 110-4 2 2 0 010 4zM10 18a2 2 0 110-4 2 2 0 010 4z"/>
                                            </svg>
                                        </button>

                                        <template x-teleport="body">
                                            <div x-show="rowMenuOpen"
                                                 x-cloak
                                                 @click.outside="rowMenuOpen = false"
                                                 x-transition.opacity.duration.150ms
                                                 :style="menuStyle"
                                                 class="w-52 bg-surface rounded-2xl shadow-2xl border-2 border-border-subtle overflow-hidden divide-y divide-border-subtle/60 text-xs select-none">
                                                <button type="button"
                                                        @click="rowMenuOpen = false; openEditRider(@js($riderEditPayload))"
                                                        class="w-full text-left px-4 py-2.5 font-bold text-primary-dark hover:bg-brand-light/25 flex items-center gap-2 cursor-pointer">
                                                    <svg class="w-4 h-4 text-primary shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                    </svg>
                                                    Edit Profile &amp; Photo
                                                </button>

                                                <button type="button"
                                                        @click="rowMenuOpen = false; openSuspendRider(@js($riderEditPayload))"
                                                        class="w-full text-left px-4 py-2.5 font-semibold text-danger hover:bg-red-50 flex items-center gap-2 cursor-pointer">
                                                    <span class="w-2 h-2 rounded-full bg-danger"></span>
                                                    Suspend Rider
                                                </button>
                                            </div>
                                        </template>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($riders->hasPages())
                <div class="px-5 py-4 border-t border-border-subtle">
                    {{ $riders->links() }}
                </div>
            @endif
        @endif
    </div>

    <!-- Enhanced Edit Rider Profile Modal -->
    <template x-teleport="body">
        <div x-show="editRiderModalOpen && editingRider"
             x-cloak
             class="fixed inset-0 z-[195] flex items-center justify-center p-4">
            <div @click="editRiderModalOpen = false" class="absolute inset-0 bg-black/65 backdrop-blur-xs cursor-pointer"></div>
            <div @click.stop class="relative z-10 bg-surface rounded-3xl max-w-2xl w-full p-6 sm:p-8 shadow-2xl border-2 border-border-subtle space-y-6 max-h-[92vh] overflow-y-auto custom-scrollbar">
                <div class="flex items-start justify-between gap-4 pb-4 border-b border-border-subtle">
                    <div>
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[10px] font-extrabold uppercase tracking-wider bg-primary/10 text-primary mb-1">
                            Last-Mile Fleet Record
                        </span>
                        <h3 class="text-xl font-extrabold text-primary-dark">Edit Rider Profile &amp; Vehicle</h3>
                        <p class="text-xs text-text-muted mt-0.5">Upload a new profile photo, update vehicle &amp; license credentials, or replace legal documents.</p>
                    </div>
                    <button type="button" @click="editRiderModalOpen = false" class="p-2 rounded-full bg-surface-subtle hover:bg-red-50 text-text-muted hover:text-danger transition-colors cursor-pointer">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <form :action="`{{ url('/logistics/riders') }}/${editingRider?.id}/profile`"
                      method="POST"
                      enctype="multipart/form-data"
                      @submit="isSubmittingEdit = true"
                      class="space-y-6 text-xs">
                    @csrf
                    @method('PATCH')

                    <!-- High-Emphasis Profile Picture Upload Card -->
                    <div class="p-5 rounded-2xl bg-gradient-to-r from-brand-light/35 via-surface-subtle to-brand-light/20 border-2 border-primary/30 shadow-xs flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                        <div class="flex items-center gap-4">
                            <div class="w-20 h-20 rounded-full bg-white border-4 border-primary/30 shadow-md overflow-hidden flex items-center justify-center shrink-0 font-serif font-bold text-2xl text-primary-dark">
                                <template x-if="avatarPreview">
                                    <img :src="avatarPreview" class="w-full h-full object-cover">
                                </template>
                                <template x-if="!avatarPreview">
                                    <span x-text="(editingRider?.first_name || 'R').charAt(0).toUpperCase()"></span>
                                </template>
                            </div>
                            <div class="space-y-1">
                                <span class="px-2 py-0.5 rounded-md bg-primary text-white text-[10px] font-extrabold uppercase tracking-wider">Profile Photo</span>
                                <h4 class="text-sm font-extrabold text-text-main">Rider Avatar Image</h4>
                                <p class="text-[11px] text-text-muted" x-text="avatarFileName ? 'Selected: ' + avatarFileName : 'JPG, PNG, or WEBP up to 5 MB (Auto-optimized to WebP)'"></p>
                            </div>
                        </div>

                        <div class="shrink-0">
                            <label class="inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl bg-primary-dark hover:bg-primary text-white font-extrabold text-xs shadow-sm transition-all cursor-pointer active:scale-95">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                                </svg>
                                <span x-text="avatarFileName ? 'Change Chosen Photo' : 'Choose Photo'"></span>
                                <input type="file"
                                       name="avatar"
                                       accept="image/jpeg,image/png,image/webp"
                                       @change="onAvatarChange($event)"
                                       class="hidden">
                            </label>
                        </div>
                    </div>

                    <!-- Read-Only Personal Identity Details -->
                    <div class="p-4 rounded-2xl bg-gray-100/90 border border-gray-300 space-y-3">
                        <div class="flex items-center justify-between">
                            <span class="text-[11px] font-extrabold uppercase tracking-wider text-gray-600 flex items-center gap-1.5">
                                <svg class="w-3.5 h-3.5 text-gray-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/></svg>
                                Personal Identity Details (Locked)
                            </span>
                            <span class="px-2.5 py-0.5 rounded-full bg-gray-200 text-gray-700 text-[10px] font-extrabold uppercase">Read-Only</span>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label class="block text-[10px] font-bold text-gray-500 uppercase">Full Name</label>
                                <input type="text" :value="editingRider?.full_name" readonly class="w-full mt-1 px-3 py-2 rounded-xl border border-gray-300 bg-gray-200/70 text-gray-700 font-bold cursor-not-allowed outline-none">
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-gray-500 uppercase">Date of Birth</label>
                                <input type="text" :value="editingRider?.birthday" readonly class="w-full mt-1 px-3 py-2 rounded-xl border border-gray-300 bg-gray-200/70 text-gray-700 font-semibold cursor-not-allowed outline-none">
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-gray-500 uppercase">Internal Email</label>
                                <input type="text" :value="editingRider?.email" readonly class="w-full mt-1 px-3 py-2 rounded-xl border border-gray-300 bg-gray-200/70 text-gray-700 font-mono cursor-not-allowed outline-none">
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-gray-500 uppercase">Contact Number</label>
                                <input type="text" :value="editingRider?.contact_no" readonly class="w-full mt-1 px-3 py-2 rounded-xl border border-gray-300 bg-gray-200/70 text-gray-700 font-mono cursor-not-allowed outline-none">
                            </div>
                        </div>
                    </div>

                    <!-- Emphasized Editable Vehicle & License Fields -->
                    <div class="p-5 rounded-2xl bg-white border-2 border-primary/25 shadow-2xs space-y-4">
                        <div class="flex items-center justify-between border-b border-border-subtle pb-2.5">
                            <span class="text-xs font-extrabold uppercase tracking-wider text-primary-dark">Editable Vehicle &amp; License Credentials</span>
                            <span class="text-[11px] font-bold text-primary">Required Fields *</span>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block font-extrabold text-text-main uppercase text-[11px] mb-1.5">Vehicle Type <span class="text-danger">*</span></label>
                                <select name="vehicle_type"
                                        x-model="editingRider.vehicle_type"
                                        required
                                        class="w-full px-3.5 py-2.5 rounded-xl border-2 border-gray-300 hover:border-primary/60 focus:border-primary focus:ring-4 focus:ring-primary/10 bg-white text-text-main font-bold text-xs outline-none transition-all cursor-pointer">
                                    <option value="Motorcycle">Motorcycle</option>
                                    <option value="Bicycle / E-Bike">Bicycle / E-Bike</option>
                                    <option value="Tricycle / Three-Wheeler">Tricycle / Three-Wheeler</option>
                                    <option value="Sedan / Hatchback">Sedan / Hatchback</option>
                                    <option value="MPV / Utility Van">MPV / Utility Van</option>
                                </select>
                            </div>

                            <div>
                                <label class="block font-extrabold text-text-main uppercase text-[11px] mb-1.5">Plate Number <span class="text-danger">*</span></label>
                                <input type="text"
                                       name="plate_number"
                                       x-model="editingRider.plate_number"
                                       required
                                       maxlength="20"
                                       placeholder="e.g. ABC-1234"
                                       class="w-full px-3.5 py-2.5 rounded-xl border-2 border-gray-300 hover:border-primary/60 focus:border-primary focus:ring-4 focus:ring-primary/10 bg-white font-mono uppercase font-extrabold text-primary-dark text-xs outline-none transition-all">
                            </div>

                            <div>
                                <label class="block font-extrabold text-text-main uppercase text-[11px] mb-1.5">Driver's License No. <span class="text-danger">*</span></label>
                                <input type="text"
                                       name="license_no"
                                       x-model="editingRider.license_no"
                                       required
                                       maxlength="35"
                                       placeholder="e.g. N01-23-456789"
                                       class="w-full px-3.5 py-2.5 rounded-xl border-2 border-gray-300 hover:border-primary/60 focus:border-primary focus:ring-4 focus:ring-primary/10 bg-white font-mono uppercase font-extrabold text-text-main text-xs outline-none transition-all">
                            </div>

                            <div>
                                <label class="block font-extrabold text-text-main uppercase text-[11px] mb-1.5">License Expiry Date <span class="text-danger">*</span></label>
                                <input type="date"
                                       name="license_expiry_date"
                                       x-model="editingRider.license_expiry_date"
                                       required
                                       class="w-full px-3.5 py-2.5 rounded-xl border-2 border-gray-300 hover:border-primary/60 focus:border-primary focus:ring-4 focus:ring-primary/10 bg-white font-bold text-text-main text-xs outline-none transition-all cursor-pointer">
                            </div>
                        </div>
                    </div>

                    <!-- High-Emphasis Document Replacement Upload Cards -->
                    <div class="p-5 rounded-2xl bg-surface-subtle/70 border-2 border-border-subtle space-y-3">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-extrabold uppercase tracking-wider text-primary-dark">Replace Compliance Documents (Optional)</span>
                            <span class="text-[11px] font-semibold text-text-muted">JPG, PNG, WEBP, or PDF (Max 5MB)</span>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div class="p-3.5 rounded-2xl bg-white border-2 border-dashed border-primary/35 hover:border-primary transition-all flex flex-col justify-between gap-2.5">
                                <div>
                                    <span class="text-[11px] font-extrabold text-text-main block">Driver's License Document</span>
                                    <span class="text-[10px] text-text-muted block mt-0.5 truncate" x-text="licenseFileName ? 'Selected: ' + licenseFileName : 'Keep current file or choose replacement'"></span>
                                </div>
                                <label class="inline-flex items-center justify-center gap-2 px-3.5 py-2 rounded-xl bg-primary/10 hover:bg-primary text-primary hover:text-white font-extrabold text-xs border border-primary/30 transition-all cursor-pointer w-full text-center">
                                    <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg>
                                    <span x-text="licenseFileName ? 'Change License File' : 'Choose License File'"></span>
                                    <input type="file"
                                           name="license_file"
                                           accept=".jpg,.jpeg,.png,.webp,.pdf"
                                           @change="licenseFileName = $event.target.files[0]?.name || ''"
                                           class="hidden">
                                </label>
                            </div>

                            <div class="p-3.5 rounded-2xl bg-white border-2 border-dashed border-primary/35 hover:border-primary transition-all flex flex-col justify-between gap-2.5">
                                <div>
                                    <span class="text-[11px] font-extrabold text-text-main block">Vehicle OR/CR Document</span>
                                    <span class="text-[10px] text-text-muted block mt-0.5 truncate" x-text="orCrFileName ? 'Selected: ' + orCrFileName : 'Keep current file or choose replacement'"></span>
                                </div>
                                <label class="inline-flex items-center justify-center gap-2 px-3.5 py-2 rounded-xl bg-primary/10 hover:bg-primary text-primary hover:text-white font-extrabold text-xs border border-primary/30 transition-all cursor-pointer w-full text-center">
                                    <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg>
                                    <span x-text="orCrFileName ? 'Change OR/CR File' : 'Choose OR/CR File'"></span>
                                    <input type="file"
                                           name="or_cr_file"
                                           accept=".jpg,.jpeg,.png,.webp,.pdf"
                                           @change="orCrFileName = $event.target.files[0]?.name || ''"
                                           class="hidden">
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-3 pt-3 border-t border-border-subtle">
                        <button type="button"
                                @click="editRiderModalOpen = false"
                                class="px-5 py-2.5 rounded-xl bg-surface-subtle hover:bg-gray-200 text-text-main font-bold cursor-pointer transition-colors">
                            Cancel
                        </button>
                        <button type="submit"
                                :disabled="isSubmittingEdit"
                                class="px-6 py-2.5 rounded-xl bg-primary-dark hover:bg-primary text-white font-extrabold shadow-md cursor-pointer transition-all disabled:opacity-50">
                            <span x-show="!isSubmittingEdit">Save Rider Profile</span>
                            <span x-show="isSubmittingEdit" x-cloak>Saving...</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </template>

    <!-- Suspend Rider Modal -->
    <template x-teleport="body">
        <div x-show="suspendModalOpen && suspendingRider"
             x-cloak
             class="fixed inset-0 z-[195] flex items-center justify-center p-4">
            <div @click="suspendModalOpen = false" class="absolute inset-0 bg-black/65 backdrop-blur-xs cursor-pointer"></div>
            <div @click.stop class="relative z-10 bg-surface rounded-3xl max-w-md w-full p-6 shadow-2xl border-2 border-border-subtle space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-border-subtle">
                    <div>
                        <h3 class="text-base font-extrabold text-danger">Suspend Rider Account</h3>
                        <p class="text-xs text-text-muted">Rider: <strong class="text-text-main" x-text="suspendingRider?.full_name"></strong></p>
                    </div>
                    <button type="button" @click="suspendModalOpen = false" class="p-1 text-text-muted hover:text-text-main cursor-pointer">&times;</button>
                </div>

                <form :action="`{{ url('/logistics/riders') }}/${suspendingRider?.id}/suspend`"
                      method="POST"
                      @submit="isSubmittingSuspend = true"
                      class="space-y-4 text-xs">
                    @csrf
                    <div>
                        <label class="block font-extrabold text-text-main uppercase text-[11px] mb-1.5">Suspension Reason (10–300 chars) <span class="text-danger">*</span></label>
                        <textarea name="reason"
                                  x-model="suspendReason"
                                  rows="3"
                                  minlength="10"
                                  maxlength="300"
                                  required
                                  placeholder="Explain why this rider is being suspended (uncollected assignments will return to the hub queue)..."
                                  class="w-full p-3 rounded-xl border-2 border-gray-300 focus:border-danger bg-white text-text-main font-medium outline-none"></textarea>
                    </div>

                    <div class="flex justify-end gap-2 pt-2 border-t border-border-subtle">
                        <button type="button" @click="suspendModalOpen = false" class="px-4 py-2.5 rounded-xl bg-surface-subtle text-text-muted font-bold cursor-pointer">
                            Cancel
                        </button>
                        <button type="submit"
                                :disabled="isSubmittingSuspend"
                                class="px-5 py-2.5 rounded-xl bg-danger hover:bg-red-700 text-white font-extrabold shadow-sm cursor-pointer disabled:opacity-50">
                            Confirm Suspension
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </template>

    <!-- Document Preview Modal -->
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
                        <img :src="docModalUrl" alt="Rider Document" class="max-h-full max-w-full object-contain rounded-lg shadow-sm">
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