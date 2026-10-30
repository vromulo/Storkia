@extends('layouts.logistics', ['title' => 'Storkia - Branch Hub Management'])

@section('content')
<div x-data="{
    createModalOpen: {{ $errors->any() ? 'true' : 'false' }},
    detailHub: null,
    emailPrefix: @js(old('email_prefix', '')),
    provincesList: @js($provinces),
    selectedProvinceCode: @js(old('province_code', $defaultProvinceCode)),
    selectedProvinceName: @js(old('province', $defaultProvinceName)),
    selectedMunicipalityCode: @js(old('municipality_code', '')),
    selectedMunicipalityName: @js(old('municipality', '')),
    municipalities: @js($initialMunicipalities),
    loadingMunicipalities: false,
    provinceDropdownOpen: false,
    municipalityDropdownOpen: false,
    provinceSearch: '',
    municipalitySearch: '',
    showPass: false,
    showConfirmPass: false,
    sanitizePrefix() {
        this.emailPrefix = (this.emailPrefix || '').toLowerCase().replace(/@.*$/, '').replace(/[^a-z0-9._-]/g, '').slice(0, 40);
    },
    get filteredProvinces() {
        const q = (this.provinceSearch || '').trim().toLowerCase();
        if (!q) return this.provincesList || [];
        return (this.provincesList || []).filter(p => (p.name || '').toLowerCase().includes(q));
    },
    get filteredMunicipalities() {
        const q = (this.municipalitySearch || '').trim().toLowerCase();
        if (!q) return this.municipalities || [];
        return (this.municipalities || []).filter(m => (m.name || '').toLowerCase().includes(q));
    },
    async selectProvince(prov) {
        this.selectedProvinceCode = prov.code;
        this.selectedProvinceName = prov.name;
        this.provinceDropdownOpen = false;
        this.provinceSearch = '';
        this.selectedMunicipalityCode = '';
        this.selectedMunicipalityName = '';
        this.municipalities = [];
        if (!this.selectedProvinceCode) return;
        this.loadingMunicipalities = true;
        try {
            const res = await fetch(`{{ route('logistics.branches.municipalities') }}?province_code=${encodeURIComponent(this.selectedProvinceCode)}`);
            const data = await res.json();
            this.municipalities = data.municipalities || [];
        } catch (e) {
            this.municipalities = [];
        } finally {
            this.loadingMunicipalities = false;
        }
    },
    selectMunicipality(mun) {
        this.selectedMunicipalityName = mun.name;
        this.selectedMunicipalityCode = mun.code || '';
        this.municipalityDropdownOpen = false;
        this.municipalitySearch = '';
    }
}" class="max-w-7xl mx-auto space-y-6 select-none">

    <div class="flex flex-col md:flex-row md:items-end justify-between gap-4">
        <div>
            <div class="flex items-center gap-2.5">
                <h1 class="text-3xl font-bold text-primary-dark">Branch Hub Management</h1>
                <span class="px-3 py-1 rounded-full text-xs font-bold bg-brand-light/60 text-primary-dark border border-border-subtle">
                    Main Logistics Account
                </span>
            </div>
            <p class="text-sm text-text-muted mt-1">
                Manage your Main Hub and create Municipality Branch Hub Accounts under <strong>{{ $profile->business_name }}</strong>.
            </p>
        </div>

        <div class="flex items-center gap-2.5">
            <a href="{{ route('logistics.employees.index') }}"
               class="px-4 py-2.5 rounded-2xl border border-border-subtle bg-surface hover:border-primary text-xs font-bold text-text-main transition-colors">
                Manage Company Personnel
            </a>
            <button type="button"
                    @click="createModalOpen = true"
                    class="inline-flex items-center gap-2 px-5 py-2.5 rounded-2xl bg-primary-dark hover:bg-text-main text-surface text-xs font-bold shadow-xs transition-all cursor-pointer">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                </svg>
                Create Branch Hub
            </button>
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

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="p-5 bg-surface rounded-2xl border border-border-subtle shadow-xs">
            <span class="text-xs font-bold uppercase text-text-muted">Total Operating Hubs</span>
            <p class="text-3xl font-serif font-bold text-primary-dark mt-1">{{ $hubs->count() }}</p>
            <span class="text-[11px] text-text-muted">1 Main Hub &bull; {{ max(0, $hubs->count() - 1) }} Branch Hub(s)</span>
        </div>
        <div class="p-5 bg-surface rounded-2xl border border-border-subtle shadow-xs">
            <span class="text-xs font-bold uppercase text-text-muted">Active Branch Hubs</span>
            <p class="text-3xl font-serif font-bold text-green-700 mt-1">{{ $hubs->where('is_main_hub', false)->where('status', 'active')->count() }}</p>
            <span class="text-[11px] text-text-muted">Serving designated municipalities</span>
        </div>
        <div class="p-5 bg-surface rounded-2xl border border-border-subtle shadow-xs">
            <span class="text-xs font-bold uppercase text-text-muted">Parent Logistics Company</span>
            <p class="text-lg font-bold text-text-main mt-1 truncate">{{ $profile->business_name }}</p>
            <span class="text-[11px] text-text-muted">Main Hub: {{ $profile->municipality }}, {{ $profile->province }}</span>
        </div>
    </div>

    <div class="bg-surface rounded-3xl border border-border-subtle shadow-xs overflow-hidden">
        <div class="p-5 border-b border-border-subtle bg-surface-subtle/50 flex items-center justify-between">
            <h2 class="text-base font-bold text-primary-dark">Company Hub Hierarchy ({{ $profile->business_name }})</h2>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-xs">
                <thead>
                    <tr class="border-b border-border-subtle bg-surface-subtle/40 text-text-muted uppercase text-[11px] font-bold">
                        <th class="py-3.5 px-5">Hub Code &amp; Name</th>
                        <th class="py-3.5 px-4">Hub Type</th>
                        <th class="py-3.5 px-4">Municipality Coverage</th>
                        <th class="py-3.5 px-4">Account Login Email</th>
                        <th class="py-3.5 px-4">Assigned Personnel</th>
                        <th class="py-3.5 px-4">Status</th>
                        <th class="py-3.5 px-5 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border-subtle">
                    @foreach($hubs as $hub)
                        @php
                            $courierCount = $hub->activeCourierAssignments->count();
                            $riderCount = $hub->activeRiderAssignments->count();
                            $loginEmail = $hub->is_main_hub
                                ? ($profile->user->email ?? '')
                                : ($hub->branchUser->email ?? ($hub->email_prefix ? $hub->email_prefix . '@storkia.shop' : 'N/A'));
                        @endphp
                        <tr class="hover:bg-surface-subtle/30 transition-colors">
                            <td class="py-4 px-5 align-top">
                                <div class="font-mono font-bold text-primary-dark">{{ $hub->hub_code }}</div>
                                <div class="font-bold text-sm text-text-main mt-0.5">{{ $hub->hub_name }}</div>
                            </td>
                            <td class="py-4 px-4 align-top">
                                @if($hub->is_main_hub)
                                    <span class="px-2.5 py-1 rounded-full text-[11px] font-bold bg-primary text-white">
                                        Main Hub
                                    </span>
                                @else
                                    <span class="px-2.5 py-1 rounded-full text-[11px] font-bold bg-brand-light/60 text-primary-dark border border-border-subtle">
                                        Branch Hub
                                    </span>
                                @endif
                            </td>
                            <td class="py-4 px-4 align-top">
                                <div class="font-bold text-text-main">{{ $hub->municipality }}</div>
                                <div class="text-text-muted">{{ $hub->province }}</div>
                            </td>
                            <td class="py-4 px-4 align-top font-mono text-text-main">
                                {{ $loginEmail }}
                            </td>
                            <td class="py-4 px-4 align-top space-y-0.5">
                                <div>Couriers: <strong class="text-primary-dark">{{ $courierCount }}</strong></div>
                                <div>Riders: <strong class="text-primary-dark">{{ $riderCount }}</strong></div>
                            </td>
                            <td class="py-4 px-4 align-top">
                                @if($hub->status === 'active')
                                    <span class="px-2.5 py-1 rounded-full text-[11px] font-bold bg-green-100 text-green-700 border border-green-200">
                                        Active
                                    </span>
                                @else
                                    <span class="px-2.5 py-1 rounded-full text-[11px] font-bold bg-red-100 text-red-700 border border-red-200">
                                        Inactive
                                    </span>
                                @endif
                            </td>
                            <td class="py-4 px-5 align-top text-right">
                                <div class="flex flex-wrap items-center justify-end gap-2">
                                    <a href="{{ route('logistics.employees.index', ['hub_id' => $hub->id]) }}"
                                       class="px-3 py-1.5 rounded-xl bg-surface-subtle hover:bg-brand-light/40 border border-border-subtle text-text-main font-bold transition-colors">
                                        Manage Personnel
                                    </a>

                                    @if(!$hub->is_main_hub)
                                        <form action="{{ route('logistics.branches.toggle-status', $hub->id) }}" method="POST" class="m-0">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit"
                                                    class="px-3 py-1.5 rounded-xl text-xs font-bold cursor-pointer {{ $hub->status === 'active' ? 'bg-red-50 text-danger hover:bg-red-100 border border-red-200' : 'bg-green-600 text-white hover:bg-green-700' }}">
                                                {{ $hub->status === 'active' ? 'Deactivate' : 'Activate' }}
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
    </div>

    <template x-teleport="body">
        <div x-show="createModalOpen"
             x-cloak
             class="fixed inset-0 z-[190] flex items-center justify-center p-4">
            <div @click="createModalOpen = false" class="absolute inset-0 bg-black/60 backdrop-blur-xs"></div>

            <div class="relative z-10 bg-surface rounded-3xl max-w-lg w-full p-6 sm:p-8 shadow-2xl border border-border-subtle space-y-5 max-h-[92vh] overflow-y-auto custom-scrollbar">
                <div class="flex items-center justify-between pb-3 border-b border-border-subtle">
                    <div>
                        <h3 class="text-lg font-bold text-primary-dark">Create Branch Hub Account</h3>
                        <p class="text-xs text-text-muted mt-0.5">
                            Parent Company: <strong>{{ $profile->business_name }}</strong>
                        </p>
                    </div>
                    <button type="button" @click="createModalOpen = false" class="p-1.5 text-text-muted hover:text-text-main cursor-pointer">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <form action="{{ route('logistics.branches.store') }}" method="POST" class="space-y-4 text-xs">
                    @csrf
                    <input type="hidden" name="province_code" :value="selectedProvinceCode">
                    <input type="hidden" name="province" :value="selectedProvinceName">
                    <input type="hidden" name="municipality_code" :value="selectedMunicipalityCode">
                    <input type="hidden" name="municipality" :value="selectedMunicipalityName">

                    <div>
                        <label class="block font-bold text-text-muted uppercase tracking-wider mb-1.5">
                            Branch Account Email Username <span class="text-danger">*</span>
                        </label>
                        <div class="flex items-center rounded-xl border border-border-subtle focus-within:border-primary overflow-hidden bg-surface">
                            <input type="text"
                                   name="email_prefix"
                                   x-model="emailPrefix"
                                   @input="sanitizePrefix()"
                                   required
                                   maxlength="40"
                                   placeholder="branch.imus"
                                   class="flex-1 p-3 bg-transparent text-sm text-text-main outline-none font-mono">
                            <span class="px-3.5 py-3 bg-surface-subtle border-l border-border-subtle text-text-muted font-mono font-bold text-xs select-none">
                                @storkia.shop
                            </span>
                        </div>
                        <p class="text-[11px] text-text-muted mt-1">
                            Generated Login Email: <strong class="font-mono text-primary-dark" x-text="(emailPrefix || 'username') + '@storkia.shop'"></strong>
                        </p>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                        <!-- Custom Downward-Opening Province Dropdown -->
                        <div class="relative" @click.away="provinceDropdownOpen = false">
                            <label class="block font-bold text-text-muted uppercase tracking-wider mb-1.5">
                                Province <span class="text-danger">*</span>
                            </label>
                            <button type="button"
                                    @click="provinceDropdownOpen = !provinceDropdownOpen; municipalityDropdownOpen = false"
                                    class="w-full p-3 rounded-xl border border-border-subtle bg-surface text-xs font-semibold text-left flex items-center justify-between gap-2 cursor-pointer hover:border-primary focus:outline-none">
                                <span class="truncate" :class="selectedProvinceName ? 'text-text-main' : 'text-text-muted'" x-text="selectedProvinceName || 'Select Province'"></span>
                                <svg class="w-4 h-4 text-text-muted transition-transform shrink-0" :class="provinceDropdownOpen ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                            </button>

                            <div x-show="provinceDropdownOpen"
                                 x-cloak
                                 x-transition
                                 class="absolute left-0 right-0 top-full mt-1.5 z-50 bg-surface border border-border-subtle rounded-2xl shadow-xl overflow-hidden">
                                <div class="p-2 border-b border-border-subtle bg-surface-subtle">
                                    <input type="text"
                                           x-model="provinceSearch"
                                           placeholder="Search province..."
                                           class="w-full px-3 py-1.5 rounded-xl border border-border-subtle bg-surface text-xs outline-none focus:border-primary">
                                </div>
                                <div class="max-h-52 overflow-y-auto divide-y divide-border-subtle/40 custom-scrollbar">
                                    <template x-for="prov in filteredProvinces" :key="prov.code">
                                        <button type="button"
                                                @click="selectProvince(prov)"
                                                class="w-full px-3.5 py-2.5 text-left text-xs hover:bg-primary/10 transition-colors flex items-center justify-between cursor-pointer"
                                                :class="String(selectedProvinceCode) === String(prov.code) ? 'bg-primary/10 text-primary font-bold' : 'text-text-main font-medium'">
                                            <span x-text="prov.name"></span>
                                            <span x-show="String(selectedProvinceCode) === String(prov.code)" class="text-primary font-bold">✓</span>
                                        </button>
                                    </template>
                                </div>
                            </div>
                        </div>

                        <!-- Custom Downward-Opening Branch Municipality Dropdown -->
                        <div class="relative" @click.away="municipalityDropdownOpen = false">
                            <label class="block font-bold text-text-muted uppercase tracking-wider mb-1.5">
                                Branch Municipality <span class="text-danger">*</span>
                            </label>
                            <button type="button"
                                    @click="if (!loadingMunicipalities && municipalities.length > 0) { municipalityDropdownOpen = !municipalityDropdownOpen; provinceDropdownOpen = false; }"
                                    :disabled="loadingMunicipalities || municipalities.length === 0"
                                    class="w-full p-3 rounded-xl border border-border-subtle bg-surface text-xs font-semibold text-left flex items-center justify-between gap-2 cursor-pointer hover:border-primary disabled:opacity-50 focus:outline-none">
                                <span class="truncate"
                                      :class="selectedMunicipalityName ? 'text-text-main' : 'text-text-muted'"
                                      x-text="loadingMunicipalities ? 'Loading municipalities...' : (selectedMunicipalityName || 'Select Municipality')"></span>
                                <svg class="w-4 h-4 text-text-muted transition-transform shrink-0" :class="municipalityDropdownOpen ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                            </button>

                            <div x-show="municipalityDropdownOpen"
                                 x-cloak
                                 x-transition
                                 class="absolute left-0 right-0 top-full mt-1.5 z-50 bg-surface border border-border-subtle rounded-2xl shadow-xl overflow-hidden">
                                <div class="p-2 border-b border-border-subtle bg-surface-subtle">
                                    <input type="text"
                                           x-model="municipalitySearch"
                                           placeholder="Search city or municipality..."
                                           class="w-full px-3 py-1.5 rounded-xl border border-border-subtle bg-surface text-xs outline-none focus:border-primary">
                                </div>
                                <div class="max-h-52 overflow-y-auto divide-y divide-border-subtle/40 custom-scrollbar">
                                    <template x-for="mun in filteredMunicipalities" :key="mun.code">
                                        <button type="button"
                                                @click="selectMunicipality(mun)"
                                                class="w-full px-3.5 py-2.5 text-left text-xs hover:bg-primary/10 transition-colors flex items-center justify-between cursor-pointer"
                                                :class="selectedMunicipalityName === mun.name ? 'bg-primary/10 text-primary font-bold' : 'text-text-main font-medium'">
                                            <span x-text="mun.name"></span>
                                            <span x-show="selectedMunicipalityName === mun.name" class="text-primary font-bold">✓</span>
                                        </button>
                                    </template>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div>
                        <label class="block font-bold text-text-muted uppercase tracking-wider mb-1.5">
                            Branch Hub Display Name (Optional)
                        </label>
                        <input type="text"
                               name="hub_name"
                               value="{{ old('hub_name') }}"
                               maxlength="120"
                               placeholder="e.g. {{ $profile->business_name }} - Imus Branch Hub"
                               class="w-full p-3 rounded-xl border border-border-subtle bg-surface text-xs outline-none focus:border-primary">
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                        <div>
                            <label class="block font-bold text-text-muted uppercase tracking-wider mb-1.5">
                                Password <span class="text-danger">*</span>
                            </label>
                            <div class="relative flex items-center">
                                <input :type="showPass ? 'text' : 'password'"
                                       name="password"
                                       required
                                       minlength="8"
                                       placeholder="••••••••"
                                       class="w-full p-3 pr-9 rounded-xl border border-border-subtle bg-surface text-xs outline-none focus:border-primary">
                                <button type="button" @click="showPass = !showPass" class="absolute right-3 text-text-muted hover:text-text-main cursor-pointer">
                                    <span class="text-[10px] font-bold" x-text="showPass ? 'HIDE' : 'SHOW'"></span>
                                </button>
                            </div>
                        </div>

                        <div>
                            <label class="block font-bold text-text-muted uppercase tracking-wider mb-1.5">
                                Confirm Password <span class="text-danger">*</span>
                            </label>
                            <div class="relative flex items-center">
                                <input :type="showConfirmPass ? 'text' : 'password'"
                                       name="password_confirmation"
                                       required
                                       minlength="8"
                                       placeholder="••••••••"
                                       class="w-full p-3 pr-9 rounded-xl border border-border-subtle bg-surface text-xs outline-none focus:border-primary">
                                <button type="button" @click="showConfirmPass = !showConfirmPass" class="absolute right-3 text-text-muted hover:text-text-main cursor-pointer">
                                    <span class="text-[10px] font-bold" x-text="showConfirmPass ? 'HIDE' : 'SHOW'"></span>
                                </button>
                            </div>
                        </div>
                    </div>

                    <p class="text-[11px] text-text-muted">
                        Password must be at least 8 characters and include 1 uppercase, 1 lowercase, 1 number, and 1 special character.
                    </p>

                    <div class="flex justify-end gap-2.5 pt-3 border-t border-border-subtle">
                        <button type="button"
                                @click="createModalOpen = false"
                                class="px-4 py-2.5 rounded-xl bg-surface-subtle text-text-muted font-bold cursor-pointer">
                            Cancel
                        </button>
                        <button type="submit"
                                class="px-5 py-2.5 rounded-xl bg-primary-dark hover:bg-text-main text-surface font-bold shadow-sm cursor-pointer">
                            Create Branch Hub Account
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </template>

</div>
@endsection