@extends('layouts.admin', ['title' => 'Storkia Admin - User Account Management & Appeals'])

@section('content')
<div class="p-8 lg:p-10 max-w-7xl mx-auto space-y-8 select-none">

    <div class="flex flex-col md:flex-row md:items-end justify-between gap-4">
        <div>
            <h1 class="text-3xl font-bold text-primary-dark">User Account Management &amp; Ban Appeals</h1>
            <p class="text-sm text-text-muted mt-1">
                Monitor all registered accounts by role (<strong>Buyer, Seller, Logistics, Courier, Rider</strong>), track 3-Strike COD abandonment records, suspend or reactivate accounts, and review Buyer reactivation appeals.
            </p>
        </div>

        <form method="GET" action="{{ route('admin.accounts.index') }}" class="flex items-center gap-2">
            <input type="hidden" name="role" value="{{ $roleTab }}">
            <input type="text"
                   name="search"
                   value="{{ $search }}"
                   placeholder="Search name, email, phone, plate..."
                   class="px-4 py-2.5 rounded-2xl border border-border-subtle bg-surface text-xs text-text-main outline-none focus:border-primary w-64">
            <button type="submit" class="px-4 py-2.5 rounded-2xl bg-primary-dark hover:bg-text-main text-surface text-xs font-bold cursor-pointer">
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

    <div class="flex flex-wrap items-center gap-2">
        @foreach([
            'Buyer'     => 'Buyers (' . $counts['Buyer'] . ')',
            'Seller'    => 'Sellers (' . $counts['Seller'] . ')',
            'Logistics' => 'Logistics Hubs (' . $counts['Logistics'] . ')',
            'Courier'   => 'First-Mile Couriers (' . $counts['Courier'] . ')',
            'Rider'     => 'Last-Mile Riders (' . $counts['Rider'] . ')',
            'Appeals'   => 'Buyer 3-Strike Ban Appeals (' . $counts['Appeals'] . ' Pending)',
        ] as $key => $label)
            <a href="{{ route('admin.accounts.index', ['role' => $key]) }}"
               class="px-4 py-2.5 rounded-2xl text-xs font-bold border transition-all {{ $roleTab === $key ? 'bg-primary-dark text-white border-primary-dark shadow-xs' : ($key === 'Appeals' && $counts['Appeals'] > 0 ? 'bg-amber-50 text-amber-900 border-amber-300' : 'bg-surface text-text-muted border-border-subtle hover:text-text-main') }}">
                {{ $label }}
            </a>
        @endforeach
    </div>

    @if($roleTab === 'Appeals')
        <div class="bg-surface rounded-3xl border border-border-subtle shadow-xs overflow-hidden">
            <div class="p-5 border-b border-border-subtle bg-surface-subtle/50">
                <h2 class="text-base font-bold text-primary-dark">Buyer 3-Strike COD Abandonment Reactivation Appeals</h2>
                <p class="text-xs text-text-muted">Approving a 1st-ban appeal reactivates the Buyer on probation. If they abandon a COD parcel again, their 2nd ban is automatically a Permanent Ban.</p>
            </div>

            @if($appeals->isEmpty())
                <div class="p-12 text-center text-xs text-text-muted">No Buyer ban appeals submitted yet.</div>
            @else
                <div class="divide-y divide-border-subtle">
                    @foreach($appeals as $ap)
                        <div class="p-5 flex flex-col lg:flex-row lg:items-center justify-between gap-4 text-xs">
                            <div class="space-y-1.5 max-w-2xl">
                                <div class="flex items-center gap-2">
                                    <span class="font-bold text-sm text-text-main">{{ $ap->buyer?->full_name }} ({{ $ap->buyer?->email }})</span>
                                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-extrabold uppercase {{ $ap->status === 'pending' ? 'bg-amber-100 text-amber-900' : ($ap->status === 'approved' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-danger') }}">
                                        {{ $ap->status }}
                                    </span>
                                    <span class="text-text-muted">Strikes: <strong class="text-danger">{{ $ap->sanction?->cod_abandonment_strikes ?? 3 }}/3</strong></span>
                                </div>
                                <p class="text-text-main bg-surface-subtle p-3 rounded-xl border border-border-subtle italic">
                                    "{{ $ap->appeal_explanation }}"
                                </p>
                                @if($ap->supporting_photo_path)
                                    <a href="{{ asset('storage/' . $ap->supporting_photo_path) }}" target="_blank" class="text-primary font-bold hover:underline inline-block">
                                        View Supporting Appeal Attachment &rarr;
                                    </a>
                                @endif
                            </div>

                            @if($ap->status === 'pending')
                                <form action="{{ route('admin.accounts.resolve-appeal', $ap->id) }}" method="POST" class="flex flex-wrap items-center gap-2 shrink-0">
                                    @csrf
                                    <input type="text" name="remarks" required minlength="5" placeholder="Admin review remarks..." class="p-2.5 rounded-xl border border-border-subtle bg-surface text-xs w-56">
                                    <button type="submit" name="decision" value="approve" class="px-4 py-2.5 rounded-xl bg-green-600 hover:bg-green-700 text-white font-bold cursor-pointer">
                                        Approve &amp; Reactivate
                                    </button>
                                    <button type="submit" name="decision" value="reject" class="px-4 py-2.5 rounded-xl bg-danger text-white font-bold cursor-pointer">
                                        Reject Appeal
                                    </button>
                                </form>
                            @else
                                <div class="text-right text-text-muted">
                                    <div>Reviewed {{ $ap->reviewed_at?->format('M d, Y h:i A') }}</div>
                                    <div class="font-semibold text-text-main">"{{ $ap->admin_remarks }}"</div>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

    @elseif(in_array($roleTab, ['Buyer', 'Seller', 'Logistics'], true))
        <div class="bg-surface rounded-3xl border border-border-subtle shadow-xs overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-xs">
                    <thead>
                        <tr class="border-b border-border-subtle bg-surface-subtle/50 text-text-muted uppercase text-[11px] font-bold">
                            <th class="py-3.5 px-5">User / Identity</th>
                            <th class="py-3.5 px-4">Role &amp; Profile</th>
                            <th class="py-3.5 px-4">Monitoring &amp; COD Strikes</th>
                            <th class="py-3.5 px-4">Account Status</th>
                            <th class="py-3.5 px-5 text-right">Admin Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border-subtle">
                        @foreach($users as $u)
                            @php
                                $sanction = $sanctionsMap->get($u->id);
                                $acctStatus = $sanction?->account_status ?? 'active';
                                $isBlocked = $sanction && $sanction->isBlockedFromMarketplace();
                            @endphp
                            <tr class="hover:bg-surface-subtle/30">
                                <td class="py-4 px-5 align-top">
                                    <div class="font-bold text-sm text-text-main">{{ $u->full_name }}</div>
                                    <div class="font-mono text-text-muted">{{ $u->email }}</div>
                                    <div class="text-[11px] text-text-muted">{{ $u->contact_no ?? 'No phone' }}</div>
                                </td>
                                <td class="py-4 px-4 align-top">
                                    <span class="px-2.5 py-0.5 rounded-full bg-brand-light/50 text-primary-dark font-bold text-[10px] uppercase">{{ $u->role }}</span>
                                    @if($u->sellerProfile)
                                        <div class="font-semibold text-text-main mt-1">{{ $u->sellerProfile->business_name }}</div>
                                    @elseif($u->logisticsProfile)
                                        <div class="font-semibold text-text-main mt-1">{{ $u->logisticsProfile->business_name }}</div>
                                    @endif
                                    @if($u->role === 'Buyer')
                                        <div class="text-[11px] mt-1 {{ $u->identityVerification?->status === 'verified' ? 'text-green-700 font-bold' : 'text-text-muted' }}">
                                            Valid ID: {{ ucfirst($u->identityVerification?->status ?? 'Unverified') }}
                                        </div>
                                    @endif
                                </td>
                                <td class="py-4 px-4 align-top space-y-0.5">
                                    <div>COD Abandonment Strikes: <strong class="{{ ($sanction?->cod_abandonment_strikes ?? 0) > 0 ? 'text-danger' : 'text-text-main' }}">{{ $sanction?->cod_abandonment_strikes ?? 0 }} / 3</strong></div>
                                    <div>Ban Level: <strong>{{ $sanction?->ban_level ?? 0 }}</strong></div>
                                    @if($sanction?->last_abandonment_reason)
                                        <div class="text-[11px] text-danger truncate max-w-xs" title="{{ $sanction->last_abandonment_reason }}">
                                            Note: "{{ $sanction->last_abandonment_reason }}"
                                        </div>
                                    @endif
                                </td>
                                <td class="py-4 px-4 align-top">
                                    <span class="px-2.5 py-1 rounded-full text-[10px] font-extrabold uppercase {{ $isBlocked ? 'bg-red-100 text-danger border border-red-200' : 'bg-green-100 text-green-700 border border-green-200' }}">
                                        {{ str_replace('_', ' ', $acctStatus) }}
                                    </span>
                                    @if($sanction?->temp_ban_ends_at && $acctStatus === 'temp_banned')
                                        <div class="text-[10px] text-amber-800 font-semibold mt-1">
                                            Ends: {{ $sanction->temp_ban_ends_at->format('M d, Y h:i A') }}
                                        </div>
                                    @endif
                                </td>
                                <td class="py-4 px-5 align-top text-right">
                                    <form action="{{ route('admin.accounts.toggle-status') }}" method="POST" class="inline-flex flex-wrap items-center justify-end gap-1.5">
                                        @csrf
                                        <input type="hidden" name="entity_type" value="user">
                                        <input type="hidden" name="entity_id" value="{{ $u->id }}">
                                        @if($isBlocked)
                                            <button type="submit" name="action" value="reactivate" class="px-3 py-1.5 rounded-xl bg-green-600 hover:bg-green-700 text-white font-bold text-[11px] cursor-pointer">
                                                Reactivate Account
                                            </button>
                                        @else
                                            <button type="submit" name="action" value="suspend_temp" class="px-3 py-1.5 rounded-xl bg-amber-500 hover:bg-amber-600 text-white font-bold text-[11px] cursor-pointer">
                                                7-Day Suspend
                                            </button>
                                            <button type="submit" name="action" value="suspend_perm" onclick="return confirm('Permanently ban this account?')" class="px-3 py-1.5 rounded-xl bg-danger text-white font-bold text-[11px] cursor-pointer">
                                                Permanent Ban
                                            </button>
                                        @endif
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="px-5 py-4 border-t border-border-subtle">
                {{ $users->links() }}
            </div>
        </div>

    @elseif($roleTab === 'Courier')
        <div class="bg-surface rounded-3xl border border-border-subtle shadow-xs overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-xs">
                    <thead>
                        <tr class="border-b border-border-subtle bg-surface-subtle/50 text-text-muted uppercase text-[11px] font-bold">
                            <th class="py-3.5 px-5">Courier</th>
                            <th class="py-3.5 px-4">Logistics Hub</th>
                            <th class="py-3.5 px-4">Vehicle &amp; License</th>
                            <th class="py-3.5 px-4">Status</th>
                            <th class="py-3.5 px-5 text-right">Admin Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border-subtle">
                        @foreach($couriers as $c)
                            <tr class="hover:bg-surface-subtle/30">
                                <td class="py-4 px-5">
                                    <div class="font-bold text-text-main">{{ $c->full_name }}</div>
                                    <div class="font-mono text-text-muted">{{ $c->email }}</div>
                                </td>
                                <td class="py-4 px-4">
                                    <div class="font-semibold text-text-main">{{ $c->logisticsProfile->business_name ?? 'N/A' }}</div>
                                    <div class="text-text-muted">{{ $c->home_hub }}</div>
                                </td>
                                <td class="py-4 px-4">
                                    <div>{{ $c->vehicle_type }} ({{ $c->plate_number }})</div>
                                    <div class="font-mono text-text-muted">{{ $c->license_no }}</div>
                                </td>
                                <td class="py-4 px-4">
                                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase {{ $c->status === 'suspended' ? 'bg-red-100 text-danger' : 'bg-green-100 text-green-700' }}">
                                        {{ $c->status }}
                                    </span>
                                </td>
                                <td class="py-4 px-5 text-right">
                                    <form action="{{ route('admin.accounts.toggle-status') }}" method="POST" class="inline">
                                        @csrf
                                        <input type="hidden" name="entity_type" value="courier">
                                        <input type="hidden" name="entity_id" value="{{ $c->id }}">
                                        @if($c->status === 'suspended')
                                            <button type="submit" name="action" value="reactivate" class="px-3.5 py-1.5 rounded-xl bg-green-600 text-white font-bold cursor-pointer">Reactivate</button>
                                        @else
                                            <button type="submit" name="action" value="suspend_temp" class="px-3.5 py-1.5 rounded-xl bg-danger text-white font-bold cursor-pointer">Suspend</button>
                                        @endif
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="px-5 py-4 border-t border-border-subtle">
                {{ $couriers->links() }}
            </div>
        </div>

    @elseif($roleTab === 'Rider')
        <div class="bg-surface rounded-3xl border border-border-subtle shadow-xs overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-xs">
                    <thead>
                        <tr class="border-b border-border-subtle bg-surface-subtle/50 text-text-muted uppercase text-[11px] font-bold">
                            <th class="py-3.5 px-5">Rider</th>
                            <th class="py-3.5 px-4">Logistics Hub &amp; Barangays</th>
                            <th class="py-3.5 px-4">Vehicle &amp; License</th>
                            <th class="py-3.5 px-4">Status</th>
                            <th class="py-3.5 px-5 text-right">Admin Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border-subtle">
                        @foreach($riders as $r)
                            <tr class="hover:bg-surface-subtle/30">
                                <td class="py-4 px-5">
                                    <div class="font-bold text-text-main">{{ $r->full_name }}</div>
                                    <div class="font-mono text-text-muted">{{ $r->email }}</div>
                                </td>
                                <td class="py-4 px-4">
                                    <div class="font-semibold text-text-main">{{ $r->logisticsProfile->business_name ?? 'N/A' }}</div>
                                    <div class="text-text-muted">{{ $r->formatted_barangay_coverage }}</div>
                                </td>
                                <td class="py-4 px-4">
                                    <div>{{ $r->vehicle_type }} ({{ $r->plate_number }})</div>
                                    <div class="font-mono text-text-muted">{{ $r->license_no }}</div>
                                </td>
                                <td class="py-4 px-4">
                                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase {{ $r->status === 'approved' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-danger' }}">
                                        {{ $r->status }}
                                    </span>
                                </td>
                                <td class="py-4 px-5 text-right">
                                    <form action="{{ route('admin.accounts.toggle-status') }}" method="POST" class="inline">
                                        @csrf
                                        <input type="hidden" name="entity_type" value="rider">
                                        <input type="hidden" name="entity_id" value="{{ $r->id }}">
                                        @if($r->status !== 'approved')
                                            <button type="submit" name="action" value="reactivate" class="px-3.5 py-1.5 rounded-xl bg-green-600 text-white font-bold cursor-pointer">Reactivate</button>
                                        @else
                                            <button type="submit" name="action" value="suspend_temp" class="px-3.5 py-1.5 rounded-xl bg-danger text-white font-bold cursor-pointer">Suspend</button>
                                        @endif
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="px-5 py-4 border-t border-border-subtle">
                {{ $riders->links() }}
            </div>
        </div>
    @endif

</div>
@endsection