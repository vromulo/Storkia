@extends('layouts.logistics', ['title' => 'Storkia - Logistics COD & E-Wallet Reports'])

@section('content')
@php
    $withdrawalRequests = $withdrawalRequests ?? collect();
    $remittances = $remittances ?? collect();
    $unremittedToAdminCollections = $unremittedToAdminCollections ?? collect();
    $adminRemittances = $adminRemittances ?? collect();
    $commissionRecords = $commissionRecords ?? collect();
    $walletTransactions = $walletTransactions ?? collect();
    $settlements = $settlements ?? collect();
    $sellers = $sellers ?? collect();

    $availableBalance = (float) ($metrics['wallet_available'] ?? ($logisticsWallet->available_balance ?? 0));
    $pendingEscrow = (float) ($metrics['wallet_pending'] ?? ($logisticsWallet->pending_balance ?? 0));
    $unremittedCodBalance = (float) ($metrics['wallet_unremitted_cod'] ?? ($logisticsWallet->unremitted_cod_balance ?? 0));
    $readyToRemitAmount = (float) ($metrics['ready_to_remit_to_admin_amount'] ?? 0);
    $pendingAdminVerificationAmount = (float) ($metrics['pending_admin_remittance_amount'] ?? 0);

    $hasPendingWithdrawal = $withdrawalRequests->whereIn('status', ['pending', 'approved'])->isNotEmpty();
    $isWithdrawalLockedByCod = $unremittedCodBalance > 0.009;
    $canRequestWithdrawal = $availableBalance >= 100 && ! $hasPendingWithdrawal && ! $isWithdrawalLockedByCod;

    $pendingRemittancesCount = $remittances->where('status', 'submitted')->count();
    $unremittedToAdminCount = $unremittedToAdminCollections->count();
@endphp

