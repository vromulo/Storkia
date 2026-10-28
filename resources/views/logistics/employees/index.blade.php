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
                Create Courier and Rider accounts with internal <strong>@storkia.shop</strong> credentials, assign Couriers by Municipality Hub, and assign Riders by Barangay coverage.
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
                            <th class="py-3.5 px-5">Courier & Internal Email</th>
                            <th class="py-3.5 px-4">Vehicle & Capacity</th>
                            <th class="py-3.5 px-4">Assigned Municipality Hub</th>
                            <th class="py-3.5 px-4">Status</th>
                            <th class="py-3.5 px-5 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border-subtle">
                        @foreach($couriers as $courier)
                            @php
                                $cHub = $courier->assigned_hub;
                            @endphp
                            <tr class="hover:bg-surface-subtle/30">
                                <td class="py-4 px-5 align-top">
                                    <div class="font-bold text-sm text-text-main">{{ $courier->full_name }}</div>
                                    <div class="font-mono text-primary-dark mt-0.5">{{ $courier->email }}</div>
                                    <div class="text-text-muted">{{ $courier->contact_no }}</div>
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
                                    <div class="flex flex-wrap items-center justify-end gap-2">
                                        <button type="button"
                                                @click="openCourierReassign({{ $courier->id }}, '{{ addslashes($courier->full_name) }}', '{{ $cHub?->id }}')"
                                                class="px-3 py-1.5 rounded-xl bg-surface-subtle hover:bg-brand-light/40 border border-border-subtle text-text-main font-bold cursor-pointer">
                                            Reassign Hub
                                        </button>

                                        <form action="{{ route('logistics.employees.toggle-status') }}" method="POST" class="m-0">
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="employee_type" value="courier">
                                            <input type="hidden" name="employee_id" value="{{ $courier->id }}">
                                            <input type="hidden" name="status" value="{{ $courier->status === 'suspended' ? 'active' : 'suspended' }}">
                                            <button type="submit"
                                                    class="px-3 py-1.5 rounded-xl font-bold cursor-pointer {{ $courier->status === 'suspended' ? 'bg-green-600 text-white' : 'bg-red-50 text-danger border border-red-200' }}">
                                                {{ $courier->status === 'suspended' ? 'Activate' : 'Deactivate' }}
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    <div class="bg-surface rounded-3xl border border-border-subtle shadow-xs overflow-hidden">
        <div class="p-5 border-b border-border-subtle bg-surface-subtle/50 flex items-center justify-between">
            <div>
                <h2 class="text-base font-bold text-primary-dark">Last-Mile Delivery Riders — Assigned by Hub & Barangay Coverage ({{ $riders->count() }})</h2>
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
                            <th class="py-3.5 px-5">Rider & Internal Email</th>
                            <th class="py-3.5 px-4">Vehicle & License</th>
                            <th class="py-3.5 px-4">Assigned Hub (Municipality)</th>
                            <th class="py-3.5 px-4">Assigned Barangay Coverage</th>
                            <th class="py-3.5 px-4">Status</th>
                            <th class="py-3.5 px-5 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border-subtle">
                        @foreach($riders as $rider)
                            @php
                                $rHub = $rider->assigned_hub;
                                $coveredBarangays = $rider->covered_barangay_names;
                            @endphp
                            <tr class="hover:bg-surface-subtle/30">
                                <td class="py-4 px-5 align-top">
                                    <div class="font-bold text-sm text-text-main">{{ $rider->full_name }}</div>
                                    <div class="font-mono text-primary-dark mt-0.5">{{ $rider->email }}</div>
                                    <div class="text-text-muted">{{ $rider->contact_no }}</div>
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
                                    <span class="px-2.5 py-1 rounded-full text-[11px] font-bold uppercase {{ $rider->status === 'approved' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">
                                        {{ $rider->status === 'approved' ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                                <td class="py-4 px-5 align-top text-right">
                                    <div class="flex flex-wrap items-center justify-end gap-2">
                                        <button type="button"
                                                @click="openRiderReassign({{ $rider->id }}, '{{ addslashes($rider->full_name) }}', '{{ $rHub?->id }}', {{ json_encode($coveredBarangays) }})"
                                                class="px-3 py-1.5 rounded-xl bg-surface-subtle hover:bg-brand-light/40 border border-border-subtle text-text-main font-bold cursor-pointer">
                                            Assign Hub & Barangays
                                        </button>

                                        <form action="{{ route('logistics.employees.toggle-status') }}" method="POST" class="m-0">
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="employee_type" value="rider">
                                            <input type="hidden" name="employee_id" value="{{ $rider->id }}">
                                            <input type="hidden" name="status" value="{{ $rider->status === 'approved' ? 'rejected' : 'approved' }}">
                                            <button type="submit"
                                                    class="px-3 py-1.5 rounded-xl font-bold cursor-pointer {{ $rider->status !== 'approved' ? 'bg-green-600 text-white' : 'bg-red-50 text-danger border border-red-200' }}">
                                                {{ $rider->status !== 'approved' ? 'Activate' : 'Deactivate' }}
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    <template x-teleport="body">
        <div x-show="courierModalOpen" x-cloak class="fixed inset-0 z-[190] flex items-center justify-center p-4">
            <div @click="courierModalOpen = false" class="absolute inset-0 bg-black/60 backdrop-blur-xs"></div>
            <div class="relative z-10 bg-surface rounded-3xl max-w-md w-full p-6 shadow-2xl border border-border-subtle space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-border-subtle">
                    <h3 class="text-base font-bold text-primary-dark">Assign Courier Municipality Hub</h3>
                    <button type="button" @click="courierModalOpen = false" class="p-1 text-text-muted hover:text-text-main cursor-pointer">&times;</button>
                </div>

                <form :action="`{{ url('/logistics/employees/couriers') }}/${editingCourierId}/reassign`" method="POST" class="space-y-4 text-xs">
                    @csrf
                    <p class="text-text-muted">Courier: <strong class="text-text-main" x-text="editingCourierName"></strong></p>

                    <div>
                        <label class="block font-bold text-text-muted mb-1">Select Company Hub (Municipality) <span class="text-danger">*</span></label>
                        <select name="logistics_hub_id" x-model="selectedCourierHubId" required class="w-full p-3 rounded-xl border border-border-subtle bg-surface font-semibold">
                            @foreach($companyHubs as $h)
                                <option value="{{ $h->id }}">{{ $h->display_type }} — {{ $h->municipality }} ({{ $h->hub_name }})</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block font-bold text-text-muted mb-1">Assignment Remarks (Optional)</label>
                        <input type="text" name="remarks" x-model="courierRemarks" placeholder="Reason for hub assignment..." class="w-full p-3 rounded-xl border border-border-subtle bg-surface">
                    </div>

                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" @click="courierModalOpen = false" class="px-4 py-2 rounded-xl bg-surface-subtle text-text-muted font-bold cursor-pointer">Cancel</button>
                        <button type="submit" class="px-5 py-2 rounded-xl bg-primary-dark text-white font-bold cursor-pointer">Save Courier Hub</button>
                    </div>
                </form>
            </div>
        </div>
    </template>

    <template x-teleport="body">
        <div x-show="riderModalOpen" x-cloak class="fixed inset-0 z-[190] flex items-center justify-center p-4">
            <div @click="riderModalOpen = false" class="absolute inset-0 bg-black/60 backdrop-blur-xs"></div>
            <div class="relative z-10 bg-surface rounded-3xl max-w-lg w-full p-6 shadow-2xl border border-border-subtle space-y-4 max-h-[90vh] overflow-y-auto custom-scrollbar">
                <div class="flex items-center justify-between pb-3 border-b border-border-subtle">
                    <h3 class="text-base font-bold text-primary-dark">Assign Rider Hub & Barangay Coverage</h3>
                    <button type="button" @click="riderModalOpen = false" class="p-1 text-text-muted hover:text-text-main cursor-pointer">&times;</button>
                </div>

                <form :action="`{{ url('/logistics/employees/riders') }}/${editingRiderId}/reassign`" method="POST" class="space-y-4 text-xs">
                    @csrf
                    <p class="text-text-muted">Rider: <strong class="text-text-main" x-text="editingRiderName"></strong></p>

                    <div>
                        <label class="block font-bold text-text-muted mb-1">1. Select Company Hub <span class="text-danger">*</span></label>
                        <select name="logistics_hub_id"
                                x-model="selectedRiderHubId"
                                @change="onRiderHubChange()"
                                required
                                class="w-full p-3 rounded-xl border border-border-subtle bg-surface font-semibold">
                            @foreach($companyHubs as $h)
                                <option value="{{ $h->id }}">{{ $h->display_type }} — {{ $h->municipality }} ({{ $h->hub_name }})</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <div class="flex items-center justify-between mb-1.5">
                            <label class="block font-bold text-text-muted">2. Select Barangays in Hub Municipality <span class="text-danger">*</span></label>
                            <span class="text-[11px] text-primary font-bold" x-text="selectedRiderBarangays.length + ' selected'"></span>
                        </div>
                        <div class="max-h-52 overflow-y-auto border border-border-subtle rounded-2xl p-3 bg-surface-subtle/50 grid grid-cols-1 sm:grid-cols-2 gap-2 custom-scrollbar">
                            <template x-for="brgy in availableBarangaysForSelectedHub()" :key="brgy.code || brgy.name">
                                <label class="flex items-center gap-2 p-2 rounded-xl bg-surface border border-border-subtle hover:border-primary cursor-pointer">
                                    <input type="checkbox"
                                           name="barangays[]"
                                           :value="brgy.name"
                                           x-model="selectedRiderBarangays"
                                           class="rounded text-primary focus:ring-primary">
                                    <span class="text-xs font-medium text-text-main truncate" x-text="brgy.name"></span>
                                </label>
                            </template>
                        </div>
                    </div>

                    <div>
                        <label class="block font-bold text-text-muted mb-1">Assignment Remarks (Optional)</label>
                        <input type="text" name="remarks" x-model="riderRemarks" placeholder="Coverage update notes..." class="w-full p-3 rounded-xl border border-border-subtle bg-surface">
                    </div>

                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" @click="riderModalOpen = false" class="px-4 py-2 rounded-xl bg-surface-subtle text-text-muted font-bold cursor-pointer">Cancel</button>
                        <button type="submit" class="px-5 py-2 rounded-xl bg-primary-dark text-white font-bold cursor-pointer">Save Rider Coverage</button>
                    </div>
                </form>
            </div>
        </div>
    </template>

</div>
@endsection