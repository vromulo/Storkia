@extends('layouts.logistics', ['title' => 'Storkia - Create Rider Account'])

@section('content')
@php
    $isBranchAccount = $isBranchAccount ?? (auth()->user()?->isBranchLogisticsAccount() ?? false);
    $lockedBranchHub = $lockedBranchHub ?? ($isBranchAccount ? auth()->user()?->branchLogisticsHub : null);

    $hubsArray = $hubs->map(fn ($h) => [
        'id'           => (string) $h->id,
        'label'        => ($h->is_main_hub ? 'Main Hub' : 'Branch Hub') . ' — ' . $h->municipality . ' (' . $h->hub_name . ')',
        'municipality' => $h->municipality,
        'province'     => $h->province,
        'hub_name'     => $h->hub_name,
    ])->values();

    $defaultHubId = (string) ($lockedBranchHub?->id ?? ($hubs->first()?->id ?? ''));
    $defaultHubLabel = $hubsArray->firstWhere('id', $defaultHubId)['label'] ?? 'Select Company Hub';
@endphp

<div x-data="{
    emailPrefix: @js(old('email_prefix', '')),
    hubsOptions: @js($hubsArray),
    selectedHubId: @js((string) old('logistics_hub_id', $defaultHubId)),
    selectedHubLabel: @js($defaultHubLabel),
    hubDropdownOpen: false,
    vehicleDropdownOpen: false,
    selectedVehicleType: @js(old('vehicle_type', 'Motorcycle')),
    vehicleTypes: ['Motorcycle', 'Bicycle / E-Bike', 'Tricycle / Three-Wheeler', 'Sedan / Hatchback'],
    hubBarangaysMap: {{ json_encode($hubs->mapWithKeys(fn($h) => [$h->id => app(\App\Services\AddressService::class)->getBarangaysForLocation($h->province, $h->municipality, $h->municipality_code)])) }},
    selectedBarangays: @js(old('barangays', [])),
    sanitizePrefix() {
        this.emailPrefix = (this.emailPrefix || '').toLowerCase().replace(/@.*$/, '').replace(/[^a-z0-9._-]/g, '').slice(0, 40);
    },
    currentBarangays() {
        return this.hubBarangaysMap[this.selectedHubId] || [];
    },
    selectHub(hub) {
        this.selectedHubId = String(hub.id);
        this.selectedHubLabel = hub.label;
        this.hubDropdownOpen = false;
        this.selectedBarangays = [];
    }
}" class="max-w-3xl mx-auto select-none space-y-6">

    <div class="flex items-center justify-between gap-4">
        <a href="{{ route('logistics.rider-applications.index') }}"
           class="inline-flex items-center gap-2 px-4 py-2 rounded-2xl bg-surface border border-border-subtle hover:border-primary text-xs font-bold text-text-main transition-all cursor-pointer">
            <svg class="w-4 h-4 text-text-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
            </svg>
            Back to Rider Directory
        </a>

        <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-bold bg-brand-light/60 text-primary-dark border border-border-subtle">
            {{ $profile->business_name }}
            @if($isBranchAccount && $lockedBranchHub)
                &bull; {{ $lockedBranchHub->municipality }} Branch
            @endif
        </span>
    </div>

    <div class="bg-surface rounded-3xl border border-border-subtle shadow-xs p-8 sm:p-12">
        <div class="mb-8">
            <h1 class="text-2xl font-bold text-primary-dark">Create Last-Mile Rider Account</h1>
            <p class="text-xs text-text-muted mt-1">
                @if($isBranchAccount && $lockedBranchHub)
                    Branch Hub Mode: Riders created here are automatically locked and assigned to <strong>{{ $lockedBranchHub->hub_name }} ({{ $lockedBranchHub->municipality }})</strong>.
                @else
                    Main Hub Mode: Select which of your company hubs (Main Hub or Branch Hub) this rider will be registered under.
                @endif
            </p>
        </div>

        @if($errors->any())
            <div class="mb-6 p-4 rounded-2xl bg-red-50 border border-red-200 text-red-700 text-xs space-y-1">
                @foreach($errors->all() as $err)
                    <p class="font-semibold">{{ $err }}</p>
                @endforeach
            </div>
        @endif

        <form action="{{ route('logistics.riders.store') }}" method="POST" enctype="multipart/form-data" class="space-y-6 text-xs">
            @csrf
            <input type="hidden" name="logistics_hub_id" :value="selectedHubId">
            <input type="hidden" name="vehicle_type" :value="selectedVehicleType">

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block font-bold text-text-muted uppercase tracking-wider mb-1.5">
                        Internal Email Username <span class="text-danger">*</span>
                    </label>
                    <div class="flex items-center rounded-xl border border-border-subtle focus-within:border-primary overflow-hidden bg-surface">
                        <input type="text"
                               name="email_prefix"
                               x-model="emailPrefix"
                               @input="sanitizePrefix()"
                               required
                               maxlength="40"
                               placeholder="rider.imus1"
                               class="flex-1 p-3 bg-transparent text-sm text-text-main outline-none font-mono">
                        <span class="px-3.5 py-3 bg-surface-subtle border-l border-border-subtle text-text-muted font-mono font-bold text-xs select-none">
                            @storkia.shop
                        </span>
                    </div>
                    <p class="text-[11px] text-text-muted mt-1">
                        Login Email: <strong class="font-mono text-primary-dark" x-text="(emailPrefix || 'username') + '@storkia.shop'"></strong>
                    </p>
                </div>

                <div>
                    <label class="block font-bold text-text-muted uppercase tracking-wider mb-1.5">
                        Assign Company Hub (Municipality) <span class="text-danger">*</span>
                    </label>

                    @if($isBranchAccount && $lockedBranchHub)
                        <div class="w-full p-3 rounded-xl border border-border-subtle bg-surface-subtle text-xs font-bold text-primary-dark flex items-center justify-between select-none cursor-not-allowed">
                            <span class="truncate">Branch Hub — {{ $lockedBranchHub->municipality }} ({{ $lockedBranchHub->hub_name }})</span>
                            <span class="px-2 py-0.5 rounded bg-brand-light/60 text-[10px] font-extrabold uppercase shrink-0">Read-Only</span>
                        </div>
                    @else
                        <div class="relative" @click.away="hubDropdownOpen = false">
                            <button type="button"
                                    @click="hubDropdownOpen = !hubDropdownOpen"
                                    class="w-full p-3 rounded-xl border border-border-subtle bg-surface text-xs font-semibold text-left flex items-center justify-between gap-2 cursor-pointer hover:border-primary focus:outline-none">
                                <span class="truncate text-text-main" x-text="selectedHubLabel"></span>
                                <svg class="w-4 h-4 text-text-muted transition-transform shrink-0" :class="hubDropdownOpen ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                            </button>

                            <div x-show="hubDropdownOpen"
                                 x-cloak
                                 x-transition
                                 class="absolute left-0 right-0 top-full mt-1.5 z-50 bg-surface border border-border-subtle rounded-2xl shadow-xl overflow-hidden max-h-56 overflow-y-auto divide-y divide-border-subtle/40 custom-scrollbar">
                                <template x-for="hub in hubsOptions" :key="hub.id">
                                    <button type="button"
                                            @click="selectHub(hub)"
                                            class="w-full px-4 py-2.5 text-left text-xs hover:bg-primary/10 transition-colors flex items-center justify-between cursor-pointer"
                                            :class="String(selectedHubId) === String(hub.id) ? 'bg-primary/10 text-primary font-bold' : 'text-text-main font-medium'">
                                        <span x-text="hub.label"></span>
                                        <span x-show="String(selectedHubId) === String(hub.id)" class="text-primary font-bold">✓</span>
                                    </button>
                                </template>
                            </div>
                        </div>
                    @endif
                </div>
            </div>

            <div>
                <div class="flex items-center justify-between mb-1.5">
                    <label class="block font-bold text-text-muted uppercase tracking-wider">
                        Assigned Barangay Coverage (Select Barangays in Hub Municipality) <span class="text-danger">*</span>
                    </label>
                    <span class="text-[11px] text-primary font-bold" x-text="selectedBarangays.length + ' barangay(s) selected'"></span>
                </div>
                <div class="max-h-56 overflow-y-auto border border-border-subtle rounded-2xl p-3 bg-surface-subtle/50 grid grid-cols-1 sm:grid-cols-2 gap-2 custom-scrollbar">
                    <template x-for="brgy in currentBarangays()" :key="brgy.code || brgy.name">
                        <label class="flex items-center gap-2 p-2.5 rounded-xl bg-surface border border-border-subtle hover:border-primary cursor-pointer">
                            <input type="checkbox"
                                   name="barangays[]"
                                   :value="brgy.name"
                                   x-model="selectedBarangays"
                                   class="rounded text-primary focus:ring-primary">
                            <span class="text-xs font-medium text-text-main truncate" x-text="brgy.name"></span>
                        </label>
                    </template>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <label class="block font-bold text-text-muted uppercase tracking-wider mb-1.5">First Name <span class="text-danger">*</span></label>
                    <input type="text" name="first_name" value="{{ old('first_name') }}" required maxlength="50" placeholder="Juan" class="w-full p-3 rounded-xl border border-border-subtle bg-surface">
                </div>
                <div>
                    <label class="block font-bold text-text-muted uppercase tracking-wider mb-1.5">Last Name <span class="text-danger">*</span></label>
                    <input type="text" name="last_name" value="{{ old('last_name') }}" required maxlength="50" placeholder="Dela Cruz" class="w-full p-3 rounded-xl border border-border-subtle bg-surface">
                </div>
                <div>
                    <label class="block font-bold text-text-muted uppercase tracking-wider mb-1.5">Date of Birth <span class="text-danger">*</span></label>
                    <input type="date" name="birthday" value="{{ old('birthday') }}" required max="{{ now()->subYears(18)->format('Y-m-d') }}" class="w-full p-3 rounded-xl border border-border-subtle bg-surface">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block font-bold text-text-muted uppercase tracking-wider mb-1.5">Mobile Number <span class="text-danger">*</span></label>
                    <div class="flex items-center rounded-xl border border-border-subtle overflow-hidden bg-surface">
                        <span class="px-3 bg-surface-subtle border-r border-border-subtle font-bold text-text-muted">+63</span>
                        <input type="text" name="contact_no" value="{{ old('contact_no') }}" required maxlength="10" placeholder="9123456789" class="flex-1 p-3 outline-none bg-transparent">
                    </div>
                </div>
                <div>
                    <label class="block font-bold text-text-muted uppercase tracking-wider mb-1.5">Driver's License No. <span class="text-danger">*</span></label>
                    <input type="text" name="license_no" value="{{ old('license_no') }}" required placeholder="N01-23-456789" class="w-full p-3 rounded-xl border border-border-subtle bg-surface font-mono uppercase">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <label class="block font-bold text-text-muted uppercase tracking-wider mb-1.5">License Expiry Date <span class="text-danger">*</span></label>
                    <input type="date" name="license_expiry_date" value="{{ old('license_expiry_date') }}" required min="{{ now()->format('Y-m-d') }}" class="w-full p-3 rounded-xl border border-border-subtle bg-surface">
                </div>

                <div class="relative" @click.away="vehicleDropdownOpen = false">
                    <label class="block font-bold text-text-muted uppercase tracking-wider mb-1.5">Vehicle Type <span class="text-danger">*</span></label>
                    <button type="button"
                            @click="vehicleDropdownOpen = !vehicleDropdownOpen"
                            class="w-full p-3 rounded-xl border border-border-subtle bg-surface text-xs font-semibold text-left flex items-center justify-between gap-2 cursor-pointer hover:border-primary">
                        <span class="truncate text-text-main" x-text="selectedVehicleType"></span>
                        <svg class="w-4 h-4 text-text-muted transition-transform shrink-0" :class="vehicleDropdownOpen ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                    </button>

                    <div x-show="vehicleDropdownOpen"
                         x-cloak
                         x-transition
                         class="absolute left-0 right-0 top-full mt-1.5 z-50 bg-surface border border-border-subtle rounded-2xl shadow-xl overflow-hidden divide-y divide-border-subtle/40">
                        <template x-for="vType in vehicleTypes" :key="vType">
                            <button type="button"
                                    @click="selectedVehicleType = vType; vehicleDropdownOpen = false"
                                    class="w-full px-3.5 py-2.5 text-left text-xs hover:bg-primary/10 transition-colors flex items-center justify-between cursor-pointer"
                                    :class="selectedVehicleType === vType ? 'bg-primary/10 text-primary font-bold' : 'text-text-main font-medium'">
                                <span x-text="vType"></span>
                                <span x-show="selectedVehicleType === vType" class="text-primary font-bold">✓</span>
                            </button>
                        </template>
                    </div>
                </div>

                <div>
                    <label class="block font-bold text-text-muted uppercase tracking-wider mb-1.5">Plate Number <span class="text-danger">*</span></label>
                    <input type="text" name="plate_number" value="{{ old('plate_number') }}" required placeholder="ABC1234" class="w-full p-3 rounded-xl border border-border-subtle bg-surface font-mono uppercase">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block font-bold text-text-muted uppercase tracking-wider mb-1">Driver's License Document <span class="text-danger">*</span></label>
                    <input type="file" name="drivers_license" accept=".jpg,.jpeg,.png,.pdf" required class="w-full p-2.5 rounded-xl border border-border-subtle bg-surface text-xs file:mr-3 file:py-1 file:px-3 file:rounded-lg file:border-0 file:bg-primary/10 file:text-primary file:font-bold">
                </div>
                <div>
                    <label class="block font-bold text-text-muted uppercase tracking-wider mb-1">Vehicle OR/CR Document <span class="text-danger">*</span></label>
                    <input type="file" name="vehicle_or_cr" accept=".jpg,.jpeg,.png,.pdf" required class="w-full p-2.5 rounded-xl border border-border-subtle bg-surface text-xs file:mr-3 file:py-1 file:px-3 file:rounded-lg file:border-0 file:bg-primary/10 file:text-primary file:font-bold">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-2 border-t border-border-subtle">
                <div>
                    <label class="block font-bold text-text-muted uppercase tracking-wider mb-1.5">Password <span class="text-danger">*</span></label>
                    <input type="password" name="password" required minlength="8" placeholder="••••••••" class="w-full p-3 rounded-xl border border-border-subtle bg-surface">
                </div>
                <div>
                    <label class="block font-bold text-text-muted uppercase tracking-wider mb-1.5">Confirm Password <span class="text-danger">*</span></label>
                    <input type="password" name="password_confirmation" required minlength="8" placeholder="••••••••" class="w-full p-3 rounded-xl border border-border-subtle bg-surface">
                </div>
            </div>

            <div class="flex justify-end gap-3 pt-4">
                <a href="{{ route('logistics.rider-applications.index') }}" class="px-5 py-3 rounded-xl bg-surface-subtle text-text-muted font-bold">
                    Cancel
                </a>
                <button type="submit" class="px-6 py-3 rounded-xl bg-primary-dark hover:bg-text-main text-surface font-bold shadow-sm cursor-pointer">
                    Create Rider Account
                </button>
            </div>
        </form>
    </div>
</div>
@endsection