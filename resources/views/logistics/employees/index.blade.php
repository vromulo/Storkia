@extends('layouts.logistics', ['title' => 'Storkia - Employee Management'])

@section('content')
<div x-data="{
    hubBarangaysMap: @js($hubBarangaysMap),
    courierModalOpen: false,
    editingCourierId: null,
    editingCourierName: '',
    selectedCourierHubId: '',
    courierRemarks: '',
    riderModalOpen: false,
    editingRiderId: null,
    editingRiderName: '',
    selectedRiderHubId: '',
    selectedRiderBarangays: [],
    riderRemarks: '',
    editCourierProfileOpen: false,
    editingCourierProfile: null,
    editRiderProfileOpen: false,
    editingRiderProfile: null,
    avatarPreview: '',
    avatarFileName: '',
    licenseFileName: '',
    orCrFileName: '',
    nbiFileName: '',
    isSubmittingProfile: false,
    openCourierReassign(id, name, currentHubId) {
        this.editingCourierId = id;
        this.editingCourierName = name;
        this.selectedCourierHubId = String(currentHubId || '');
        this.courierRemarks = '';
        this.courierModalOpen = true;
    },
    openRiderReassign(id, name, currentHubId, currentBarangays) {
        this.editingRiderId = id;
        this.editingRiderName = name;
        this.selectedRiderHubId = String(currentHubId || '');
        this.selectedRiderBarangays = Array.isArray(currentBarangays) ? [...currentBarangays] : [];
        this.riderRemarks = '';
        this.riderModalOpen = true;
    },
    openEditCourierProfile(data) {
        this.editingCourierProfile = JSON.parse(JSON.stringify(data));
        this.avatarPreview = data.avatar_url || '';
        this.avatarFileName = '';
        this.licenseFileName = '';
        this.orCrFileName = '';
        this.nbiFileName = '';
        this.isSubmittingProfile = false;
        this.editCourierProfileOpen = true;
    },
    openEditRiderProfile(data) {
        this.editingRiderProfile = JSON.parse(JSON.stringify(data));
        this.avatarPreview = data.avatar_url || '';
        this.avatarFileName = '';
        this.licenseFileName = '';
        this.orCrFileName = '';
        this.isSubmittingProfile = false;
        this.editRiderProfileOpen = true;
    },
    onAvatarFileChange(event) {
        const file = event.target.files[0];
        if (!file) return;
        this.avatarFileName = file.name;
        const reader = new FileReader();
        reader.onload = (e) => {
            this.avatarPreview = e.target.result;
        };
        reader.readAsDataURL(file);
    },
    availableBarangaysForSelectedHub() {
        return this.hubBarangaysMap[this.selectedRiderHubId] || [];
    },
    onRiderHubChange() {
        this.selectedRiderBarangays = [];
    }
}" class="max-w-7xl mx-auto space-y-6 select-none">

    <div class="flex flex-col lg:flex-row lg:items-end justify-between gap-4">
        <div>
            <div class="flex items-center gap-2.5">
                <h1 class="text-3xl font-bold text-primary-dark">Employee Management</h1>
                <span class="px-3 py-1 rounded-full text-xs font-bold bg-brand-light/60 text-primary-dark border border-border-subtle">
                    {{ $isMainAccount ? $profile->business_name . ' (Company-Wide)' : ($activeBranchHub->hub_name ?? 'Branch Hub') }}
                </span>
            </div>
            <p class="text-sm text-text-muted mt-1">
                Create Courier and Rider accounts with internal <strong>@storkia.shop</strong> credentials, manage profile photos &amp; vehicle details, assign Couriers by Municipality Hub, and assign Riders by Barangay coverage.
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-2.5">
            <a href="{{ route('logistics.couriers.create') }}"
               class="inline-flex items-center gap-2 px-4 py-2.5 rounded-2xl bg-surface border border-border-subtle hover:border-primary text-text-main text-xs font-bold transition-all cursor-pointer">
                + Create Courier Account
            </a>
            <a href="{{ route('rider.register') }}"
               class="inline-flex items-center gap-2 px-5 py-2.5 rounded-2xl bg-primary-dark hover:bg-text-main text-surface text-xs font-bold shadow-xs transition-all cursor-pointer">
                + Create Rider Account
            </a>
        </div>
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

    <div class="bg-surface rounded-3xl border border-border-subtle p-5 shadow-xs flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div class="flex flex-wrap items-center gap-2">
            @if($isMainAccount)
                <a href="{{ route('logistics.employees.index', array_filter(['tab' => $tab, 'search' => $search])) }}"
                   class="px-3.5 py-2 rounded-xl text-xs font-bold border transition-all {{ !$selectedHubId ? 'bg-primary-dark text-white border-primary-dark' : 'bg-surface-subtle text-text-muted border-border-subtle hover:text-text-main' }}">
                    All Company Hubs ({{ $companyHubs->count() }})
                </a>
            @endif
            @foreach($companyHubs as $h)
                <a href="{{ route('logistics.employees.index', array_filter(['hub_id' => $h->id, 'tab' => $tab, 'search' => $search])) }}"
                   class="px-3.5 py-2 rounded-xl text-xs font-bold border transition-all {{ (int) $selectedHubId === (int) $h->id ? 'bg-primary text-white border-primary' : 'bg-surface-subtle text-text-muted border-border-subtle hover:text-text-main' }}">
                    {{ $h->is_main_hub ? 'Main Hub' : 'Branch' }}: {{ $h->municipality }}
                </a>
            @endforeach
        </div>

        <form method="GET" action="{{ route('logistics.employees.index') }}" class="flex items-center gap-2">
            @if($selectedHubId)
                <input type="hidden" name="hub_id" value="{{ $selectedHubId }}">
            @endif
            <input type="text"
                   name="search"
                   value="{{ $search }}"
                   placeholder="Search employee name, @storkia.shop email, plate..."
                   class="px-4 py-2 rounded-xl border border-border-subtle bg-surface text-xs text-text-main outline-none focus:border-primary w-64">
            <button type="submit" class="px-4 py-2 rounded-xl bg-primary-dark text-surface text-xs font-bold cursor-pointer">
                Filter
            </button>
        </form>
    </div>

    <!-- First-Mile Couriers Table -->
    <div class="bg-surface rounded-3xl border border-border-subtle shadow-xs overflow-hidden">
        <div class="p-5 border-b border-border-subtle bg-surface-subtle/50 flex items-center justify-between">
            <div>
                <h2 class="text-base font-bold text-primary-dark">First-Mile Couriers — Assigned by Municipality Hub ({{ $couriers->count() }})</h2>
                <p class="text-xs text-text-muted">Couriers receive seller pickup tasks according to the Municipality served by their assigned hub.</p>
            </div>
        </div>

        @if($couriers->isEmpty())
            <div class="p-10 text-center text-xs text-text-muted">
                No couriers found for the selected hub filter.
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-xs">
                    <thead>
                        <tr class="border-b border-border-subtle bg-surface-subtle/40 text-text-muted uppercase text-[11px] font-bold">
                            <th class="py-3.5 px-5">Courier &amp; Internal Email</th>
                            <th class="py-3.5 px-4">Vehicle &amp; Capacity</th>
                            <th class="py-3.5 px-4">Assigned Municipality Hub</th>
                            <th class="py-3.5 px-4">Status</th>
                            <th class="py-3.5 px-5 text-right w-28">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border-subtle">
                        @foreach($couriers as $courier)
                            @php
                                $cHub = $courier->assigned_hub;
                                $courierProfileData = [
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
                            <tr class="hover:bg-surface-subtle/30">
                                <td class="py-4 px-5 align-top">
                                    <div class="flex items-start gap-3">
                                        <div class="w-10 h-10 rounded-full bg-brand-light/40 border border-border-subtle overflow-hidden flex items-center justify-center shrink-0 font-serif font-bold text-primary-dark text-sm">
                                            @if($courier->avatar_url)
                                                <img src="{{ $courier->avatar_url }}" alt="{{ $courier->full_name }}" class="w-full h-full object-cover">
                                            @else
                                                {{ strtoupper(substr($courier->first_name ?? 'C', 0, 1)) }}
                                            @endif
                                        </div>
                                        <div>
                                            <div class="font-bold text-sm text-text-main">{{ $courier->full_name }}</div>
                                            <div class="font-mono text-primary-dark mt-0.5">{{ $courier->email }}</div>
                                            <div class="text-text-muted">{{ $courier->contact_no }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="py-4 px-4 align-top">
                                    <div class="font-bold text-text-main">{{ $courier->vehicle_type }}</div>
                                    <div class="font-mono text-text-muted">{{ $courier->plate_number }} &bull; {{ number_format($courier->load_capacity) }} kg</div>
                                </td>
                                <td class="py-4 px-4 align-top">
                                    @if($cHub)
                                        <span class="px-2.5 py-1 rounded-lg text-[11px] font-bold bg-brand-light/50 text-primary-dark border border-border-subtle inline-block">
                                            {{ $cHub->display_type }} — {{ $cHub->municipality }}
                                        </span>
                                        <div class="text-[11px] text-text-muted mt-1">{{ $cHub->hub_name }}</div>
                                    @else
                                        <span class="text-text-muted italic">Unassigned</span>
                                    @endif
                                </td>
                                <td class="py-4 px-4 align-top">
                                    <span class="px-2.5 py-1 rounded-full text-[11px] font-bold uppercase {{ $courier->status === 'active' ? 'bg-green-100 text-green-700' : ($courier->status === 'suspended' ? 'bg-red-100 text-red-700' : 'bg-yellow-100 text-yellow-800') }}">
                                        {{ str_replace('_', ' ', $courier->status) }}
                                    </span>
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
                                            if (spaceBelow < 185) {
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
                                                 class="w-52 bg-surface rounded-2xl shadow-2xl border-2 border-border-subtle overflow-hidden divide-y divide-border-subtle/60 text-xs select-none">
                                                <button type="button"
                                                        @click="rowMenuOpen = false; openEditCourierProfile(@js($courierProfileData))"
                                                        class="w-full text-left px-4 py-2.5 font-bold text-primary-dark hover:bg-brand-light/25 flex items-center gap-2 cursor-pointer">
                                                    <svg class="w-4 h-4 text-primary shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                    </svg>
                                                    Edit Profile &amp; Photo
                                                </button>

                                                <button type="button"
                                                        @click="rowMenuOpen = false; openCourierReassign({{ $courier->id }}, '{{ addslashes($courier->full_name) }}', '{{ $cHub?->id }}')"
                                                        class="w-full text-left px-4 py-2.5 font-semibold text-text-main hover:bg-surface-subtle flex items-center gap-2 cursor-pointer">
                                                    Reassign Municipality Hub
                                                </button>

                                                <form action="{{ route('logistics.employees.toggle-status') }}" method="POST" class="m-0">
                                                    @csrf
                                                    @method('PATCH')
                                                    <input type="hidden" name="employee_type" value="courier">
                                                    <input type="hidden" name="employee_id" value="{{ $courier->id }}">
                                                    <input type="hidden" name="status" value="{{ $courier->status === 'suspended' ? 'active' : 'suspended' }}">
                                                    <button type="submit"
                                                            class="w-full text-left px-4 py-2.5 font-bold cursor-pointer {{ $courier->status === 'suspended' ? 'text-green-700 hover:bg-green-50' : 'text-danger hover:bg-red-50' }}">
                                                        {{ $courier->status === 'suspended' ? 'Activate Courier' : 'Deactivate / Suspend' }}
                                                    </button>
                                                </form>
                                            </div>
                                        </template>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    <!-- Last-Mile Delivery Riders Table -->
    <div class="bg-surface rounded-3xl border border-border-subtle shadow-xs overflow-hidden">
        <div class="p-5 border-b border-border-subtle bg-surface-subtle/50 flex items-center justify-between">
            <div>
                <h2 class="text-base font-bold text-primary-dark">Last-Mile Delivery Riders — Assigned by Hub &amp; Barangay Coverage ({{ $riders->count() }})</h2>
                <p class="text-xs text-text-muted">Riders cover specific Barangays within the Municipality served by their assigned hub.</p>
            </div>
        </div>

        @if($riders->isEmpty())
            <div class="p-10 text-center text-xs text-text-muted">
                No riders found for the selected hub filter.
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-xs">
                    <thead>
                        <tr class="border-b border-border-subtle bg-surface-subtle/40 text-text-muted uppercase text-[11px] font-bold">
                            <th class="py-3.5 px-5">Rider &amp; Internal Email</th>
                            <th class="py-3.5 px-4">Vehicle &amp; License</th>
                            <th class="py-3.5 px-4">Assigned Hub (Municipality)</th>
                            <th class="py-3.5 px-4">Assigned Barangay Coverage</th>
                            <th class="py-3.5 px-4">Status</th>
                            <th class="py-3.5 px-5 text-right w-28">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border-subtle">
                        @foreach($riders as $rider)
                            @php
                                $rHub = $rider->assigned_hub;
                                $coveredBarangays = $rider->covered_barangay_names;
                                $riderProfileData = [
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
                            <tr class="hover:bg-surface-subtle/30">
                                <td class="py-4 px-5 align-top">
                                    <div class="flex items-start gap-3">
                                        <div class="w-10 h-10 rounded-full bg-brand-light/40 border border-border-subtle overflow-hidden flex items-center justify-center shrink-0 font-serif font-bold text-primary-dark text-sm">
                                            @if($rider->avatar_url)
                                                <img src="{{ $rider->avatar_url }}" alt="{{ $rider->full_name }}" class="w-full h-full object-cover">
                                            @else
                                                {{ strtoupper(substr($rider->first_name ?? 'R', 0, 1)) }}
                                            @endif
                                        </div>
                                        <div>
                                            <div class="font-bold text-sm text-text-main">{{ $rider->full_name }}</div>
                                            <div class="font-mono text-primary-dark mt-0.5">{{ $rider->email }}</div>
                                            <div class="text-text-muted">{{ $rider->contact_no }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="py-4 px-4 align-top">
                                    <div class="font-bold text-text-main">{{ $rider->vehicle_type }}</div>
                                    <div class="font-mono text-text-muted">{{ $rider->plate_number }} &bull; {{ $rider->license_no }}</div>
                                </td>
                                <td class="py-4 px-4 align-top">
                                    @if($rHub)
                                        <span class="px-2.5 py-1 rounded-lg text-[11px] font-bold bg-brand-light/50 text-primary-dark border border-border-subtle inline-block">
                                            {{ $rHub->display_type }} — {{ $rHub->municipality }}
                                        </span>
                                        <div class="text-[11px] text-text-muted mt-1">{{ $rHub->hub_name }}</div>
                                    @else
                                        <span class="text-text-muted italic">Unassigned</span>
                                    @endif
                                </td>
                                <td class="py-4 px-4 align-top max-w-xs">
                                    @if(!empty($coveredBarangays))
                                        <div class="flex flex-wrap gap-1">
                                            @foreach($coveredBarangays as $brgy)
                                                <span class="px-2 py-0.5 rounded-md bg-blue-50 text-blue-800 border border-blue-200 text-[11px] font-semibold">
                                                    {{ $brgy }}
                                                </span>
                                            @endforeach
                                        </div>
                                    @else
                                        <span class="text-text-muted italic">No specific barangay assigned yet</span>
                                    @endif
                                </td>
                                <td class="py-4 px-4 align-top">
                                    <span class="px-2.5 py-1 rounded-full text-[11px] font-bold uppercase {{ strtolower($rider->status) === 'approved' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">
                                        {{ strtolower($rider->status) === 'approved' ? 'Active' : 'Inactive' }}
                                    </span>
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
                                            if (spaceBelow < 185) {
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
                                                        @click="rowMenuOpen = false; openEditRiderProfile(@js($riderProfileData))"
                                                        class="w-full text-left px-4 py-2.5 font-bold text-primary-dark hover:bg-brand-light/25 flex items-center gap-2 cursor-pointer">
                                                    <svg class="w-4 h-4 text-primary shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                    </svg>
                                                    Edit Profile &amp; Photo
                                                </button>

                                                <button type="button"
                                                        @click="rowMenuOpen = false; openRiderReassign({{ $rider->id }}, '{{ addslashes($rider->full_name) }}', '{{ $rHub?->id }}', {{ json_encode($coveredBarangays) }})"
                                                        class="w-full text-left px-4 py-2.5 font-semibold text-text-main hover:bg-surface-subtle flex items-center gap-2 cursor-pointer">
                                                    Assign Hub &amp; Barangays
                                                </button>

                                                <form action="{{ route('logistics.employees.toggle-status') }}" method="POST" class="m-0">
                                                    @csrf
                                                    @method('PATCH')
                                                    <input type="hidden" name="employee_type" value="rider">
                                                    <input type="hidden" name="employee_id" value="{{ $rider->id }}">
                                                    <input type="hidden" name="status" value="{{ strtolower($rider->status) === 'approved' ? 'rejected' : 'approved' }}">
                                                    <button type="submit"
                                                            class="w-full text-left px-4 py-2.5 font-bold cursor-pointer {{ strtolower($rider->status) !== 'approved' ? 'text-green-700 hover:bg-green-50' : 'text-danger hover:bg-red-50' }}">
                                                        {{ strtolower($rider->status) !== 'approved' ? 'Activate Rider' : 'Deactivate Rider' }}
                                                    </button>
                                                </form>
                                            </div>
                                        </template>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    <!-- Reassign Courier Hub Modal -->
    <template x-teleport="body">
        <div x-show="courierModalOpen" x-cloak class="fixed inset-0 z-[190] flex items-center justify-center p-4">
            <div @click="courierModalOpen = false" class="absolute inset-0 bg-black/65 backdrop-blur-xs"></div>
            <div class="relative z-10 bg-surface rounded-3xl max-w-md w-full p-6 shadow-2xl border-2 border-border-subtle space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-border-subtle">
                    <h3 class="text-base font-extrabold text-primary-dark">Assign Courier Municipality Hub</h3>
                    <button type="button" @click="courierModalOpen = false" class="p-1 text-text-muted hover:text-text-main cursor-pointer">&times;</button>
                </div>

                <form :action="`{{ url('/logistics/employees/couriers') }}/${editingCourierId}/reassign`" method="POST" class="space-y-4 text-xs">
                    @csrf
                    <p class="text-text-muted">Courier: <strong class="text-text-main" x-text="editingCourierName"></strong></p>

                    <div>
                        <label class="block font-extrabold text-text-main uppercase text-[11px] mb-1.5">Select Company Hub (Municipality) <span class="text-danger">*</span></label>
                        <select name="logistics_hub_id" x-model="selectedCourierHubId" required class="w-full px-3.5 py-2.5 rounded-xl border-2 border-gray-300 focus:border-primary bg-white font-bold">
                            @foreach($companyHubs as $h)
                                <option value="{{ $h->id }}">{{ $h->display_type }} — {{ $h->municipality }} ({{ $h->hub_name }})</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block font-extrabold text-text-main uppercase text-[11px] mb-1.5">Assignment Remarks (Optional)</label>
                        <input type="text" name="remarks" x-model="courierRemarks" placeholder="Reason for hub assignment..." class="w-full px-3.5 py-2.5 rounded-xl border-2 border-gray-300 focus:border-primary bg-white font-semibold">
                    </div>

                    <div class="flex justify-end gap-2 pt-2 border-t border-border-subtle">
                        <button type="button" @click="courierModalOpen = false" class="px-4 py-2.5 rounded-xl bg-surface-subtle text-text-main font-bold cursor-pointer">Cancel</button>
                        <button type="submit" class="px-5 py-2.5 rounded-xl bg-primary-dark hover:bg-primary text-white font-extrabold shadow-sm cursor-pointer">Save Courier Hub</button>
                    </div>
                </form>
            </div>
        </div>
    </template>

    <!-- Reassign Rider Hub & Barangays Modal -->
    <template x-teleport="body">
        <div x-show="riderModalOpen" x-cloak class="fixed inset-0 z-[190] flex items-center justify-center p-4">
            <div @click="riderModalOpen = false" class="absolute inset-0 bg-black/65 backdrop-blur-xs"></div>
            <div class="relative z-10 bg-surface rounded-3xl max-w-lg w-full p-6 shadow-2xl border-2 border-border-subtle space-y-4 max-h-[90vh] overflow-y-auto custom-scrollbar">
                <div class="flex items-center justify-between pb-3 border-b border-border-subtle">
                    <h3 class="text-base font-extrabold text-primary-dark">Assign Rider Hub &amp; Barangay Coverage</h3>
                    <button type="button" @click="riderModalOpen = false" class="p-1 text-text-muted hover:text-text-main cursor-pointer">&times;</button>
                </div>

                <form :action="`{{ url('/logistics/employees/riders') }}/${editingRiderId}/reassign`" method="POST" class="space-y-4 text-xs">
                    @csrf
                    <p class="text-text-muted">Rider: <strong class="text-text-main" x-text="editingRiderName"></strong></p>

                    <div>
                        <label class="block font-extrabold text-text-main uppercase text-[11px] mb-1.5">1. Select Company Hub <span class="text-danger">*</span></label>
                        <select name="logistics_hub_id"
                                x-model="selectedRiderHubId"
                                @change="onRiderHubChange()"
                                required
                                class="w-full px-3.5 py-2.5 rounded-xl border-2 border-gray-300 focus:border-primary bg-white font-bold">
                            @foreach($companyHubs as $h)
                                <option value="{{ $h->id }}">{{ $h->display_type }} — {{ $h->municipality }} ({{ $h->hub_name }})</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <div class="flex items-center justify-between mb-1.5">
                            <label class="block font-extrabold text-text-main uppercase text-[11px]">2. Select Barangays in Hub Municipality <span class="text-danger">*</span></label>
                            <span class="text-[11px] text-primary font-extrabold" x-text="selectedRiderBarangays.length + ' selected'"></span>
                        </div>
                        <div class="max-h-52 overflow-y-auto border-2 border-gray-300 rounded-2xl p-3 bg-surface-subtle/50 grid grid-cols-1 sm:grid-cols-2 gap-2 custom-scrollbar">
                            <template x-for="brgy in availableBarangaysForSelectedHub()" :key="brgy.code || brgy.name">
                                <label class="flex items-center gap-2 p-2.5 rounded-xl bg-white border border-border-subtle hover:border-primary cursor-pointer">
                                    <input type="checkbox"
                                           name="barangays[]"
                                           :value="brgy.name"
                                           x-model="selectedRiderBarangays"
                                           class="rounded text-primary focus:ring-primary">
                                    <span class="text-xs font-semibold text-text-main truncate" x-text="brgy.name"></span>
                                </label>
                            </template>
                        </div>
                    </div>

                    <div>
                        <label class="block font-extrabold text-text-main uppercase text-[11px] mb-1.5">Assignment Remarks (Optional)</label>
                        <input type="text" name="remarks" x-model="riderRemarks" placeholder="Coverage update notes..." class="w-full px-3.5 py-2.5 rounded-xl border-2 border-gray-300 focus:border-primary bg-white font-semibold">
                    </div>

                    <div class="flex justify-end gap-2 pt-2 border-t border-border-subtle">
                        <button type="button" @click="riderModalOpen = false" class="px-4 py-2.5 rounded-xl bg-surface-subtle text-text-main font-bold cursor-pointer">Cancel</button>
                        <button type="submit" class="px-5 py-2.5 rounded-xl bg-primary-dark hover:bg-primary text-white font-extrabold shadow-sm cursor-pointer">Save Rider Coverage</button>
                    </div>
                </form>
            </div>
        </div>
    </template>

    <!-- Enhanced Edit Courier Profile Modal -->
    <template x-teleport="body">
        <div x-show="editCourierProfileOpen && editingCourierProfile" x-cloak class="fixed inset-0 z-[195] flex items-center justify-center p-4">
            <div @click="editCourierProfileOpen = false" class="absolute inset-0 bg-black/65 backdrop-blur-xs cursor-pointer"></div>
            <div @click.stop class="relative z-10 bg-surface rounded-3xl max-w-2xl w-full p-6 sm:p-8 shadow-2xl border-2 border-border-subtle space-y-6 max-h-[92vh] overflow-y-auto custom-scrollbar">
                <div class="flex items-start justify-between gap-4 pb-4 border-b border-border-subtle">
                    <div>
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[10px] font-extrabold uppercase tracking-wider bg-primary/10 text-primary mb-1">
                            First-Mile Fleet Record
                        </span>
                        <h3 class="text-xl font-extrabold text-primary-dark">Edit Courier Profile &amp; Photo</h3>
                        <p class="text-xs text-text-muted mt-0.5">Personal identity details are locked. Upload a new profile photo, update vehicle/license details, or replace documents.</p>
                    </div>
                    <button type="button" @click="editCourierProfileOpen = false" class="p-2 rounded-full bg-surface-subtle hover:bg-red-50 text-text-muted hover:text-danger transition-colors cursor-pointer">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <form :action="`{{ url('/logistics/couriers') }}/${editingCourierProfile?.id}/profile`"
                      method="POST"
                      enctype="multipart/form-data"
                      @submit="isSubmittingProfile = true"
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
                                    <span x-text="(editingCourierProfile?.first_name || 'C').charAt(0).toUpperCase()"></span>
                                </template>
                            </div>
                            <div class="space-y-1">
                                <span class="px-2 py-0.5 rounded-md bg-primary text-white text-[10px] font-extrabold uppercase tracking-wider">Profile Photo</span>
                                <h4 class="text-sm font-extrabold text-text-main">Courier Avatar Image</h4>
                                <p class="text-[11px] text-text-muted" x-text="avatarFileName ? 'Selected: ' + avatarFileName : 'JPG, PNG, or WEBP up to 5 MB'"></p>
                            </div>
                        </div>

                        <label class="inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl bg-primary-dark hover:bg-primary text-white font-extrabold text-xs shadow-sm transition-all cursor-pointer active:scale-95 shrink-0">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                            </svg>
                            <span x-text="avatarFileName ? 'Change Chosen Photo' : 'Choose Photo'"></span>
                            <input type="file" name="avatar" accept="image/jpeg,image/png,image/webp" @change="onAvatarFileChange($event)" class="hidden">
                        </label>
                    </div>

                    <!-- Locked Identity Details -->
                    <div class="p-4 rounded-2xl bg-gray-100/90 border border-gray-300 grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <span class="text-[10px] font-extrabold text-gray-500 uppercase block">Full Name (Read-Only)</span>
                            <div class="font-bold text-gray-700 mt-1 px-3 py-2 rounded-xl bg-gray-200/70 border border-gray-300" x-text="editingCourierProfile?.full_name"></div>
                        </div>
                        <div>
                            <span class="text-[10px] font-extrabold text-gray-500 uppercase block">Email &amp; Contact (Read-Only)</span>
                            <div class="font-mono text-gray-700 mt-1 px-3 py-2 rounded-xl bg-gray-200/70 border border-gray-300 truncate" x-text="(editingCourierProfile?.email || '') + ' • ' + (editingCourierProfile?.contact_no || '')"></div>
                        </div>
                    </div>

                    <!-- Emphasized Editable Vehicle & License Inputs -->
                    <div class="p-5 rounded-2xl bg-white border-2 border-primary/25 shadow-2xs space-y-4">
                        <div class="flex items-center justify-between border-b border-border-subtle pb-2.5">
                            <span class="text-xs font-extrabold uppercase tracking-wider text-primary-dark">Editable Vehicle &amp; License Credentials</span>
                            <span class="text-[11px] font-bold text-primary">Required Fields *</span>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block font-extrabold text-text-main uppercase text-[11px] mb-1.5">Vehicle Type <span class="text-danger">*</span></label>
                                <select name="vehicle_type" x-model="editingCourierProfile.vehicle_type" required class="w-full px-3.5 py-2.5 rounded-xl border-2 border-gray-300 hover:border-primary/60 focus:border-primary focus:ring-4 focus:ring-primary/10 bg-white font-bold text-xs outline-none cursor-pointer">
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
                                <input type="number" min="50" max="20000" name="load_capacity" x-model="editingCourierProfile.load_capacity" required class="w-full px-3.5 py-2.5 rounded-xl border-2 border-gray-300 hover:border-primary/60 focus:border-primary focus:ring-4 focus:ring-primary/10 bg-white font-extrabold text-xs outline-none">
                            </div>
                            <div>
                                <label class="block font-extrabold text-text-main uppercase text-[11px] mb-1.5">Plate Number <span class="text-danger">*</span></label>
                                <input type="text" name="plate_number" x-model="editingCourierProfile.plate_number" required maxlength="20" class="w-full px-3.5 py-2.5 rounded-xl border-2 border-gray-300 hover:border-primary/60 focus:border-primary focus:ring-4 focus:ring-primary/10 bg-white font-mono uppercase font-extrabold text-primary-dark text-xs outline-none">
                            </div>
                            <div>
                                <label class="block font-extrabold text-text-main uppercase text-[11px] mb-1.5">License No. <span class="text-danger">*</span></label>
                                <input type="text" name="license_no" x-model="editingCourierProfile.license_no" required maxlength="35" class="w-full px-3.5 py-2.5 rounded-xl border-2 border-gray-300 hover:border-primary/60 focus:border-primary focus:ring-4 focus:ring-primary/10 bg-white font-mono uppercase font-extrabold text-xs outline-none">
                            </div>
                            <div class="sm:col-span-2">
                                <label class="block font-extrabold text-text-main uppercase text-[11px] mb-1.5">License Expiry Date <span class="text-danger">*</span></label>
                                <input type="date" name="license_expiry_date" x-model="editingCourierProfile.license_expiry_date" required class="w-full px-3.5 py-2.5 rounded-xl border-2 border-gray-300 hover:border-primary/60 focus:border-primary focus:ring-4 focus:ring-primary/10 bg-white font-bold text-xs outline-none cursor-pointer">
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
                                    <span x-text="licenseFileName ? 'Change File' : 'Choose File'"></span>
                                    <input type="file" name="license_file" accept=".jpg,.jpeg,.png,.webp,.pdf" @change="licenseFileName = $event.target.files[0]?.name || ''" class="hidden">
                                </label>
                            </div>

                            <div class="p-3.5 rounded-2xl bg-white border-2 border-dashed border-primary/35 hover:border-primary transition-all flex flex-col justify-between gap-2.5">
                                <div>
                                    <span class="text-[11px] font-extrabold text-text-main block">Vehicle OR/CR</span>
                                    <span class="text-[10px] text-text-muted block mt-0.5 truncate" x-text="orCrFileName ? 'Selected: ' + orCrFileName : 'Replace OR/CR file'"></span>
                                </div>
                                <label class="inline-flex items-center justify-center gap-1.5 px-3 py-2 rounded-xl bg-primary/10 hover:bg-primary text-primary hover:text-white font-extrabold text-xs border border-primary/30 transition-all cursor-pointer w-full text-center">
                                    <span x-text="orCrFileName ? 'Change File' : 'Choose File'"></span>
                                    <input type="file" name="or_cr_file" accept=".jpg,.jpeg,.png,.webp,.pdf" @change="orCrFileName = $event.target.files[0]?.name || ''" class="hidden">
                                </label>
                            </div>

                            <div class="p-3.5 rounded-2xl bg-white border-2 border-dashed border-primary/35 hover:border-primary transition-all flex flex-col justify-between gap-2.5">
                                <div>
                                    <span class="text-[11px] font-extrabold text-text-main block">NBI Clearance</span>
                                    <span class="text-[10px] text-text-muted block mt-0.5 truncate" x-text="nbiFileName ? 'Selected: ' + nbiFileName : 'Replace NBI file'"></span>
                                </div>
                                <label class="inline-flex items-center justify-center gap-1.5 px-3 py-2 rounded-xl bg-primary/10 hover:bg-primary text-primary hover:text-white font-extrabold text-xs border border-primary/30 transition-all cursor-pointer w-full text-center">
                                    <span x-text="nbiFileName ? 'Change File' : 'Choose File'"></span>
                                    <input type="file" name="nbi_clearance_file" accept=".jpg,.jpeg,.png,.webp,.pdf" @change="nbiFileName = $event.target.files[0]?.name || ''" class="hidden">
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end gap-3 pt-3 border-t border-border-subtle">
                        <button type="button" @click="editCourierProfileOpen = false" class="px-5 py-2.5 rounded-xl bg-surface-subtle hover:bg-gray-200 text-text-main font-bold cursor-pointer">Cancel</button>
                        <button type="submit" :disabled="isSubmittingProfile" class="px-6 py-2.5 rounded-xl bg-primary-dark hover:bg-primary text-white font-extrabold shadow-md cursor-pointer">Save Courier Profile</button>
                    </div>
                </form>
            </div>
        </div>
    </template>

    <!-- Enhanced Edit Rider Profile Modal -->
    <template x-teleport="body">
        <div x-show="editRiderProfileOpen && editingRiderProfile" x-cloak class="fixed inset-0 z-[195] flex items-center justify-center p-4">
            <div @click="editRiderProfileOpen = false" class="absolute inset-0 bg-black/65 backdrop-blur-xs cursor-pointer"></div>
            <div @click.stop class="relative z-10 bg-surface rounded-3xl max-w-2xl w-full p-6 sm:p-8 shadow-2xl border-2 border-border-subtle space-y-6 max-h-[92vh] overflow-y-auto custom-scrollbar">
                <div class="flex items-start justify-between gap-4 pb-4 border-b border-border-subtle">
                    <div>
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[10px] font-extrabold uppercase tracking-wider bg-primary/10 text-primary mb-1">
                            Last-Mile Fleet Record
                        </span>
                        <h3 class="text-xl font-extrabold text-primary-dark">Edit Rider Profile &amp; Photo</h3>
                        <p class="text-xs text-text-muted mt-0.5">Personal identity details are locked. Upload a new profile photo, update vehicle/license details, or replace documents.</p>
                    </div>
                    <button type="button" @click="editRiderProfileOpen = false" class="p-2 rounded-full bg-surface-subtle hover:bg-red-50 text-text-muted hover:text-danger transition-colors cursor-pointer">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <form :action="`{{ url('/logistics/riders') }}/${editingRiderProfile?.id}/profile`"
                      method="POST"
                      enctype="multipart/form-data"
                      @submit="isSubmittingProfile = true"
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
                                    <span x-text="(editingRiderProfile?.first_name || 'R').charAt(0).toUpperCase()"></span>
                                </template>
                            </div>
                            <div class="space-y-1">
                                <span class="px-2 py-0.5 rounded-md bg-primary text-white text-[10px] font-extrabold uppercase tracking-wider">Profile Photo</span>
                                <h4 class="text-sm font-extrabold text-text-main">Rider Avatar Image</h4>
                                <p class="text-[11px] text-text-muted" x-text="avatarFileName ? 'Selected: ' + avatarFileName : 'JPG, PNG, or WEBP up to 5 MB'"></p>
                            </div>
                        </div>

                        <label class="inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl bg-primary-dark hover:bg-primary text-white font-extrabold text-xs shadow-sm transition-all cursor-pointer active:scale-95 shrink-0">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                            </svg>
                            <span x-text="avatarFileName ? 'Change Chosen Photo' : 'Choose Photo'"></span>
                            <input type="file" name="avatar" accept="image/jpeg,image/png,image/webp" @change="onAvatarFileChange($event)" class="hidden">
                        </label>
                    </div>

                    <!-- Locked Identity Details -->
                    <div class="p-4 rounded-2xl bg-gray-100/90 border border-gray-300 grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <span class="text-[10px] font-extrabold text-gray-500 uppercase block">Full Name (Read-Only)</span>
                            <div class="font-bold text-gray-700 mt-1 px-3 py-2 rounded-xl bg-gray-200/70 border border-gray-300" x-text="editingRiderProfile?.full_name"></div>
                        </div>
                        <div>
                            <span class="text-[10px] font-extrabold text-gray-500 uppercase block">Email &amp; Contact (Read-Only)</span>
                            <div class="font-mono text-gray-700 mt-1 px-3 py-2 rounded-xl bg-gray-200/70 border border-gray-300 truncate" x-text="(editingRiderProfile?.email || '') + ' • ' + (editingRiderProfile?.contact_no || '')"></div>
                        </div>
                    </div>

                    <!-- Emphasized Editable Vehicle & License Inputs -->
                    <div class="p-5 rounded-2xl bg-white border-2 border-primary/25 shadow-2xs space-y-4">
                        <div class="flex items-center justify-between border-b border-border-subtle pb-2.5">
                            <span class="text-xs font-extrabold uppercase tracking-wider text-primary-dark">Editable Vehicle &amp; License Credentials</span>
                            <span class="text-[11px] font-bold text-primary">Required Fields *</span>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block font-extrabold text-text-main uppercase text-[11px] mb-1.5">Vehicle Type <span class="text-danger">*</span></label>
                                <select name="vehicle_type" x-model="editingRiderProfile.vehicle_type" required class="w-full px-3.5 py-2.5 rounded-xl border-2 border-gray-300 hover:border-primary/60 focus:border-primary focus:ring-4 focus:ring-primary/10 bg-white font-bold text-xs outline-none cursor-pointer">
                                    <option value="Motorcycle">Motorcycle</option>
                                    <option value="Bicycle / E-Bike">Bicycle / E-Bike</option>
                                    <option value="Tricycle / Three-Wheeler">Tricycle / Three-Wheeler</option>
                                    <option value="Sedan / Hatchback">Sedan / Hatchback</option>
                                    <option value="MPV / Utility Van">MPV / Utility Van</option>
                                </select>
                            </div>
                            <div>
                                <label class="block font-extrabold text-text-main uppercase text-[11px] mb-1.5">Plate Number <span class="text-danger">*</span></label>
                                <input type="text" name="plate_number" x-model="editingRiderProfile.plate_number" required maxlength="20" class="w-full px-3.5 py-2.5 rounded-xl border-2 border-gray-300 hover:border-primary/60 focus:border-primary focus:ring-4 focus:ring-primary/10 bg-white font-mono uppercase font-extrabold text-primary-dark text-xs outline-none">
                            </div>
                            <div>
                                <label class="block font-extrabold text-text-main uppercase text-[11px] mb-1.5">License No. <span class="text-danger">*</span></label>
                                <input type="text" name="license_no" x-model="editingRiderProfile.license_no" required maxlength="35" class="w-full px-3.5 py-2.5 rounded-xl border-2 border-gray-300 hover:border-primary/60 focus:border-primary focus:ring-4 focus:ring-primary/10 bg-white font-mono uppercase font-extrabold text-xs outline-none">
                            </div>
                            <div>
                                <label class="block font-extrabold text-text-main uppercase text-[11px] mb-1.5">License Expiry Date <span class="text-danger">*</span></label>
                                <input type="date" name="license_expiry_date" x-model="editingRiderProfile.license_expiry_date" required class="w-full px-3.5 py-2.5 rounded-xl border-2 border-gray-300 hover:border-primary/60 focus:border-primary focus:ring-4 focus:ring-primary/10 bg-white font-bold text-xs outline-none cursor-pointer">
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
                                    <span x-text="licenseFileName ? 'Change License File' : 'Choose License File'"></span>
                                    <input type="file" name="license_file" accept=".jpg,.jpeg,.png,.webp,.pdf" @change="licenseFileName = $event.target.files[0]?.name || ''" class="hidden">
                                </label>
                            </div>

                            <div class="p-3.5 rounded-2xl bg-white border-2 border-dashed border-primary/35 hover:border-primary transition-all flex flex-col justify-between gap-2.5">
                                <div>
                                    <span class="text-[11px] font-extrabold text-text-main block">Vehicle OR/CR Document</span>
                                    <span class="text-[10px] text-text-muted block mt-0.5 truncate" x-text="orCrFileName ? 'Selected: ' + orCrFileName : 'Keep current file or choose replacement'"></span>
                                </div>
                                <label class="inline-flex items-center justify-center gap-2 px-3.5 py-2 rounded-xl bg-primary/10 hover:bg-primary text-primary hover:text-white font-extrabold text-xs border border-primary/30 transition-all cursor-pointer w-full text-center">
                                    <span x-text="orCrFileName ? 'Change OR/CR File' : 'Choose OR/CR File'"></span>
                                    <input type="file" name="or_cr_file" accept=".jpg,.jpeg,.png,.webp,.pdf" @change="orCrFileName = $event.target.files[0]?.name || ''" class="hidden">
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end gap-3 pt-3 border-t border-border-subtle">
                        <button type="button" @click="editRiderProfileOpen = false" class="px-5 py-2.5 rounded-xl bg-surface-subtle hover:bg-gray-200 text-text-main font-bold cursor-pointer">Cancel</button>
                        <button type="submit" :disabled="isSubmittingProfile" class="px-6 py-2.5 rounded-xl bg-primary-dark hover:bg-primary text-white font-extrabold shadow-md cursor-pointer">Save Rider Profile</button>
                    </div>
                </form>
            </div>
        </div>
    </template>
</div>
@endsection