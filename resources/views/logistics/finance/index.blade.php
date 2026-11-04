@extends('layouts.logistics', ['title' => 'Storkia - Logistics COD & E-Wallet Reports'])

@section('content')
@php
    $withdrawalRequests = $withdrawalRequests ?? collect();
    $remittances = $remittances ?? collect();
    $commissionRecords = $commissionRecords ?? collect();
    $walletTransactions = $walletTransactions ?? collect();
    $settlements = $settlements ?? collect();
    $sellers = $sellers ?? collect();
    $availableBalance = (float) ($metrics['wallet_available'] ?? ($logisticsWallet->available_balance ?? 0));
    $pendingEscrow = (float) ($metrics['wallet_pending'] ?? ($logisticsWallet->pending_balance ?? 0));
    $hasPendingWithdrawal = $withdrawalRequests->whereIn('status', ['pending', 'approved'])->isNotEmpty();
    $pendingRemittancesCount = $remittances->where('status', 'submitted')->count();
@endphp

<div x-data="{
    activeTab: '{{ request()->query('tab', 'remittances') }}',
    withdrawModalOpen: false,
    withdrawConfirmStep: false,
    withdrawError: '',
    settleModalOpen: false,
    payoutMethod: 'GCASH',
    maxWithdrawable: {{ round(max(0, $availableBalance), 2) }},
    requestedAmount: {{ max(100, floor($availableBalance * 100) / 100) }},
    accountName: @js($profile->business_name ?? auth()->user()?->full_name ?? ''),
    accountNumber: @js($profile->contact_no ?? auth()->user()?->contact_no ?? ''),
    isSubmittingWithdraw: false,
    isSubmittingSettle: false,
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
                Shipping Fee Split Policy: Your Logistics Hub earns <strong>90%</strong> of the Original Shipping Fee (10% Logistics Commission + 80% Operational Share), while Riders earn 10% + distance bonuses.
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

            <button type="button"
                    @click="openWithdrawModal()"
                    @if($availableBalance < 100 || $hasPendingWithdrawal) disabled @endif
                    class="px-4 py-2.5 rounded-2xl text-xs font-bold shadow-xs transition-colors {{ ($availableBalance >= 100 && !$hasPendingWithdrawal) ? 'bg-green-600 hover:bg-green-700 text-white cursor-pointer' : 'bg-gray-200 text-gray-500 cursor-not-allowed' }}">
                {{ $hasPendingWithdrawal ? 'Withdrawal Pending Admin Review' : 'Request Withdrawal (Min &#8369;100)' }}
            </button>
        </div>
    </div>

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

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
        <div class="p-5 bg-gradient-to-br from-primary-dark to-primary text-white rounded-2xl shadow-sm">
            <span class="text-[11px] font-bold text-white/80 uppercase">Available Hub E-Wallet (90%)</span>
            <p class="text-2xl font-serif font-extrabold mt-2">&#8369;{{ number_format($availableBalance, 2) }}</p>
            <span class="text-[10px] text-white/85 mt-1 block">Withdrawable when &ge; &#8369;100.00</span>
        </div>
        <div class="p-5 bg-surface rounded-2xl border border-border-subtle shadow-xs">
            <span class="text-[11px] font-bold text-text-muted uppercase">Pending Escrow Shipping</span>
            <p class="text-2xl font-serif font-bold text-amber-600 mt-2">&#8369;{{ number_format($pendingEscrow, 2) }}</p>
            <span class="text-[10px] text-text-muted mt-1 block">Delivered &bull; Awaiting Buyer Confirm</span>
        </div>
        <div class="p-5 bg-surface rounded-2xl border border-border-subtle shadow-xs">
            <span class="text-[11px] font-bold text-text-muted uppercase">Period Hub Net (90% Ship)</span>
            <p class="text-2xl font-serif font-bold text-green-700 mt-2">&#8369;{{ number_format($metrics['logistics_net_90'] ?? 0, 2) }}</p>
            <span class="text-[10px] text-text-muted mt-1 block">Gross Ship: &#8369;{{ number_format($metrics['gross_shipping_revenue'] ?? 0, 2) }}</span>
        </div>
        <div class="p-5 bg-surface rounded-2xl border border-border-subtle shadow-xs">
            <span class="text-[11px] font-bold text-text-muted uppercase">Verified COD Remitted</span>
            <p class="text-2xl font-serif font-bold text-primary-dark mt-2">&#8369;{{ number_format($metrics['verified_cod_remitted'] ?? 0, 2) }}</p>
            <span class="text-[10px] text-text-muted mt-1 block">Pending Batch: &#8369;{{ number_format($metrics['pending_cod_remittances'] ?? 0, 2) }}</span>
        </div>
        <div class="p-5 bg-surface rounded-2xl border border-border-subtle shadow-xs">
            <span class="text-[11px] font-bold text-text-muted uppercase">Total Withdrawn</span>
            <p class="text-2xl font-serif font-bold text-text-main mt-2">&#8369;{{ number_format($metrics['wallet_total_withdrawn'] ?? 0, 2) }}</p>
            <span class="text-[10px] text-text-muted mt-1 block">Lifetime Disbursed by Admin</span>
        </div>
    </div>

    <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4 border-b border-border-subtle pb-4">
        <div class="flex flex-wrap items-center gap-2">
            <button type="button"
                    @click="activeTab = 'remittances'"
                    :class="activeTab === 'remittances' ? 'bg-primary-dark text-white border-primary-dark shadow-xs' : 'bg-surface text-text-muted border-border-subtle hover:text-text-main'"
                    class="px-4 py-2 rounded-2xl text-xs font-bold border transition-all cursor-pointer inline-flex items-center gap-2">
                <span>Rider COD Remittances</span>
                @if($pendingRemittancesCount > 0)
                    <span class="px-2 py-0.5 rounded-full text-[10px] bg-red-500 text-white font-extrabold">{{ $pendingRemittancesCount }}</span>
                @endif
            </button>
            <button type="button"
                    @click="activeTab = 'withdrawals'"
                    :class="activeTab === 'withdrawals' ? 'bg-primary-dark text-white border-primary-dark shadow-xs' : 'bg-surface text-text-muted border-border-subtle hover:text-text-main'"
                    class="px-4 py-2 rounded-2xl text-xs font-bold border transition-all cursor-pointer inline-flex items-center gap-2">
                <span>Hub Withdrawal Requests ({{ $withdrawalRequests->count() }})</span>
            </button>
            <button type="button"
                    @click="activeTab = 'ledger'"
                    :class="activeTab === 'ledger' ? 'bg-primary-dark text-white border-primary-dark shadow-xs' : 'bg-surface text-text-muted border-border-subtle hover:text-text-main'"
                    class="px-4 py-2 rounded-2xl text-xs font-bold border transition-all cursor-pointer">
                <span>Shipping Fee Split &amp; Wallet Ledger</span>
            </button>
            <button type="button"
                    @click="activeTab = 'settlements'"
                    :class="activeTab === 'settlements' ? 'bg-primary-dark text-white border-primary-dark shadow-xs' : 'bg-surface text-text-muted border-border-subtle hover:text-text-main'"
                    class="px-4 py-2 rounded-2xl text-xs font-bold border transition-all cursor-pointer">
                <span>Seller COD Settlements ({{ $settlements->count() }})</span>
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

    <div x-show="activeTab === 'remittances'" class="bg-surface rounded-3xl border border-border-subtle shadow-xs overflow-hidden">
        <div class="p-5 border-b border-border-subtle bg-surface-subtle/50 flex flex-wrap items-center justify-between gap-2">
            <div>
                <h2 class="text-base font-bold text-primary-dark">Rider COD Remittance Batches (FIFO)</h2>
                <p class="text-xs text-text-muted">Verify cash remitted by your Last-Mile Riders at the sorting hub.</p>
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
                            <th class="py-3.5 px-4">Total COD Collected</th>
                            <th class="py-3.5 px-4">Rider 10% Ship Comm</th>
                            <th class="py-3.5 px-4">Net Cash Remitted</th>
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
                                <td class="py-3.5 px-4 font-bold">&#8369;{{ number_format((float) $rem->total_collected_amount, 2) }}</td>
                                <td class="py-3.5 px-4 text-green-700 font-semibold">&#8369;{{ number_format((float) $rem->total_rider_commission, 2) }}</td>
                                <td class="py-3.5 px-4 font-extrabold text-primary">&#8369;{{ number_format((float) $rem->net_remitted_amount, 2) }}</td>
                                <td class="py-3.5 px-4">
                                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase {{ $rem->status === 'verified' ? 'bg-green-100 text-green-700' : 'bg-amber-100 text-amber-800' }}">
                                        {{ $rem->status }}
                                    </span>
                                </td>
                                <td class="py-3.5 px-5 text-right">
                                    @if($rem->status !== 'verified')
                                        <form action="{{ route('logistics.finance.verify-remittance', $rem->id) }}" method="POST" class="inline">
                                            @csrf
                                            <button type="submit" class="px-3.5 py-1.5 rounded-xl bg-green-600 hover:bg-green-700 text-white font-bold text-xs cursor-pointer">
                                                Verify Cash Received
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

    <div x-show="activeTab === 'withdrawals'" x-cloak class="bg-surface rounded-3xl border border-border-subtle shadow-xs overflow-hidden">
        <div class="p-5 border-b border-border-subtle bg-surface-subtle/50 flex flex-wrap items-center justify-between gap-2">
            <div>
                <h2 class="text-base font-bold text-primary-dark">Logistics Hub E-Wallet Withdrawal Requests</h2>
                <p class="text-xs text-text-muted">Submitted withdrawal requests are reviewed and disbursed by Storkia Admin.</p>
            </div>
            <button type="button"
                    @click="openWithdrawModal()"
                    @if($availableBalance < 100 || $hasPendingWithdrawal) disabled @endif
                    class="px-4 py-2 rounded-xl text-xs font-bold {{ ($availableBalance >= 100 && !$hasPendingWithdrawal) ? 'bg-green-600 text-white cursor-pointer' : 'bg-gray-200 text-gray-500 cursor-not-allowed' }}">
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

    <div x-show="activeTab === 'ledger'" x-cloak class="bg-surface rounded-3xl border border-border-subtle shadow-xs overflow-hidden">
        <div class="p-5 border-b border-border-subtle bg-surface-subtle/50">
            <h2 class="text-base font-bold text-primary-dark">Per-Shipment Shipping Fee Split Ledger ({{ $fromDate }} to {{ $toDate }})</h2>
        </div>
        @if($commissionRecords->isEmpty())
            <div class="p-10 text-center text-xs text-text-muted">No completed shipment commission records in this date range.</div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-xs">
                    <thead>
                        <tr class="border-b border-border-subtle bg-surface-subtle/40 text-text-muted uppercase text-[11px] font-bold">
                            <th class="py-3.5 px-5">Tracking / Order #</th>
                            <th class="py-3.5 px-4">Original Shipping Fee</th>
                            <th class="py-3.5 px-4">Hub Net 90% (10% + 80%)</th>
                            <th class="py-3.5 px-4">Rider Share (10%)</th>
                            <th class="py-3.5 px-5 text-right">Date</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border-subtle">
                        @foreach($commissionRecords as $rec)
                            @php
                                $hubNet90 = (float) ($rec->logistics_total_earning ?: ((float) $rec->logistics_commission_amount + (float) ($rec->logistics_operational_revenue ?? $rec->logistics_operational_amount ?? 0)));
                            @endphp
                            <tr class="hover:bg-surface-subtle/30">
                                <td class="py-3.5 px-5 font-mono font-bold text-primary-dark">
                                    {{ $rec->shipment->tracking_number ?? ('#' . $rec->shipment_id) }}
                                    <span class="text-text-muted font-normal ml-1">({{ $rec->sellerOrder->seller_order_number ?? '' }})</span>
                                </td>
                                <td class="py-3.5 px-4 font-semibold">&#8369;{{ number_format((float) $rec->shipping_fee, 2) }}</td>
                                <td class="py-3.5 px-4 font-extrabold text-green-700">
                                    &#8369;{{ number_format($hubNet90, 2) }}
                                </td>
                                <td class="py-3.5 px-4 text-blue-700 font-semibold">&#8369;{{ number_format((float) $rec->rider_commission_amount, 2) }}</td>
                                <td class="py-3.5 px-5 text-right text-text-muted">{{ $rec->created_at?->format('M d, Y h:i A') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    <div x-show="activeTab === 'settlements'" x-cloak class="bg-surface rounded-3xl border border-border-subtle shadow-xs overflow-hidden">
        <div class="p-5 border-b border-border-subtle bg-surface-subtle/50 flex flex-wrap items-center justify-between gap-2">
            <div>
                <h2 class="text-base font-bold text-primary-dark">Seller COD Merchandise Settlements</h2>
                <p class="text-xs text-text-muted">Record settlements for Sellers whose COD orders were collected by your hub.</p>
            </div>
            <button type="button"
                    @click="settleModalOpen = true"
                    class="px-4 py-2 rounded-xl bg-primary hover:bg-primary-dark text-white text-xs font-bold cursor-pointer">
                + Settle Seller COD Batch
            </button>
        </div>
        @if($settlements->isEmpty())
            <div class="p-10 text-center text-xs text-text-muted">No Seller COD settlement batches recorded yet.</div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-xs">
                    <thead>
                        <tr class="border-b border-border-subtle bg-surface-subtle/40 text-text-muted uppercase text-[11px] font-bold">
                            <th class="py-3.5 px-5">Settlement Ref</th>
                            <th class="py-3.5 px-4">Seller Store</th>
                            <th class="py-3.5 px-4">Orders Count</th>
                            <th class="py-3.5 px-4">Gross Product Sales</th>
                            <th class="py-3.5 px-4">Net Seller Payout (90%)</th>
                            <th class="py-3.5 px-5 text-right">Settled At</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border-subtle">
                        @foreach($settlements as $stl)
                            @php
                                $stlOrdersCount = $stl->orders_count ?? $stl->items_count ?? ($stl->relationLoaded('items') ? $stl->items->count() : 1);
                                $stlGrossSales = (float) ($stl->gross_product_subtotal ?? $stl->gross_product_sales ?? 0);
                                $stlNetPayout = (float) ($stl->net_settlement_amount ?? $stl->net_seller_payout_90 ?? 0);
                            @endphp
                            <tr>
                                <td class="py-3.5 px-5 font-mono font-bold text-primary-dark">{{ $stl->settlement_reference }}</td>
                                <td class="py-3.5 px-4 font-semibold text-text-main">{{ $stl->sellerProfile->business_name ?? 'Seller' }}</td>
                                <td class="py-3.5 px-4">{{ $stlOrdersCount }}</td>
                                <td class="py-3.5 px-4">&#8369;{{ number_format($stlGrossSales, 2) }}</td>
                                <td class="py-3.5 px-4 font-bold text-green-700">&#8369;{{ number_format($stlNetPayout, 2) }}</td>
                                <td class="py-3.5 px-5 text-right text-text-muted">{{ $stl->settled_at?->format('M d, Y h:i A') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    <!-- Logistics E-Wallet Withdrawal Modal with Required GCash Account Holder Name & Confirmation Step -->
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

                    <!-- STEP 1: Enter Withdrawal & GCash Account Holder Details -->
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
                            <span class="text-[10px] text-text-muted mt-1 block">Must match the recipient's registered GCash account name.</span>
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

                    <!-- STEP 2: Pre-Submission Confirmation Message & Summary -->
                    <div x-show="withdrawConfirmStep" x-cloak class="space-y-4">
                        <div class="p-3.5 rounded-2xl bg-amber-50 border border-amber-300 text-amber-950 space-y-1">
                            <div class="font-extrabold text-xs uppercase tracking-wider text-amber-900">Please Confirm Your GCash Details</div>
                            <p class="text-[11px] leading-relaxed">
                                Please verify that the <strong>GCash Account Holder Name</strong> and <strong>11-Digit GCash Number</strong> below are accurate before proceeding. Approved funds will be transferred to this account.
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

    <!-- Settle Seller Modal -->
    <template x-teleport="body">
        <div x-show="settleModalOpen"
             x-cloak
             class="fixed inset-0 z-[195] flex items-center justify-center p-4">
            <div @click="settleModalOpen = false" class="absolute inset-0 bg-black/60 backdrop-blur-xs cursor-pointer"></div>
            <div @click.stop class="relative z-10 bg-surface rounded-3xl max-w-md w-full p-6 shadow-2xl border border-border-subtle space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-border-subtle">
                    <h3 class="text-base font-bold text-primary-dark">Settle Seller COD Merchandise Batch</h3>
                    <button type="button" @click="settleModalOpen = false" class="p-1 text-text-muted hover:text-text-main cursor-pointer">&times;</button>
                </div>

                <form action="{{ route('logistics.finance.settle-seller') }}"
                      method="POST"
                      @submit="isSubmittingSettle = true"
                      class="space-y-4 text-xs">
                    @csrf
                    <div>
                        <label class="block font-bold text-text-muted uppercase mb-1">Select Seller Store <span class="text-danger">*</span></label>
                        <select name="seller_profile_id" required class="w-full p-3 rounded-xl border border-border-subtle bg-surface font-semibold">
                            @foreach($sellers as $s)
                                <option value="{{ $s->id }}">{{ $s->business_name }} ({{ $s->municipality }}, {{ $s->province }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block font-bold text-text-muted uppercase mb-1">Settlement Notes</label>
                        <input type="text" name="notes" maxlength="255" placeholder="Optional reference or notes..." class="w-full p-3 rounded-xl border border-border-subtle bg-surface">
                    </div>
                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" @click="settleModalOpen = false" class="px-4 py-2 rounded-xl bg-surface-subtle text-text-muted font-bold cursor-pointer">Cancel</button>
                        <button type="submit" :disabled="isSubmittingSettle" class="px-5 py-2 rounded-xl bg-primary text-white font-bold cursor-pointer">Confirm Settlement</button>
                    </div>
                </form>
            </div>
        </div>
    </template>
</div>
@endsection