<div x-data="{
    activeTab: '{{ request()->query('tab', 'remittances') }}',
    withdrawModalOpen: false,
    withdrawConfirmStep: false,
    withdrawError: '',
    remitAdminModalOpen: false,
    senderAccountName: @js($profile->business_name ?? auth()->user()?->full_name ?? ''),
    senderAccountNumber: @js($profile->contact_no ?? auth()->user()?->contact_no ?? ''),
    gcashReferenceNumber: '',
    isSubmittingAdminRemit: false,
    screenshotModalOpen: false,
    screenshotModalUrl: '',
    payoutMethod: 'GCASH',
    maxWithdrawable: {{ round(max(0, $availableBalance), 2) }},
    requestedAmount: {{ max(100, floor($availableBalance * 100) / 100) }},
    accountName: @js($profile->business_name ?? auth()->user()?->full_name ?? ''),
    accountNumber: @js($profile->contact_no ?? auth()->user()?->contact_no ?? ''),
    isSubmittingWithdraw: false,
    openWithdrawModal() {
        this.withdrawError = '';
        this.withdrawConfirmStep = false;
        this.isSubmittingWithdraw = false;
        this.sanitizeAccountNumber();
        this.withdrawModalOpen = true;
    },
    sanitizeAccountNumber() {
        let digits = (this.accountNumber || '').replace(/\D/g, '');
        if (digits.startsWith('63') && digits.length === 12) {
            digits = '0' + digits.slice(2);
        } else if (digits.startsWith('9') && digits.length === 10) {
            digits = '0' + digits;
        }
        this.accountNumber = digits.slice(0, 11);
    },
    sanitizeSenderPhone() {
        let digits = (this.senderAccountNumber || '').replace(/\D/g, '');
        if (digits.startsWith('63') && digits.length === 12) {
            digits = '0' + digits.slice(2);
        } else if (digits.startsWith('9') && digits.length === 10) {
            digits = '0' + digits;
        }
        this.senderAccountNumber = digits.slice(0, 11);
    },
    reviewWithdrawRequest() {
        this.withdrawError = '';
        this.sanitizeAccountNumber();
        const amt = parseFloat(this.requestedAmount);
        if (isNaN(amt) || amt < 100) {
            this.withdrawError = 'Minimum withdrawal amount is \u20B1100.00.';
            return;
        }
        if (amt > this.maxWithdrawable + 0.001) {
            this.withdrawError = 'Requested amount cannot exceed your available balance of \u20B1' + this.maxWithdrawable.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + '.';
            return;
        }
        if (!this.accountName || this.accountName.trim().length < 2) {
            this.withdrawError = 'Please enter the GCash Account Holder Name (at least 2 characters).';
            return;
        }
        if (!/^09\d{9}$/.test(this.accountNumber)) {
            this.withdrawError = 'Please enter a valid 11-digit GCash mobile number starting with 09 (e.g., 09171234567).';
            return;
        }
        this.withdrawConfirmStep = true;
    },
    confirmAndSubmitWithdraw() {
        if (this.isSubmittingWithdraw) return;
        this.isSubmittingWithdraw = true;
        this.$refs.logisticsWithdrawForm.submit();
    }
}" class="max-w-7xl mx-auto space-y-8 select-none">

    <div class="flex flex-col lg:flex-row lg:items-end justify-between gap-4">
        <div>
            <div class="flex flex-wrap items-center gap-2.5">
                <h1 class="text-3xl font-bold text-primary-dark">COD Remittances, E-Wallet &amp; Financial Reports</h1>
                <span class="px-3 py-1 rounded-full text-xs font-bold bg-brand-light/60 text-primary-dark border border-border-subtle">
                    {{ $profile->business_name }}
                </span>
            </div>
            <p class="text-sm text-text-muted mt-1">
                Two-Pool Commission Architecture: <strong>Logistics Hub, First-Mile Courier, and Last-Mile Rider commissions</strong> are funded 100% from the <strong>Shipping &amp; Operational Fare Fee Pool</strong> (never deducted from Product Price).
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-2.5">
            <a href="{{ route('logistics.ratings.index') }}"
               class="px-4 py-2.5 rounded-2xl bg-amber-50 hover:bg-amber-100 text-amber-900 border border-amber-200 text-xs font-bold transition-colors">
                &#9733; Hub &amp; Rider Ratings
            </a>

            <a href="{{ route('logistics.reports.formal-pdf', ['from_date' => $fromDate, 'to_date' => $toDate, 'preset' => 'custom']) }}"
               target="_blank"
               class="px-4 py-2.5 rounded-2xl bg-primary-dark hover:bg-text-main text-white text-xs font-bold shadow-xs transition-colors">
                Open Formal Printable PDF Statement
            </a>

            @if($readyToRemitAmount > 0)
                <button type="button"
                        @click="sanitizeSenderPhone(); remitAdminModalOpen = true"
                        class="px-4 py-2.5 rounded-2xl bg-primary hover:bg-primary-dark text-white text-xs font-bold shadow-xs transition-colors cursor-pointer">
                    Remit COD to Admin GCash (&#8369;{{ number_format($readyToRemitAmount, 2) }})
                </button>
            @endif

            <button type="button"
                    @click="openWithdrawModal()"
                    @if(! $canRequestWithdrawal) disabled @endif
                    title="{{ $isWithdrawalLockedByCod ? 'Remit all Rider-collected COD to Storkia Admin and wait for verification to unlock withdrawals.' : '' }}"
                    class="px-4 py-2.5 rounded-2xl text-xs font-bold shadow-xs transition-colors {{ $canRequestWithdrawal ? 'bg-green-600 hover:bg-green-700 text-white cursor-pointer' : 'bg-gray-200 text-gray-500 cursor-not-allowed' }}">
                @if($hasPendingWithdrawal)
                    Withdrawal Pending Admin Review
                @elseif($isWithdrawalLockedByCod)
                    Withdrawal Locked (Unremitted COD: &#8369;{{ number_format($unremittedCodBalance, 2) }})
                @else
                    Request Withdrawal (Min &#8369;100)
                @endif
            </button>
        </div>
    </div>

    @if($isWithdrawalLockedByCod)
        <div class="p-5 rounded-3xl bg-amber-50 border border-amber-300 text-amber-950 flex flex-col lg:flex-row lg:items-center justify-between gap-4 shadow-xs">
            <div class="space-y-1">
                <div class="flex items-center gap-2">
                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-extrabold uppercase bg-amber-200 text-amber-900">
                        Hub Withdrawal Guard Active
                    </span>
                    <h3 class="text-sm font-bold text-amber-950">
                        &#8369;{{ number_format($unremittedCodBalance, 2) }} in Rider-Remitted COD Must Be Remitted &amp; Verified by Admin Before Withdrawing
                    </h3>
                </div>
                <p class="text-xs text-amber-900 leading-relaxed">
                    Your Logistics Hub E-Wallet withdrawal button is temporarily locked while holding COD cash received from Riders. Send the collected COD to <strong>Storkia Admin GCash</strong> so Admin can verify receipt and automatically distribute Courier, Rider, Hub, and Seller commissions.
                </p>
            </div>
            <div class="flex items-center gap-2 shrink-0">
                @if($readyToRemitAmount > 0)
                    <button type="button"
                            @click="sanitizeSenderPhone(); remitAdminModalOpen = true"
                            class="px-5 py-2.5 rounded-2xl bg-primary hover:bg-primary-dark text-white text-xs font-bold shadow-xs cursor-pointer">
                        Remit &#8369;{{ number_format($readyToRemitAmount, 2) }} to Admin GCash &rarr;
                    </button>
                @else
                    <button type="button"
                            @click="activeTab = 'admin_remittances'"
                            class="px-4 py-2.5 rounded-2xl bg-amber-900 text-white text-xs font-bold cursor-pointer">
                        View Pending Admin Verification (&#8369;{{ number_format($pendingAdminVerificationAmount, 2) }})
                    </button>
                @endif
            </div>
        </div>
    @endif

    @if(session('success'))
        <div class="p-4 rounded-2xl bg-green-50 border border-green-200 text-green-700 text-xs sm:text-sm font-bold">
            {{ session('success') }}
        </div>
    @endif

    @if(session('info'))
        <div class="p-4 rounded-2xl bg-blue-50 border border-blue-200 text-blue-700 text-xs sm:text-sm font-bold">
            {{ session('info') }}
        </div>
    @endif

    @if($errors->any())
        <div class="p-4 rounded-2xl bg-red-50 border border-red-200 text-red-700 text-xs sm:text-sm space-y-1">
            @foreach($errors->all() as $err)
                <p class="font-semibold">{{ $err }}</p>
            @endforeach
        </div>
    @endif

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-4">
        <div class="p-5 bg-gradient-to-br from-primary-dark to-primary text-white rounded-2xl shadow-sm">
            <span class="text-[10px] font-bold text-white/80 uppercase">Available Hub E-Wallet</span>
            <p class="text-2xl font-serif font-extrabold mt-1.5">&#8369;{{ number_format($availableBalance, 2) }}</p>
            <span class="text-[10px] text-white/85 mt-1 block">
                {{ $isWithdrawalLockedByCod ? 'Locked until COD remitted' : 'Withdrawable when >= ₱100' }}
            </span>
        </div>
        <div class="p-5 bg-surface rounded-2xl border border-border-subtle shadow-xs">
            <span class="text-[10px] font-bold text-text-muted uppercase">Pending Hub Escrow</span>
            <p class="text-2xl font-serif font-bold text-amber-600 mt-1.5">&#8369;{{ number_format($pendingEscrow, 2) }}</p>
            <span class="text-[10px] text-text-muted mt-1 block">Releases on Admin COD Verify</span>
        </div>
        <div class="p-5 bg-surface rounded-2xl border {{ $isWithdrawalLockedByCod ? 'border-amber-400 bg-amber-50/40' : 'border-border-subtle' }} shadow-xs">
            <span class="text-[10px] font-bold text-text-muted uppercase">Unremitted COD at Hub</span>
            <p class="text-2xl font-serif font-bold {{ $isWithdrawalLockedByCod ? 'text-danger' : 'text-green-700' }} mt-1.5">&#8369;{{ number_format($unremittedCodBalance, 2) }}</p>
            <span class="text-[10px] text-text-muted mt-1 block">To Remit Now: &#8369;{{ number_format($readyToRemitAmount, 2) }}</span>
        </div>
        <div class="p-5 bg-surface rounded-2xl border border-border-subtle shadow-xs">
            <span class="text-[10px] font-bold text-text-muted uppercase">Period Hub Net Share</span>
            <p class="text-2xl font-serif font-bold text-green-700 mt-1.5">&#8369;{{ number_format($metrics['logistics_net_90'] ?? 0, 2) }}</p>
            <span class="text-[10px] text-text-muted mt-1 block">Ship/Ops Pool: &#8369;{{ number_format($metrics['gross_shipping_revenue'] ?? 0, 2) }}</span>
        </div>
        <div class="p-5 bg-surface rounded-2xl border border-border-subtle shadow-xs">
            <span class="text-[10px] font-bold text-text-muted uppercase">Courier &amp; Rider Task Pay</span>
            <p class="text-2xl font-serif font-bold text-primary-dark mt-1.5">
                &#8369;{{ number_format(($metrics['courier_share_total'] ?? 0) + ($metrics['rider_share_10'] ?? 0), 2) }}
            </p>
            <span class="text-[10px] text-text-muted mt-1 block">
                Courier: &#8369;{{ number_format($metrics['courier_share_total'] ?? 0, 2) }} &bull; Rider: &#8369;{{ number_format($metrics['rider_share_10'] ?? 0, 2) }}
            </span>
        </div>
        <div class="p-5 bg-surface rounded-2xl border border-border-subtle shadow-xs">
            <span class="text-[10px] font-bold text-text-muted uppercase">Total Hub Withdrawn</span>
            <p class="text-2xl font-serif font-bold text-text-main mt-1.5">&#8369;{{ number_format($metrics['wallet_total_withdrawn'] ?? 0, 2) }}</p>
            <span class="text-[10px] text-text-muted mt-1 block">Disbursed by Admin</span>
        </div>
    </div>

    <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4 border-b border-border-subtle pb-4">
        <div class="flex flex-wrap items-center gap-2">
            <button type="button"
                    @click="activeTab = 'remittances'"
                    :class="activeTab === 'remittances' ? 'bg-primary-dark text-white border-primary-dark shadow-xs' : 'bg-surface text-text-muted border-border-subtle hover:text-text-main'"
                    class="px-4 py-2 rounded-2xl text-xs font-bold border transition-all cursor-pointer inline-flex items-center gap-2">
                <span>1. Rider-to-Hub COD Remittances</span>
                @if($pendingRemittancesCount > 0)
                    <span class="px-2 py-0.5 rounded-full text-[10px] bg-red-500 text-white font-extrabold">{{ $pendingRemittancesCount }}</span>
                @endif
            </button>
            <button type="button"
                    @click="activeTab = 'admin_remittances'"
                    :class="activeTab === 'admin_remittances' ? 'bg-primary-dark text-white border-primary-dark shadow-xs' : 'bg-surface text-text-muted border-border-subtle hover:text-text-main'"
                    class="px-4 py-2 rounded-2xl text-xs font-bold border transition-all cursor-pointer inline-flex items-center gap-2">
                <span>2. Hub-to-Admin GCash Remittances</span>
                @if($unremittedToAdminCount > 0)
                    <span class="px-2 py-0.5 rounded-full text-[10px] bg-amber-500 text-white font-extrabold">{{ $unremittedToAdminCount }} Ready</span>
                @endif
            </button>
            <button type="button"
                    @click="activeTab = 'ledger'"
                    :class="activeTab === 'ledger' ? 'bg-primary-dark text-white border-primary-dark shadow-xs' : 'bg-surface text-text-muted border-border-subtle hover:text-text-main'"
                    class="px-4 py-2 rounded-2xl text-xs font-bold border transition-all cursor-pointer">
                <span>3. Shipping &amp; Operational Fare Split Ledger</span>
            </button>
            <button type="button"
                    @click="activeTab = 'withdrawals'"
                    :class="activeTab === 'withdrawals' ? 'bg-primary-dark text-white border-primary-dark shadow-xs' : 'bg-surface text-text-muted border-border-subtle hover:text-text-main'"
                    class="px-4 py-2 rounded-2xl text-xs font-bold border transition-all cursor-pointer inline-flex items-center gap-2">
                <span>4. Hub Withdrawal Requests ({{ $withdrawalRequests->count() }})</span>
            </button>
        </div>

        <form method="GET" action="{{ route('logistics.finance.index') }}" class="flex flex-wrap items-center gap-2 text-xs">
            <input type="hidden" name="tab" :value="activeTab">
            <input type="date" name="from_date" value="{{ $fromDate }}" class="px-3 py-1.5 rounded-xl border border-border-subtle bg-surface font-semibold">
            <span>to</span>
            <input type="date" name="to_date" value="{{ $toDate }}" class="px-3 py-1.5 rounded-xl border border-border-subtle bg-surface font-semibold">
            <button type="submit" class="px-4 py-1.5 rounded-xl bg-primary-dark text-white font-bold cursor-pointer">
                Apply Filter
            </button>
        </form>
    </div>

    <!-- TAB 1: Rider-to-Hub COD Remittances -->
    <div x-show="activeTab === 'remittances'" class="bg-surface rounded-3xl border border-border-subtle shadow-xs overflow-hidden">
        <div class="p-5 border-b border-border-subtle bg-surface-subtle/50 flex flex-wrap items-center justify-between gap-2">
            <div>
                <h2 class="text-base font-bold text-primary-dark">Stage 1: Rider-to-Logistics 100% Gross COD Remittances</h2>
                <p class="text-xs text-text-muted">
                    Riders remit 100% of collected COD cash to your sorting hub. After verifying receipt here, remit the batch to Storkia Admin in Tab 2.
                </p>
            </div>
        </div>
        @if($remittances->isEmpty())
            <div class="p-10 text-center text-xs text-text-muted">No COD remittance batches submitted by Riders yet.</div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-xs">
                    <thead>
                        <tr class="border-b border-border-subtle bg-surface-subtle/40 text-text-muted uppercase text-[11px] font-bold">
                            <th class="py-3.5 px-5">Remittance Ref</th>
                            <th class="py-3.5 px-4">Rider</th>
                            <th class="py-3.5 px-4">100% Gross COD Remitted</th>
                            <th class="py-3.5 px-4">Rider Task Comm (E-Wallet)</th>
                            <th class="py-3.5 px-4">Status</th>
                            <th class="py-3.5 px-5 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border-subtle">
                        @foreach($remittances as $rem)
                            <tr class="hover:bg-surface-subtle/30">
                                <td class="py-3.5 px-5">
                                    <div class="font-mono font-bold text-primary-dark">{{ $rem->remittance_reference }}</div>
                                    <div class="text-[11px] text-text-muted">{{ $rem->submitted_at?->format('M d, Y h:i A') }}</div>
                                </td>
                                <td class="py-3.5 px-4 font-semibold text-text-main">
                                    {{ $rem->rider?->full_name ?? 'Rider #' . $rem->rider_id }}
                                </td>
                                <td class="py-3.5 px-4 font-extrabold text-primary">&#8369;{{ number_format((float) $rem->total_collected_amount, 2) }}</td>
                                <td class="py-3.5 px-4 text-green-700 font-semibold">
                                    &#8369;{{ number_format((float) $rem->total_rider_commission, 2) }}
                                    <span class="block text-[10px] text-text-muted">From Shipping/Ops Fee</span>
                                </td>
                                <td class="py-3.5 px-4">
                                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase {{ $rem->status === 'verified' ? 'bg-green-100 text-green-700' : 'bg-amber-100 text-amber-800' }}">
                                        {{ $rem->status === 'verified' ? 'Verified at Hub' : 'Submitted by Rider' }}
                                    </span>
                                </td>
                                <td class="py-3.5 px-5 text-right">
                                    @if($rem->status !== 'verified')
                                        <form action="{{ route('logistics.finance.verify-remittance', $rem->id) }}" method="POST" class="inline">
                                            @csrf
                                            <button type="submit" class="px-3.5 py-1.5 rounded-xl bg-green-600 hover:bg-green-700 text-white font-bold text-xs cursor-pointer">
                                                Verify 100% Cash Received
                                            </button>
                                        </form>
                                    @else
                                        <span class="text-[11px] text-green-700 font-bold">Verified {{ $rem->verified_at?->format('M d, h:i A') }}</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    <!-- TAB 2: Logistics-to-Admin GCash Remittances -->
    <div x-show="activeTab === 'admin_remittances'" x-cloak class="space-y-6">
        <div class="bg-surface rounded-3xl border border-border-subtle shadow-xs overflow-hidden">
            <div class="p-5 border-b border-border-subtle bg-surface-subtle/50 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-base font-bold text-primary-dark">Stage 2: Verified Rider COD Collections Ready to Remit to Storkia Admin</h2>
                    <p class="text-xs text-text-muted">
                        Transfer the total verified COD amount to Storkia Admin's GCash. Once Admin verifies receipt, Courier, Rider, Logistics Hub, and Seller commissions are automatically released to Available E-Wallets.
                    </p>
                </div>
                @if($unremittedToAdminCollections->isNotEmpty())
                    <button type="button"
                            @click="sanitizeSenderPhone(); remitAdminModalOpen = true"
                            class="px-5 py-2.5 rounded-2xl bg-primary hover:bg-primary-dark text-white text-xs font-bold shadow-xs cursor-pointer">
                        + Remit All to Admin GCash (&#8369;{{ number_format($readyToRemitAmount, 2) }})
                    </button>
                @endif
            </div>

            @if($unremittedToAdminCollections->isEmpty())
                <div class="p-10 text-center text-xs text-text-muted">
                    No unremitted Rider COD collections are currently sitting at your hub.
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse text-xs">
                        <thead>
                            <tr class="border-b border-border-subtle bg-surface-subtle/40 text-text-muted uppercase text-[11px] font-bold">
                                <th class="py-3.5 px-5">Collection Ref &amp; Order #</th>
                                <th class="py-3.5 px-4">Rider</th>
                                <th class="py-3.5 px-4">Product Base (90% Seller / 10% Admin)</th>
                                <th class="py-3.5 px-4">Shipping &amp; Ops Pool (Hub / Courier / Rider)</th>
                                <th class="py-3.5 px-4">Gross COD Held</th>
                                <th class="py-3.5 px-5 text-right">Hub Verified At</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border-subtle">
                            @foreach($unremittedToAdminCollections as $col)
                                @php
                                    $rec = $col->commissionRecord;
                                    $codAmt = (float) ($col->remitted_amount ?? $col->collected_amount);
                                    $prodSub = (float) ($rec?->product_subtotal ?? $col->product_subtotal);
                                    $shipFee = (float) ($rec?->shipping_fee ?? $col->shipping_fee);
                                    $hubEarn = (float) ($rec?->logistics_total_earning ?? 0);
                                    $courierEarn = (float) ($rec?->courier_commission_amount ?? 0);
                                    $riderEarn = (float) ($rec?->rider_commission_amount ?? 0);
                                @endphp
                                <tr class="hover:bg-surface-subtle/30">
                                    <td class="py-3.5 px-5">
                                        <div class="font-mono font-bold text-primary-dark">{{ $col->collection_reference }}</div>
                                        <div class="text-[11px] text-text-muted">
                                            Order #{{ $col->sellerOrder->seller_order_number ?? $col->seller_order_id }} &bull; {{ $col->shipment->tracking_number ?? '' }}
                                        </div>
                                    </td>
                                    <td class="py-3.5 px-4 font-semibold">{{ $col->rider?->full_name ?? 'Rider #' . $col->rider_id }}</td>
                                    <td class="py-3.5 px-4">
                                        <div class="font-bold">&#8369;{{ number_format($prodSub, 2) }}</div>
                                        <div class="text-[10px] text-text-muted">
                                            Seller 90%: &#8369;{{ number_format((float) ($rec?->seller_net_earning ?? ($prodSub * 0.90)), 2) }} &bull;
                                            Admin 10%: &#8369;{{ number_format((float) ($rec?->admin_commission_amount ?? ($prodSub * 0.10)), 2) }}
                                        </div>
                                    </td>
                                    <td class="py-3.5 px-4">
                                        <div class="font-bold">&#8369;{{ number_format($shipFee, 2) }}</div>
                                        <div class="text-[10px] text-text-muted">
                                            Hub: &#8369;{{ number_format($hubEarn, 2) }} &bull;
                                            Courier: &#8369;{{ number_format($courierEarn, 2) }} &bull;
                                            Rider: &#8369;{{ number_format($riderEarn, 2) }}
                                        </div>
                                    </td>
                                    <td class="py-3.5 px-4 font-extrabold text-primary">&#8369;{{ number_format($codAmt, 2) }}</td>
                                    <td class="py-3.5 px-5 text-right text-text-muted">{{ $col->verified_at?->format('M d, Y h:i A') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        <div class="bg-surface rounded-3xl border border-border-subtle shadow-xs overflow-hidden">
            <div class="p-5 border-b border-border-subtle bg-surface-subtle/50">
                <h2 class="text-base font-bold text-primary-dark">Submitted Logistics-to-Admin GCash Remittance Batches</h2>
            </div>
            @if($adminRemittances->isEmpty())
                <div class="p-10 text-center text-xs text-text-muted">No Logistics-to-Admin GCash remittances submitted yet.</div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse text-xs">
                        <thead>
                            <tr class="border-b border-border-subtle bg-surface-subtle/40 text-text-muted uppercase text-[11px] font-bold">
                                <th class="py-3.5 px-5">Batch Ref &amp; Date</th>
                                <th class="py-3.5 px-4">GCash Sender &amp; Ref #</th>
                                <th class="py-3.5 px-4">Total COD Remitted</th>
                                <th class="py-3.5 px-4">Product Pool Split</th>
                                <th class="py-3.5 px-4">Shipping &amp; Ops Pool Split</th>
                                <th class="py-3.5 px-4">Proof &amp; Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border-subtle">
                            @foreach($adminRemittances as $lar)
                                <tr class="hover:bg-surface-subtle/30">
                                    <td class="py-3.5 px-5">
                                        <div class="font-mono font-bold text-primary-dark">{{ $lar->remittance_reference }}</div>
                                        <div class="text-[11px] text-text-muted">{{ $lar->submitted_at?->format('M d, Y h:i A') }} ({{ $lar->items->count() }} order(s))</div>
                                    </td>
                                    <td class="py-3.5 px-4 font-mono">
                                        <div class="font-sans font-bold text-text-main">{{ $lar->sender_account_name }} ({{ $lar->sender_account_number }})</div>
                                        <div class="text-blue-800 font-bold">Ref: #{{ $lar->gcash_reference_number }}</div>
                                    </td>
                                    <td class="py-3.5 px-4 font-serif font-extrabold text-base text-primary">
                                        &#8369;{{ number_format((float) $lar->total_cod_amount, 2) }}
                                    </td>
                                    <td class="py-3.5 px-4">
                                        <div class="font-semibold">Base: &#8369;{{ number_format((float) $lar->total_product_subtotal, 2) }}</div>
                                        <div class="text-[10px] text-text-muted">
                                            Seller 90%: &#8369;{{ number_format((float) $lar->total_seller_net, 2) }} &bull;
                                            Admin 10%: &#8369;{{ number_format((float) $lar->total_admin_commission, 2) }}
                                        </div>
                                    </td>
                                    <td class="py-3.5 px-4">
                                        <div class="font-semibold">Ship/Ops: &#8369;{{ number_format((float) $lar->total_shipping_fee, 2) }}</div>
                                        <div class="text-[10px] text-text-muted">
                                            Hub: &#8369;{{ number_format((float) $lar->total_logistics_earning, 2) }} &bull;
                                            Courier: &#8369;{{ number_format((float) $lar->total_courier_commission, 2) }} &bull;
                                            Rider: &#8369;{{ number_format((float) $lar->total_rider_commission, 2) }}
                                        </div>
                                    </td>
                                    <td class="py-3.5 px-4">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase {{ $lar->status === 'verified' ? 'bg-green-100 text-green-700' : ($lar->status === 'rejected' ? 'bg-red-100 text-danger' : 'bg-amber-100 text-amber-800') }}">
                                                {{ str_replace('_', ' ', $lar->status) }}
                                            </span>
                                            @if($lar->proof_image_path)
                                                <button type="button"
                                                        @click="screenshotModalUrl = '{{ asset('storage/' . ltrim($lar->proof_image_path, '/')) }}'; screenshotModalOpen = true"
                                                        class="px-2.5 py-1 rounded-lg bg-blue-50 text-blue-800 border border-blue-200 font-bold text-[10px] cursor-pointer">
                                                    Receipt
                                                </button>
                                            @endif
                                        </div>
                                        @if($lar->rejection_reason)
                                            <div class="text-[11px] text-danger font-semibold mt-1">Rejected: "{{ $lar->rejection_reason }}"</div>
                                        @elseif($lar->verified_at)
                                            <div class="text-[10px] text-green-700 font-semibold mt-1">Verified {{ $lar->verified_at->format('M d, Y h:i A') }}</div>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    <!-- TAB 3: Per-Shipment Shipping & Operational Fee Split Ledger -->
    <div x-show="activeTab === 'ledger'" x-cloak class="bg-surface rounded-3xl border border-border-subtle shadow-xs overflow-hidden">
        <div class="p-5 border-b border-border-subtle bg-surface-subtle/50">
            <h2 class="text-base font-bold text-primary-dark">Per-Shipment Shipping &amp; Operational Fee Split Ledger ({{ $fromDate }} to {{ $toDate }})</h2>
            <p class="text-xs text-text-muted">
                All Logistics Hub, First-Mile Courier, and Last-Mile Rider commissions are deducted strictly from the Shipping &amp; Operational Fare Fee Pool.
            </p>
        </div>
        @if($commissionRecords->isEmpty())
            <div class="p-10 text-center text-xs text-text-muted">No completed shipment commission records in this date range.</div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-xs">
                    <thead>
                        <tr class="border-b border-border-subtle bg-surface-subtle/40 text-text-muted uppercase text-[11px] font-bold">
                            <th class="py-3.5 px-5">Tracking / Order #</th>
                            <th class="py-3.5 px-4">Shipping &amp; Ops Fee Pool</th>
                            <th class="py-3.5 px-4">Hub Net Share</th>
                            <th class="py-3.5 px-4">Courier Pickup Task Pay</th>
                            <th class="py-3.5 px-4">Rider Delivery Task Pay</th>
                            <th class="py-3.5 px-4">Settlement Stage</th>
                            <th class="py-3.5 px-5 text-right">Date</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border-subtle">
                        @foreach($commissionRecords as $rec)
                            @php
                                $hubNet = (float) ($rec->logistics_total_earning ?: ((float) $rec->logistics_commission_amount + (float) ($rec->logistics_operational_revenue ?? $rec->logistics_operational_amount ?? 0)));
                                $courierPay = (float) ($rec->courier_commission_amount ?? 0);
                                $riderPay = (float) $rec->rider_commission_amount;
                                $stage = $rec->cod_remittance_stage ?? $rec->status;
                            @endphp
                            <tr class="hover:bg-surface-subtle/30">
                                <td class="py-3.5 px-5 font-mono font-bold text-primary-dark">
                                    {{ $rec->shipment->tracking_number ?? ('#' . $rec->shipment_id) }}
                                    <span class="text-text-muted font-normal ml-1">({{ $rec->sellerOrder->seller_order_number ?? '' }})</span>
                                </td>
                                <td class="py-3.5 px-4 font-bold">&#8369;{{ number_format((float) $rec->shipping_fee, 2) }}</td>
                                <td class="py-3.5 px-4 font-extrabold text-green-700">&#8369;{{ number_format($hubNet, 2) }}</td>
                                <td class="py-3.5 px-4 text-amber-800 font-semibold">&#8369;{{ number_format($courierPay, 2) }}</td>
                                <td class="py-3.5 px-4 text-blue-700 font-semibold">&#8369;{{ number_format($riderPay, 2) }}</td>
                                <td class="py-3.5 px-4">
                                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase {{ $stage === 'settled' ? 'bg-green-100 text-green-700' : 'bg-amber-100 text-amber-800' }}">
                                        {{ str_replace('_', ' ', (string) $stage) }}
                                    </span>
                                </td>
                                <td class="py-3.5 px-5 text-right text-text-muted">{{ $rec->created_at?->format('M d, Y h:i A') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    <!-- TAB 4: Logistics Hub E-Wallet Withdrawal Requests -->
    <div x-show="activeTab === 'withdrawals'" x-cloak class="bg-surface rounded-3xl border border-border-subtle shadow-xs overflow-hidden">
        <div class="p-5 border-b border-border-subtle bg-surface-subtle/50 flex flex-wrap items-center justify-between gap-2">
            <div>
                <h2 class="text-base font-bold text-primary-dark">Logistics Hub E-Wallet Withdrawal Requests</h2>
                <p class="text-xs text-text-muted">Submitted withdrawal requests are reviewed and disbursed by Storkia Admin once all Rider-remitted COD has been verified by Admin.</p>
            </div>
            <button type="button"
                    @click="openWithdrawModal()"
                    @if(! $canRequestWithdrawal) disabled @endif
                    class="px-4 py-2 rounded-xl text-xs font-bold {{ $canRequestWithdrawal ? 'bg-green-600 text-white cursor-pointer' : 'bg-gray-200 text-gray-500 cursor-not-allowed' }}">
                + New Withdrawal Request
            </button>
        </div>
        @if($withdrawalRequests->isEmpty())
            <div class="p-10 text-center text-xs text-text-muted">No withdrawal requests submitted yet.</div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-xs">
                    <thead>
                        <tr class="border-b border-border-subtle bg-surface-subtle/40 text-text-muted uppercase text-[11px] font-bold">
                            <th class="py-3.5 px-5">Reference #</th>
                            <th class="py-3.5 px-4">Payout Destination</th>
                            <th class="py-3.5 px-4">Requested Amount</th>
                            <th class="py-3.5 px-4">Status</th>
                            <th class="py-3.5 px-4">Admin Remarks / Proof Ref</th>
                            <th class="py-3.5 px-5 text-right">Submitted At</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border-subtle">
                        @foreach($withdrawalRequests as $wr)
                            <tr class="hover:bg-surface-subtle/30">
                                <td class="py-3.5 px-5 font-mono font-bold text-primary-dark">{{ $wr->withdrawal_reference }}</td>
                                <td class="py-3.5 px-4">
                                    <span class="px-2 py-0.5 rounded bg-brand-light/40 text-primary-dark font-bold text-[10px] mr-1">{{ $wr->payout_method }}</span>
                                    <span class="font-semibold text-text-main">{{ $wr->account_name }}</span>
                                    <span class="font-mono text-text-muted ml-1">({{ $wr->account_number }})</span>
                                </td>
                                <td class="py-3.5 px-4 font-bold text-green-700">&#8369;{{ number_format((float) $wr->requested_amount, 2) }}</td>
                                <td class="py-3.5 px-4">
                                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase {{ $wr->status === 'completed' ? 'bg-green-100 text-green-700' : ($wr->status === 'rejected' ? 'bg-red-100 text-danger' : 'bg-amber-100 text-amber-800') }}">
                                        {{ $wr->status }}
                                    </span>
                                </td>
                                <td class="py-3.5 px-4 text-text-muted">
                                    @if($wr->payout_proof_reference)
                                        <span class="font-mono font-bold text-green-700">Disbursed Ref: {{ $wr->payout_proof_reference }}</span>
                                    @elseif($wr->rejection_reason)
                                        <span class="text-danger font-semibold">Rejected: "{{ $wr->rejection_reason }}" (Refunded)</span>
                                    @else
                                        <span>{{ $wr->admin_remarks ?? 'Awaiting Admin review' }}</span>
                                    @endif
                                </td>
                                <td class="py-3.5 px-5 text-right text-text-muted">{{ $wr->created_at?->format('M d, Y h:i A') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    <!-- Modal: Remit Verified Rider COD to Storkia Admin GCash -->
    <template x-teleport="body">
        <div x-show="remitAdminModalOpen"
             x-cloak
             class="fixed inset-0 z-[195] flex items-center justify-center p-4">
            <div @click="if (!isSubmittingAdminRemit) remitAdminModalOpen = false" class="absolute inset-0 bg-black/60 backdrop-blur-xs cursor-pointer"></div>
            <div @click.stop class="relative z-10 bg-surface rounded-3xl max-w-lg w-full p-6 shadow-2xl border border-border-subtle space-y-4 max-h-[92vh] overflow-y-auto">
                <div class="flex items-center justify-between pb-3 border-b border-border-subtle">
                    <div>
                        <h3 class="text-base font-bold text-primary-dark">Remit Collected COD to Storkia Admin GCash</h3>
                        <p class="text-xs text-text-muted">Batch includes {{ $unremittedToAdminCount }} verified Rider COD collection(s).</p>
                    </div>
                    <button type="button" @click="if (!isSubmittingAdminRemit) remitAdminModalOpen = false" class="p-1 text-text-muted hover:text-text-main cursor-pointer">&times;</button>
                </div>

                @php
                    $previewProdSub = round((float) $unremittedToAdminCollections->sum(fn ($c) => (float) ($c->commissionRecord?->product_subtotal ?? $c->product_subtotal)), 2);
                    $previewShipFee = round((float) $unremittedToAdminCollections->sum(fn ($c) => (float) ($c->commissionRecord?->shipping_fee ?? $c->shipping_fee)), 2);
                    $previewAdmin10 = round((float) $unremittedToAdminCollections->sum(fn ($c) => (float) ($c->commissionRecord?->admin_commission_amount ?? round((float) $c->product_subtotal * 0.10, 2))), 2);
                    $previewSeller90 = round((float) $unremittedToAdminCollections->sum(fn ($c) => (float) ($c->commissionRecord?->seller_net_earning ?? round((float) $c->product_subtotal * 0.90, 2))), 2);
                    $previewCourier = round((float) $unremittedToAdminCollections->sum(fn ($c) => (float) ($c->commissionRecord?->courier_commission_amount ?? 0)), 2);
                    $previewRider = round((float) $unremittedToAdminCollections->sum(fn ($c) => (float) ($c->commissionRecord?->rider_commission_amount ?? round((float) $c->shipping_fee * 0.10, 2))), 2);
                    $previewHub = round((float) $unremittedToAdminCollections->sum(fn ($c) => (float) ($c->commissionRecord?->logistics_total_earning ?? max(0, (float) $c->shipping_fee - (float) ($c->commissionRecord?->courier_commission_amount ?? 0) - (float) ($c->commissionRecord?->rider_commission_amount ?? 0)))), 2);
                @endphp

                <div class="p-4 rounded-2xl bg-brand-light/25 border border-border-subtle space-y-2 text-xs">
                    <div class="flex items-center justify-between">
                        <span class="font-bold text-text-muted uppercase text-[10px]">Storkia Admin GCash Destination</span>
                        <span class="px-2 py-0.5 rounded bg-blue-100 text-blue-800 font-bold text-[10px]">GCash Escrow</span>
                    </div>
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <div>
                            <div class="font-bold text-sm text-primary-dark">{{ $platformGcash->account_name ?? 'Storkia Admin Escrow' }}</div>
                            <div class="font-mono font-extrabold text-primary text-sm">{{ $platformGcash->gcash_number ?? '0917-000-0000 (Configure in Admin Settings)' }}</div>
                        </div>
                        <div class="text-right">
                            <span class="text-[10px] uppercase font-bold text-text-muted block">Exact Amount to Send</span>
                            <span class="text-xl font-serif font-extrabold text-primary">&#8369;{{ number_format($readyToRemitAmount, 2) }}</span>
                        </div>
                    </div>
                    @if(!empty($platformGcash?->qr_image_path))
                        <div class="pt-2 flex justify-center">
                            <img src="{{ asset('storage/' . ltrim($platformGcash->qr_image_path, '/')) }}" alt="Admin GCash QR" class="w-36 h-36 object-contain rounded-xl border border-border-subtle bg-white p-1.5">
                        </div>
                    @endif
                </div>

                <div class="p-3.5 rounded-2xl bg-surface-subtle border border-border-subtle space-y-1.5 text-[11px]">
                    <div class="font-extrabold text-primary-dark uppercase text-[10px]">Automatic E-Wallet Distribution Upon Admin Verification:</div>
                    <div class="flex justify-between">
                        <span class="text-text-muted">Seller 90% Merchandise Share (from Product Price):</span>
                        <strong class="text-text-main">&#8369;{{ number_format($previewSeller90, 2) }}</strong>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-text-muted">Admin 10% Platform Commission (from Product Price):</span>
                        <strong class="text-primary">&#8369;{{ number_format($previewAdmin10, 2) }}</strong>
                    </div>
                    <div class="flex justify-between pt-1 border-t border-border-subtle">
                        <span class="text-text-muted">Your Logistics Hub Share (from Shipping &amp; Ops Fee):</span>
                        <strong class="text-green-700">&#8369;{{ number_format($previewHub, 2) }}</strong>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-text-muted">First-Mile Courier Task Pay (from Shipping &amp; Ops Fee):</span>
                        <strong class="text-amber-800">&#8369;{{ number_format($previewCourier, 2) }}</strong>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-text-muted">Last-Mile Rider Task Pay (from Shipping &amp; Ops Fee):</span>
                        <strong class="text-blue-700">&#8369;{{ number_format($previewRider, 2) }}</strong>
                    </div>
                </div>

                <form action="{{ route('logistics.finance.remit-to-admin') }}"
                      method="POST"
                      enctype="multipart/form-data"
                      @submit="isSubmittingAdminRemit = true"
                      class="space-y-4 text-xs">
                    @csrf
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block font-bold text-text-muted uppercase mb-1">Sender GCash Name <span class="text-danger">*</span></label>
                            <input type="text"
                                   name="sender_account_name"
                                   x-model="senderAccountName"
                                   required
                                   minlength="2"
                                   maxlength="120"
                                   class="w-full p-3 rounded-xl border border-border-subtle bg-surface font-semibold">
                        </div>
                        <div>
                            <label class="block font-bold text-text-muted uppercase mb-1">Sender GCash Number <span class="text-danger">*</span></label>
                            <input type="text"
                                   name="sender_account_number"
                                   x-model="senderAccountNumber"
                                   @input="sanitizeSenderPhone()"
                                   required
                                   maxlength="11"
                                   placeholder="09171234567"
                                   class="w-full p-3 rounded-xl border border-border-subtle bg-surface font-mono font-bold">
                        </div>
                    </div>

                    <div>
                        <label class="block font-bold text-text-muted uppercase mb-1">GCash Reference Number <span class="text-danger">*</span></label>
                        <input type="text"
                               name="gcash_reference_number"
                               x-model="gcashReferenceNumber"
                               required
                               minlength="4"
                               maxlength="80"
                               placeholder="Enter 13-digit GCash Ref #"
                               class="w-full p-3 rounded-xl border border-border-subtle bg-surface font-mono font-bold uppercase text-primary-dark">
                    </div>

                    <div>
                        <label class="block font-bold text-text-muted uppercase mb-1">GCash Transfer Screenshot (Max 5MB) <span class="text-danger">*</span></label>
                        <input type="file"
                               name="proof_image"
                               accept="image/jpeg,image/png,image/jpg,image/webp"
                               required
                               class="w-full p-2.5 rounded-xl border border-border-subtle bg-surface file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:bg-primary/10 file:text-primary file:font-bold">
                    </div>

                    <div class="flex justify-end gap-2 pt-2 border-t border-border-subtle">
                        <button type="button"
                                @click="remitAdminModalOpen = false"
                                :disabled="isSubmittingAdminRemit"
                                class="px-4 py-2.5 rounded-xl bg-surface-subtle text-text-muted font-bold cursor-pointer">
                            Cancel
                        </button>
                        <button type="submit"
                                :disabled="isSubmittingAdminRemit"
                                class="px-5 py-2.5 rounded-xl bg-primary hover:bg-primary-dark text-white font-bold shadow-xs cursor-pointer disabled:opacity-50">
                            <span x-show="!isSubmittingAdminRemit">Submit &#8369;{{ number_format($readyToRemitAmount, 2) }} Remittance to Admin</span>
                            <span x-show="isSubmittingAdminRemit" x-cloak>Submitting...</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </template>

    <!-- Logistics E-Wallet Withdrawal Modal -->
    <template x-teleport="body">
        <div x-show="withdrawModalOpen"
             x-cloak
             class="fixed inset-0 z-[195] flex items-center justify-center p-4">
            <div @click="if (!isSubmittingWithdraw) { withdrawModalOpen = false; withdrawConfirmStep = false; }" class="absolute inset-0 bg-black/60 backdrop-blur-xs cursor-pointer"></div>
            <div @click.stop class="relative z-10 bg-surface rounded-3xl max-w-md w-full p-6 shadow-2xl border border-border-subtle space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-border-subtle">
                    <div>
                        <h3 class="text-base font-bold text-primary-dark" x-text="withdrawConfirmStep ? 'Confirm Hub Withdrawal Details' : 'Request Hub E-Wallet Withdrawal'"></h3>
                        <p class="text-xs text-text-muted">Available Balance: <strong class="text-green-700">&#8369;{{ number_format($availableBalance, 2) }}</strong></p>
                    </div>
                    <button type="button"
                            @click="if (!isSubmittingWithdraw) { withdrawModalOpen = false; withdrawConfirmStep = false; }"
                            class="p-1 text-text-muted hover:text-text-main cursor-pointer">&times;</button>
                </div>

                <form x-ref="logisticsWithdrawForm"
                      action="{{ route('logistics.finance.withdrawals.store') }}"
                      method="POST"
                      @submit.prevent="if (!withdrawConfirmStep) { reviewWithdrawRequest(); } else { confirmAndSubmitWithdraw(); }"
                      class="space-y-4 text-xs">
                    @csrf
                    <input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                    <input type="hidden" name="payout_method" value="GCASH">

                    <div x-show="!withdrawConfirmStep" class="space-y-4">
                        <div>
                            <label class="block font-bold text-text-muted uppercase mb-1">Withdrawal Amount (&#8369;) <span class="text-danger">*</span></label>
                            <input type="number"
                                   step="0.01"
                                   min="100"
                                   max="{{ number_format($availableBalance, 2, '.', '') }}"
                                   name="requested_amount"
                                   x-model="requestedAmount"
                                   required
                                   class="w-full p-3 rounded-xl border border-border-subtle bg-surface font-bold text-sm text-primary-dark outline-none focus:border-primary">
                            <span class="text-[11px] text-text-muted mt-1 block">Minimum &#8369;100.00 &bull; Max &#8369;{{ number_format($availableBalance, 2) }}</span>
                        </div>

                        <div>
                            <label class="block font-bold text-text-muted uppercase mb-1">Payout Method (Read-Only)</label>
                            <input type="text"
                                   value="GCash"
                                   readonly
                                   class="w-full p-3 rounded-xl border border-border-subtle bg-gray-100 text-gray-600 font-bold cursor-not-allowed select-none outline-none">
                        </div>

                        <div>
                            <label class="block font-bold text-text-muted uppercase mb-1">GCash Account Holder Name <span class="text-danger">*</span></label>
                            <input type="text"
                                   name="account_name"
                                   x-model="accountName"
                                   required
                                   minlength="2"
                                   maxlength="120"
                                   placeholder="Enter full name registered on GCash"
                                   class="w-full p-3 rounded-xl border border-border-subtle bg-surface font-semibold text-text-main outline-none focus:border-primary">
                        </div>

                        <div>
                            <label class="block font-bold text-text-muted uppercase mb-1">GCash Mobile Number (11 Digits) <span class="text-danger">*</span></label>
                            <input type="text"
                                   name="account_number"
                                   x-model="accountNumber"
                                   @input="sanitizeAccountNumber()"
                                   required
                                   maxlength="11"
                                   placeholder="09171234567"
                                   class="w-full p-3 rounded-xl border border-border-subtle bg-surface font-mono font-bold text-primary-dark outline-none focus:border-primary">
                        </div>

                        <template x-if="withdrawError">
                            <div class="p-3 rounded-xl bg-red-50 border border-red-200 text-danger font-bold text-xs" x-text="withdrawError"></div>
                        </template>

                        <div class="flex justify-end gap-2 pt-2 border-t border-border-subtle">
                            <button type="button"
                                    @click="withdrawModalOpen = false; withdrawConfirmStep = false;"
                                    class="px-4 py-2.5 rounded-xl bg-surface-subtle text-text-muted font-bold cursor-pointer">
                                Cancel
                            </button>
                            <button type="button"
                                    @click="reviewWithdrawRequest()"
                                    class="px-5 py-2.5 rounded-xl bg-green-600 hover:bg-green-700 text-white font-bold shadow-xs cursor-pointer">
                                Review Withdrawal Request &rarr;
                            </button>
                        </div>
                    </div>

                    <div x-show="withdrawConfirmStep" x-cloak class="space-y-4">
                        <div class="p-3.5 rounded-2xl bg-amber-50 border border-amber-300 text-amber-950 space-y-1">
                            <div class="font-extrabold text-xs uppercase tracking-wider text-amber-900">Please Confirm Your GCash Details</div>
                            <p class="text-[11px] leading-relaxed">
                                Please verify that the <strong>GCash Account Holder Name</strong> and <strong>11-Digit GCash Number</strong> below are accurate before proceeding.
                            </p>
                        </div>

                        <div class="p-4 rounded-2xl bg-surface-subtle border border-border-subtle space-y-2.5 text-xs">
                            <div class="flex items-center justify-between">
                                <span class="text-text-muted">Withdrawal Amount:</span>
                                <strong class="text-base font-serif font-extrabold text-green-700"
                                        x-text="'\u20B1' + Number(requestedAmount || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })"></strong>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-text-muted">Payout Channel:</span>
                                <strong class="px-2 py-0.5 rounded bg-blue-100 text-blue-800 font-bold text-[10px]">GCash</strong>
                            </div>
                            <div class="flex items-center justify-between pt-2 border-t border-border-subtle">
                                <span class="text-text-muted">GCash Account Holder Name:</span>
                                <strong class="text-text-main font-bold" x-text="accountName"></strong>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-text-muted">GCash Mobile Number:</span>
                                <strong class="font-mono text-primary-dark font-extrabold text-sm" x-text="accountNumber"></strong>
                            </div>
                        </div>

                        <div class="flex justify-end gap-2 pt-2 border-t border-border-subtle">
                            <button type="button"
                                    @click="withdrawConfirmStep = false"
                                    :disabled="isSubmittingWithdraw"
                                    class="px-4 py-2.5 rounded-xl bg-surface-subtle text-text-muted hover:text-text-main font-bold cursor-pointer">
                                &larr; Back to Edit
                            </button>
                            <button type="submit"
                                    :disabled="isSubmittingWithdraw"
                                    class="px-5 py-2.5 rounded-xl bg-green-600 hover:bg-green-700 text-white font-bold shadow-xs cursor-pointer disabled:opacity-50">
                                <span x-show="!isSubmittingWithdraw">Confirm &amp; Submit Request</span>
                                <span x-show="isSubmittingWithdraw" x-cloak>Submitting...</span>
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </template>

    <!-- Screenshot Preview Modal -->
    <template x-teleport="body">
        <div x-show="screenshotModalOpen"
             x-cloak
             @click="screenshotModalOpen = false"
             class="fixed inset-0 z-[210] flex items-center justify-center p-4 bg-black/75 backdrop-blur-xs">
            <img :src="screenshotModalUrl" alt="GCash Proof" class="max-w-full max-h-[88vh] rounded-2xl shadow-2xl border border-white/20" @click.stop>
        </div>
    </template>
</div>
@endsection