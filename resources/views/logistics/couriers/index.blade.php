@extends('layouts.logistics', ['title' => 'Storkia - All Couriers'])

@section('content')
<div x-data="{
    docModalOpen: false,
    docModalTitle: '',
    docModalUrl: '',
    docModalType: 'image',
    editCourierModalOpen: false,
    editingCourier: null,
    avatarPreview: '',
    avatarFileName: '',
    licenseFileName: '',
    orCrFileName: '',
    nbiFileName: '',
    isSubmittingEdit: false,
    openDocPreview(title, url, ext) {
        this.docModalTitle = title;
        this.docModalUrl = url;
        this.docModalType = (ext === 'pdf') ? 'pdf' : 'image';
        this.docModalOpen = true;
    },
    openEditCourier(courierData) {
        this.editingCourier = JSON.parse(JSON.stringify(courierData));
        this.avatarPreview = courierData.avatar_url || '';
        this.avatarFileName = '';
        this.licenseFileName = '';
        this.orCrFileName = '';
        this.nbiFileName = '';
        this.isSubmittingEdit = false;
        this.editCourierModalOpen = true;
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
                <h1 class="text-3xl font-bold text-text-main">All Couriers</h1>
                <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-bold bg-brand-light/60 text-primary-dark border border-border-subtle">
                    {{ $profile->business_name }}
                </span>
            </div>
            <p class="text-sm text-text-muted mt-1">
                Manage registered linehaul couriers, profile photos, assigned hubs, vehicles, and verification documents.
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

    @if ($errors->any())
        <div class="p-4 rounded-2xl bg-red-50 border border-red-200 text-red-700 text-xs space-y-1">
            @foreach ($errors->all() as $err)
                <p class="font-semibold">{{ $err }}</p>
            @endforeach
        </div>
    @endif

    <div class="flex flex-wrap items-center gap-2">
        @php
            $tabs = [
                'all'         => ['label' => 'All Couriers', 'count' => $counts['all']],
                'active'      => ['label' => 'Active', 'count' => $counts['active']],
                'on_delivery' => ['label' => 'On Delivery', 'count' => $counts['on_delivery']],
                'off_duty'    => ['label' => 'Off Duty', 'count' => $counts['off_duty']],
                'suspended'   => ['label' => 'Suspended', 'count' => $counts['suspended']],
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
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 5.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1m-6-1a1 1 0 001 1h1M5 17a2 2 0 104 0m-4 0a2 2 0 114 0m6 0a2 2 0 104 0m-4 0a2 2 0 114 0" />
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
                            <th class="py-3.5 px-4">License &amp; Vehicle</th>
                            <th class="py-3.5 px-4">Documents</th>
                            <th class="py-3.5 px-4">Status</th>
                            <th class="py-3.5 px-5 text-right w-28">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border-subtle text-xs">
                        @foreach ($couriers as $courier)
                            @php
                                $licenseExt = strtolower(pathinfo($courier->license_path ?? '', PATHINFO_EXTENSION));
                                $orCrExt = strtolower(pathinfo($courier->or_cr_path ?? '', PATHINFO_EXTENSION));
                                $nbiExt = strtolower(pathinfo($courier->nbi_clearance_path ?? '', PATHINFO_EXTENSION));
                                $courierEditPayload = [
                                    'id'                  => $courier->id,
                                    'full_name'           => $courier->full_name,
                                    'first_name'          => $courier->first_name,
                                    'last_name'           => $courier->last_name,
                                    'email'               => $courier->email,
                                    'contact_no'          => $courier->contact_no,
                                    'birthday'            => $courier->birthday?->format('M d, Y') ?? 'Not set',
                                    'avatar_url'          => $courier->avatar_url,
                                    'vehicle_type'        => $courier->vehicle_type,
                                    'plate_number'        => $courier->plate_number,
                                    'load_capacity'       => (int) $courier->load_capacity,
                                    'license_no'          => $courier->license_no,
                                    'license_expiry_date' => $courier->license_expiry_date?->format('Y-m-d') ?? '',
                                ];
                            @endphp
                            <tr class="hover:bg-surface-subtle/40 transition-colors">
                                <td class="py-4 px-5 align-top">
                                    <div class="flex items-start gap-3">
                                        <div class="w-11 h-11 rounded-full bg-brand-light/40 border border-border-subtle overflow-hidden flex items-center justify-center shrink-0 font-serif font-bold text-primary-dark text-sm">
                                            @if($courier->avatar_url)
                                                <img src="{{ $courier->avatar_url }}" alt="{{ $courier->full_name }}" class="w-full h-full object-cover">
                                            @else
                                                {{ strtoupper(substr($courier->first_name ?? 'C', 0, 1)) }}
                                            @endif
                                        </div>
                                        <div class="min-w-0">
                                            <div class="font-bold text-sm text-text-main">{{ $courier->full_name }}</div>
                                            <div class="text-text-muted mt-0.5 font-mono text-[11px]">{{ $courier->email }}</div>
                                            <div class="text-text-muted mt-0.5">{{ $courier->contact_no }}</div>
                                            <div class="text-[11px] text-text-muted/80 mt-1">
                                                DOB: {{ $courier->birthday?->format('M d, Y') }} ({{ $courier->birthday?->age }} yrs old)
                                            </div>
                                            <div class="mt-1.5">
                                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-lg text-[10px] font-bold bg-brand-light/40 text-primary-dark border border-border-subtle">
                                                    Home Hub: {{ $courier->home_hub }}
                                                </span>
                                            </div>
                                        </div>
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
                                    <div x-data="{
                                        rowMenuOpen: false,
                                        menuStyle: '',
                                        toggleRowMenu(event) {
                                            if (this.rowMenuOpen) {
                                                this.rowMenuOpen = false;
                                                return;
                                            }
                                            const rect = event.currentTarget.getBoundingClientRect();
                                            const menuWidth = 196;
                                            const left = Math.max(12, rect.right - menuWidth);
                                            const spaceBelow = window.innerHeight - rect.bottom;
                                            if (spaceBelow < 195) {
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
                                                title="Courier actions">
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
                                                 class="w-48 bg-surface rounded-2xl shadow-2xl border-2 border-border-subtle overflow-hidden divide-y divide-border-subtle/60 text-xs select-none">
                                                <button type="button"
                                                        @click="rowMenuOpen = false; openEditCourier(@js($courierEditPayload))"
                                                        class="w-full text-left px-4 py-2.5 font-bold text-primary-dark hover:bg-brand-light/25 flex items-center gap-2 cursor-pointer">
                                                    <svg class="w-4 h-4 text-primary shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                    </svg>
                                                    Edit Profile &amp; Photo
                                                </button>

                                                @if ($courier->status !== 'active')
                                                    <form method="POST" action="{{ route('logistics.couriers.update-status', $courier->id) }}" class="m-0">
                                                        @csrf
                                                        @method('PATCH')
                                                        <input type="hidden" name="status" value="active">
                                                        <button type="submit" class="w-full text-left px-4 py-2.5 font-semibold text-success hover:bg-green-50 flex items-center gap-2 cursor-pointer">
                                                            <span class="w-2 h-2 rounded-full bg-success"></span>
                                                            Set Active
                                                        </button>
                                                    </form>
                                                @endif

                                                @if ($courier->status !== 'off_duty')
                                                    <form method="POST" action="{{ route('logistics.couriers.update-status', $courier->id) }}" class="m-0">
                                                        @csrf
                                                        @method('PATCH')
                                                        <input type="hidden" name="status" value="off_duty">
                                                        <button type="submit" class="w-full text-left px-4 py-2.5 font-semibold text-text-main hover:bg-surface-subtle flex items-center gap-2 cursor-pointer">
                                                            <span class="w-2 h-2 rounded-full bg-warning"></span>
                                                            Set Off Duty
                                                        </button>
                                                    </form>
                                                @endif

                                                @if ($courier->status !== 'suspended')
                                                    <form method="POST" action="{{ route('logistics.couriers.update-status', $courier->id) }}" class="m-0">
                                                        @csrf
                                                        @method('PATCH')
                                                        <input type="hidden" name="status" value="suspended">
                                                        <button type="submit" class="w-full text-left px-4 py-2.5 font-semibold text-danger hover:bg-red-50 flex items-center gap-2 cursor-pointer">
                                                            <span class="w-2 h-2 rounded-full bg-danger"></span>
                                                            Suspend Courier
                                                        </button>
                                                    </form>
                                                @endif
                                            </div>
                                        </template>
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

    <!-- Enhanced Edit Courier Profile Modal -->
    <template x-teleport="body">
        <div x-show="editCourierModalOpen && editingCourier"
             x-cloak
             class="fixed inset-0 z-[195] flex items-center justify-center p-4">
            <div @click="editCourierModalOpen = false" class="absolute inset-0 bg-black/65 backdrop-blur-xs cursor-pointer"></div>
            <div @click.stop class="relative z-10 bg-surface rounded-3xl max-w-2xl w-full p-6 sm:p-8 shadow-2xl border-2 border-border-subtle space-y-6 max-h-[92vh] overflow-y-auto custom-scrollbar">
                <div class="flex items-start justify-between gap-4 pb-4 border-b border-border-subtle">
                    <div>
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[10px] font-extrabold uppercase tracking-wider bg-primary/10 text-primary mb-1">
                            First-Mile Fleet Record
                        </span>
                        <h3 class="text-xl font-extrabold text-primary-dark">Edit Courier Profile &amp; Vehicle</h3>
                        <p class="text-xs text-text-muted mt-0.5">Upload a profile photo, update linehaul vehicle &amp; license credentials, or replace compliance documents.</p>
                    </div>
                    <button type="button" @click="editCourierModalOpen = false" class="p-2 rounded-full bg-surface-subtle hover:bg-red-50 text-text-muted hover:text-danger transition-colors cursor-pointer">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <form :action="`{{ url('/logistics/couriers') }}/${editingCourier?.id}/profile`"
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
                                    <span x-text="(editingCourier?.first_name || 'C').charAt(0).toUpperCase()"></span>
                                </template>
                            </div>
                            <div class="space-y-1">
                                <span class="px-2 py-0.5 rounded-md bg-primary text-white text-[10px] font-extrabold uppercase tracking-wider">Profile Photo</span>
                                <h4 class="text-sm font-extrabold text-text-main">Courier Avatar Image</h4>
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
                                <input type="text" :value="editingCourier?.full_name" readonly class="w-full mt-1 px-3 py-2 rounded-xl border border-gray-300 bg-gray-200/70 text-gray-700 font-bold cursor-not-allowed outline-none">
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-gray-500 uppercase">Date of Birth</label>
                                <input type="text" :value="editingCourier?.birthday" readonly class="w-full mt-1 px-3 py-2 rounded-xl border border-gray-300 bg-gray-200/70 text-gray-700 font-semibold cursor-not-allowed outline-none">
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-gray-500 uppercase">Internal Email</label>
                                <input type="text" :value="editingCourier?.email" readonly class="w-full mt-1 px-3 py-2 rounded-xl border border-gray-300 bg-gray-200/70 text-gray-700 font-mono cursor-not-allowed outline-none">
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-gray-500 uppercase">Contact Number</label>
                                <input type="text" :value="editingCourier?.contact_no" readonly class="w-full mt-1 px-3 py-2 rounded-xl border border-gray-300 bg-gray-200/70 text-gray-700 font-mono cursor-not-allowed outline-none">
                            </div>
                        </div>
                    </div>

                    <!-- Emphasized Editable Vehicle & License Details -->
                    <div class="p-5 rounded-2xl bg-white border-2 border-primary/25 shadow-2xs space-y-4">
                        <div class="flex items-center justify-between border-b border-border-subtle pb-2.5">
                            <span class="text-xs font-extrabold uppercase tracking-wider text-primary-dark">Editable Vehicle &amp; License Credentials</span>
                            <span class="text-[11px] font-bold text-primary">Required Fields *</span>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block font-extrabold text-text-main uppercase text-[11px] mb-1.5">Vehicle Type <span class="text-danger">*</span></label>
                                <select name="vehicle_type"
                                        x-model="editingCourier.vehicle_type"
                                        required
                                        class="w-full px-3.5 py-2.5 rounded-xl border-2 border-gray-300 hover:border-primary/60 focus:border-primary focus:ring-4 focus:ring-primary/10 bg-white text-text-main font-bold text-xs outline-none transition-all cursor-pointer">
                                    <option value="L300 / Closed Van">L300 / Closed Van</option>
                                    <option value="Pickup Truck">Pickup Truck</option>
                                    <option value="4-Wheeler Box Truck">4-Wheeler Box Truck</option>
                                    <option value="6-Wheeler Cargo Truck">6-Wheeler Cargo Truck</option>
                                    <option value="MPV / Utility Van">MPV / Utility Van</option>
                                    <option value="Motorcycle">Motorcycle</option>
                                </select>
                            </div>

                            <div>
                                <label class="block font-extrabold text-text-main uppercase text-[11px] mb-1.5">Load Capacity (KG) <span class="text-danger">*</span></label>
                                <div class="relative">
                                    <input type="number"
                                           min="50"
                                           max="20000"
                                           name="load_capacity"
                                           x-model="editingCourier.load_capacity"
                                           required
                                           class="w-full px-3.5 py-2.5 pr-12 rounded-xl border-2 border-gray-300 hover:border-primary/60 focus:border-primary focus:ring-4 focus:ring-primary/10 bg-white font-extrabold text-text-main text-xs outline-none transition-all">
                                    <span class="absolute right-3 top-1/2 -translate-y-1/2 px-1.5 py-0.5 rounded bg-brand-light/50 text-primary-dark font-extrabold text-[10px]">KG</span>
                                </div>
                            </div>

                            <div>
                                <label class="block font-extrabold text-text-main uppercase text-[11px] mb-1.5">Plate Number <span class="text-danger">*</span></label>
                                <input type="text"
                                       name="plate_number"
                                       x-model="editingCourier.plate_number"
                                       required
                                       maxlength="20"
                                       placeholder="e.g. NBC-4567"
                                       class="w-full px-3.5 py-2.5 rounded-xl border-2 border-gray-300 hover:border-primary/60 focus:border-primary focus:ring-4 focus:ring-primary/10 bg-white font-mono uppercase font-extrabold text-primary-dark text-xs outline-none transition-all">
                            </div>

                            <div>
                                <label class="block font-extrabold text-text-main uppercase text-[11px] mb-1.5">Driver's License No. <span class="text-danger">*</span></label>
                                <input type="text"
                                       name="license_no"
                                       x-model="editingCourier.license_no"
                                       required
                                       maxlength="35"
                                       placeholder="e.g. N01-23-456789"
                                       class="w-full px-3.5 py-2.5 rounded-xl border-2 border-gray-300 hover:border-primary/60 focus:border-primary focus:ring-4 focus:ring-primary/10 bg-white font-mono uppercase font-extrabold text-text-main text-xs outline-none transition-all">
                            </div>

                            <div class="sm:col-span-2">
                                <label class="block font-extrabold text-text-main uppercase text-[11px] mb-1.5">License Expiry Date <span class="text-danger">*</span></label>
                                <input type="date"
                                       name="license_expiry_date"
                                       x-model="editingCourier.license_expiry_date"
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

                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3.5">
                            <div class="p-3.5 rounded-2xl bg-white border-2 border-dashed border-primary/35 hover:border-primary transition-all flex flex-col justify-between gap-2.5">
                                <div>
                                    <span class="text-[11px] font-extrabold text-text-main block">Driver's License</span>
                                    <span class="text-[10px] text-text-muted block mt-0.5 truncate" x-text="licenseFileName ? 'Selected: ' + licenseFileName : 'Replace license file'"></span>
                                </div>
                                <label class="inline-flex items-center justify-center gap-1.5 px-3 py-2 rounded-xl bg-primary/10 hover:bg-primary text-primary hover:text-white font-extrabold text-xs border border-primary/30 transition-all cursor-pointer w-full text-center">
                                    <svg class="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg>
                                    <span x-text="licenseFileName ? 'Change File' : 'Choose File'"></span>
                                    <input type="file"
                                           name="license_file"
                                           accept=".jpg,.jpeg,.png,.webp,.pdf"
                                           @change="licenseFileName = $event.target.files[0]?.name || ''"
                                           class="hidden">
                                </label>
                            </div>

                            <div class="p-3.5 rounded-2xl bg-white border-2 border-dashed border-primary/35 hover:border-primary transition-all flex flex-col justify-between gap-2.5">
                                <div>
                                    <span class="text-[11px] font-extrabold text-text-main block">Vehicle OR/CR</span>
                                    <span class="text-[10px] text-text-muted block mt-0.5 truncate" x-text="orCrFileName ? 'Selected: ' + orCrFileName : 'Replace OR/CR file'"></span>
                                </div>
                                <label class="inline-flex items-center justify-center gap-1.5 px-3 py-2 rounded-xl bg-primary/10 hover:bg-primary text-primary hover:text-white font-extrabold text-xs border border-primary/30 transition-all cursor-pointer w-full text-center">
                                    <svg class="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg>
                                    <span x-text="orCrFileName ? 'Change File' : 'Choose File'"></span>
                                    <input type="file"
                                           name="or_cr_file"
                                           accept=".jpg,.jpeg,.png,.webp,.pdf"
                                           @change="orCrFileName = $event.target.files[0]?.name || ''"
                                           class="hidden">
                                </label>
                            </div>

                            <div class="p-3.5 rounded-2xl bg-white border-2 border-dashed border-primary/35 hover:border-primary transition-all flex flex-col justify-between gap-2.5">
                                <div>
                                    <span class="text-[11px] font-extrabold text-text-main block">NBI Clearance</span>
                                    <span class="text-[10px] text-text-muted block mt-0.5 truncate" x-text="nbiFileName ? 'Selected: ' + nbiFileName : 'Replace NBI file'"></span>
                                </div>
                                <label class="inline-flex items-center justify-center gap-1.5 px-3 py-2 rounded-xl bg-primary/10 hover:bg-primary text-primary hover:text-white font-extrabold text-xs border border-primary/30 transition-all cursor-pointer w-full text-center">
                                    <svg class="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg>
                                    <span x-text="nbiFileName ? 'Change File' : 'Choose File'"></span>
                                    <input type="file"
                                           name="nbi_clearance_file"
                                           accept=".jpg,.jpeg,.png,.webp,.pdf"
                                           @change="nbiFileName = $event.target.files[0]?.name || ''"
                                           class="hidden">
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-3 pt-3 border-t border-border-subtle">
                        <button type="button"
                                @click="editCourierModalOpen = false"
                                class="px-5 py-2.5 rounded-xl bg-surface-subtle hover:bg-gray-200 text-text-main font-bold cursor-pointer transition-colors">
                            Cancel
                        </button>
                        <button type="submit"
                                :disabled="isSubmittingEdit"
                                class="px-6 py-2.5 rounded-xl bg-primary-dark hover:bg-primary text-white font-extrabold shadow-md cursor-pointer transition-all disabled:opacity-50">
                            <span x-show="!isSubmittingEdit">Save Courier Profile</span>
                            <span x-show="isSubmittingEdit" x-cloak>Saving...</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </template>

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