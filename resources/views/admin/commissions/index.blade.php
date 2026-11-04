@extends('layouts.admin', ['title' => 'Storkia Admin - Commissions, Withdrawals & Vouchers'])

@section('content')
@php
    $tab = $tab ?? request()->query('tab', 'commissions');
    $roleFilter = $roleFilter ?? request()->query('role_filter', 'all');
    $statusFilter = $statusFilter ?? request()->query('status_filter', 'all');
    $withdrawalRequests = $withdrawalRequests ?? collect();
    $gcashSubmissions = $gcashSubmissions ?? collect();
    $platformVouchers = $platformVouchers ?? collect();
    $arrearsRemittances = $arrearsRemittances ?? collect();
@endphp

<div x-data="{
    activeTab: '{{ $tab }}',
    createVoucherModalOpen: false,
    decideModalOpen: false,
    decideMode: 'approve',
    selectedWithdrawal: null,
    proofReference: '',
    decisionRemarks: '',
    refundModalOpen: false,
    selectedRefundSub: null,
    refundReference: '',
    refundRemarks: '',
    screenshotModalOpen: false,
    screenshotModalUrl: '',
    voucherCode: '',
    voucherScope: 'platform',
    discountType: 'fixed',
    sanitizeVoucherCode() {
        this.voucherCode = (this.voucherCode || '').toUpperCase().replace(/[^A-Z0-9]/g, '').slice(0, 20);
    },
    openWithdrawalDecision(wdr, mode) {
        this.selectedWithdrawal = wdr;
        this.decideMode = mode;
        this.proofReference = mode === 'approve' ? ('DISB-' + Date.now()) : '';
        this.decisionRemarks = '';
        this.decideModalOpen = true;
    },
    openGcashRefundModal(sub) {
        this.selectedRefundSub = sub;
        this.refundReference = 'REF-' + Date.now();
        this.refundRemarks = 'Prepaid GCash order refunded to buyer by Admin.';
        this.refundModalOpen = true;
    }
}" class="max-w-7xl mx-auto space-y-8 select-none">

    <div class="flex flex-col lg:flex-row lg:items-end justify-between gap-4">
        <div>
            <h1 class="text-3xl font-bold text-primary-dark">Platform Commissions, Role Withdrawals &amp; Vouchers</h1>
            <p class="text-sm text-text-muted mt-1">
                Review 10% Admin Merchandise Commissions, approve or reject E-Wallet withdrawal requests across <strong>Seller, Logistics, Rider &amp; Courier</strong> roles, verify or refund Buyer GCash Escrow receipts, and manage Platform/Free Shipping Vouchers.
            </p>
        </div>
        <div class="flex flex-wrap items-center gap-2.5">
            <a href="{{ route('admin.reports.formal-pdf') }}"
               target="_blank"
               class="px-4 py-2.5 rounded-2xl bg-primary-dark hover:bg-text-main text-white text-xs font-bold shadow-xs transition-colors">
                Formal Printable PDF Statement &nearr;
            </a>
            <button type="button"
                    @click="createVoucherModalOpen = true"
                    class="px-4 py-2.5 rounded-2xl bg-primary hover:bg-primary-dark text-white text-xs font-bold shadow-xs transition-colors cursor-pointer">
                + Create Platform / Shipping Voucher
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

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-4">
        <div class="p-4 bg-surface rounded-2xl border border-border-subtle shadow-xs">
            <span class="text-[10px] font-bold uppercase text-text-muted block">Gross Merchandise</span>
            <p class="text-xl font-serif font-bold text-text-main mt-1">₱{{ number_format($metrics['gross_merchandise_sales'] ?? 0, 2) }}</p>
            <span class="text-[10px] text-text-muted">Seller 90%: ₱{{ number_format($metrics['seller_net_90'] ?? 0, 2) }}</span>
        </div>
        <div class="p-4 bg-surface rounded-2xl border border-border-subtle shadow-xs">
            <span class="text-[10px] font-bold uppercase text-text-muted block">Gross Admin Comm (10%)</span>
            <p class="text-xl font-serif font-bold text-primary-dark mt-1">₱{{ number_format($metrics['gross_admin_commission'] ?? 0, 2) }}</p>
            <span class="text-[10px] text-danger">Subsidy: -₱{{ number_format($metrics['admin_voucher_subsidy'] ?? 0, 2) }}</span>
        </div>
        <div class="p-4 bg-gradient-to-br from-primary-dark to-primary text-white rounded-2xl shadow-sm">
            <span class="text-[10px] font-bold uppercase text-white/80 block">Net Admin Profit</span>
            <p class="text-xl font-serif font-extrabold mt-1">₱{{ number_format($metrics['net_admin_profit'] ?? 0, 2) }}</p>
            <span class="text-[10px] text-white/85">After Admin Vouchers</span>
        </div>
        <div class="p-4 bg-surface rounded-2xl border border-border-subtle shadow-xs">
            <span class="text-[10px] font-bold uppercase text-text-muted block">Gross Shipping Fees</span>
            <p class="text-xl font-serif font-bold text-text-main mt-1">₱{{ number_format($metrics['gross_shipping_fees'] ?? 0, 2) }}</p>
            <span class="text-[10px] text-text-muted">Hub 90% / Rider 10%</span>
        </div>
        <div class="p-4 bg-surface rounded-2xl border border-border-subtle shadow-xs">
            <span class="text-[10px] font-bold uppercase text-text-muted block">Pending Withdrawals</span>
            <p class="text-xl font-serif font-bold text-amber-600 mt-1">{{ $metrics['pending_withdrawals_count'] ?? 0 }}</p>
            <span class="text-[10px] text-text-muted">₱{{ number_format($metrics['pending_withdrawals_amount'] ?? 0, 2) }} queued</span>
        </div>
        <div class="p-4 bg-surface rounded-2xl border border-border-subtle shadow-xs">
            <span class="text-[10px] font-bold uppercase text-text-muted block">Disbursed Withdrawals</span>
            <p class="text-xl font-serif font-bold text-green-700 mt-1">₱{{ number_format($metrics['completed_withdrawals_amount'] ?? 0, 2) }}</p>
            <span class="text-[10px] text-text-muted">GCash Queue: {{ $metrics['pending_gcash_verifications'] ?? 0 }}</span>
        </div>
    </div>

    <div class="flex flex-wrap items-center gap-2 border-b border-border-subtle pb-3">
        <button type="button"
                @click="activeTab = 'withdrawals'"
                :class="activeTab === 'withdrawals' ? 'bg-primary-dark text-white border-primary-dark shadow-xs' : 'bg-surface text-text-muted border-border-subtle hover:text-text-main'"
                class="px-4 py-2 rounded-2xl text-xs font-bold border transition-all cursor-pointer inline-flex items-center gap-2">
            <span>Role Withdrawal Requests (Seller / Logistics / Rider / Courier)</span>
            @if(($metrics['pending_withdrawals_count'] ?? 0) > 0)
                <span class="px-2 py-0.5 rounded-full text-[10px] bg-red-500 text-white font-extrabold">{{ $metrics['pending_withdrawals_count'] }}</span>
            @endif
        </button>

        <button type="button"
                @click="activeTab = 'gcash_payments'"
                :class="activeTab === 'gcash_payments' ? 'bg-primary-dark text-white border-primary-dark shadow-xs' : 'bg-surface text-text-muted border-border-subtle hover:text-text-main'"
                class="px-4 py-2 rounded-2xl text-xs font-bold border transition-all cursor-pointer inline-flex items-center gap-2">
            <span>Buyer GCash Escrow &amp; Refunds</span>
            @if(($metrics['pending_gcash_verifications'] ?? 0) > 0)
                <span class="px-2 py-0.5 rounded-full text-[10px] bg-blue-600 text-white font-extrabold">{{ $metrics['pending_gcash_verifications'] }}</span>
            @endif
        </button>

        <button type="button"
                @click="activeTab = 'commissions'"
                :class="activeTab === 'commissions' ? 'bg-primary-dark text-white border-primary-dark shadow-xs' : 'bg-surface text-text-muted border-border-subtle hover:text-text-main'"
                class="px-4 py-2 rounded-2xl text-xs font-bold border transition-all cursor-pointer">
            <span>Commission Split Ledger</span>
        </button>

        <button type="button"
                @click="activeTab = 'vouchers'"
                :class="activeTab === 'vouchers' ? 'bg-primary-dark text-white border-primary-dark shadow-xs' : 'bg-surface text-text-muted border-border-subtle hover:text-text-main'"
                class="px-4 py-2 rounded-2xl text-xs font-bold border transition-all cursor-pointer">
            <span>Platform &amp; Shipping Vouchers ({{ $platformVouchers->count() }})</span>
        </button>

        <button type="button"
                @click="activeTab = 'arrears'"
                :class="activeTab === 'arrears' ? 'bg-primary-dark text-white border-primary-dark shadow-xs' : 'bg-surface text-text-muted border-border-subtle hover:text-text-main'"
                class="px-4 py-2 rounded-2xl text-xs font-bold border transition-all cursor-pointer">
            <span>Seller Arrears Remittances ({{ $arrearsRemittances->count() }})</span>
        </button>
    </div>

    <div x-show="activeTab === 'withdrawals'" class="bg-surface rounded-3xl border border-border-subtle shadow-xs overflow-hidden">
        <div class="p-5 border-b border-border-subtle bg-surface-subtle/50 flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-base font-bold text-primary-dark">Unified E-Wallet Withdrawal Queue (FIFO)</h2>
                <p class="text-xs text-text-muted">Approve and record payout proof references or reject to automatically refund the requester's E-Wallet.</p>
            </div>
            <form method="GET" action="{{ route('admin.commissions.index') }}" class="flex flex-wrap items-center gap-2 text-xs">
                <input type="hidden" name="tab" value="withdrawals">
                <select name="role_filter" onchange="this.form.submit()" class="px-3 py-1.5 rounded-xl border border-border-subtle bg-surface font-bold">
                    <option value="all" {{ $roleFilter === 'all' ? 'selected' : '' }}>All Roles</option>
                    <option value="Seller" {{ $roleFilter === 'Seller' ? 'selected' : '' }}>Sellers</option>
                    <option value="Logistics" {{ $roleFilter === 'Logistics' ? 'selected' : '' }}>Logistics Hubs</option>
                    <option value="Rider" {{ $roleFilter === 'Rider' ? 'selected' : '' }}>Last-Mile Riders</option>
                    <option value="Courier" {{ $roleFilter === 'Courier' ? 'selected' : '' }}>First-Mile Couriers</option>
                </select>
                <select name="status_filter" onchange="this.form.submit()" class="px-3 py-1.5 rounded-xl border border-border-subtle bg-surface font-bold">
                    <option value="all" {{ $statusFilter === 'all' ? 'selected' : '' }}>All Statuses</option>
                    <option value="pending" {{ $statusFilter === 'pending' ? 'selected' : '' }}>Pending Review</option>
                    <option value="completed" {{ $statusFilter === 'completed' ? 'selected' : '' }}>Completed / Paid</option>
                    <option value="rejected" {{ $statusFilter === 'rejected' ? 'selected' : '' }}>Rejected / Refunded</option>
                </select>
            </form>
        </div>

        @if($withdrawalRequests->isEmpty())
            <div class="p-12 text-center text-xs text-text-muted">No withdrawal requests match the selected filters.</div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-xs">
                    <thead>
                        <tr class="border-b border-border-subtle bg-surface-subtle/40 text-text-muted uppercase text-[11px] font-bold">
                            <th class="py-3.5 px-5">Reference &amp; Date</th>
                            <th class="py-3.5 px-4">Role &amp; Account</th>
                            <th class="py-3.5 px-4">Payout Destination</th>
                            <th class="py-3.5 px-4">Amount</th>
                            <th class="py-3.5 px-4">Status &amp; Audit</th>
                            <th class="py-3.5 px-5 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border-subtle">
                        @foreach($withdrawalRequests as $wdr)
                            @php
                                $roleBadge = match($wdr->requester_type) {
                                    'Seller'    => 'bg-primary/10 text-primary border-primary/20',
                                    'Logistics' => 'bg-indigo-100 text-indigo-800 border-indigo-200',
                                    'Rider'     => 'bg-emerald-100 text-emerald-800 border-emerald-200',
                                    'Courier'   => 'bg-amber-100 text-amber-800 border-amber-200',
                                    default     => 'bg-gray-100 text-gray-700 border-gray-200',
                                };
                            @endphp
                            <tr class="hover:bg-surface-subtle/30">
                                <td class="py-3.5 px-5 align-top">
                                    <div class="font-mono font-bold text-primary-dark">{{ $wdr->withdrawal_reference }}</div>
                                    <div class="text-[11px] text-text-muted">{{ $wdr->created_at?->format('M d, Y h:i A') }}</div>
                                </td>
                                <td class="py-3.5 px-4 align-top">
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-extrabold uppercase border {{ $roleBadge }}">
                                        {{ $wdr->requester_type }}
                                    </span>
                                    <div class="font-bold text-text-main mt-1">{{ $wdr->requester_name }}</div>
                                </td>
                                <td class="py-3.5 px-4 align-top">
                                    <span class="px-2 py-0.5 rounded bg-surface-subtle border border-border-subtle font-bold text-[10px]">{{ $wdr->payout_method }}</span>
                                    <div class="font-semibold text-text-main mt-0.5">{{ $wdr->account_name }}</div>
                                    <div class="font-mono text-primary-dark font-bold">{{ $wdr->account_number }}</div>
                                </td>
                                <td class="py-3.5 px-4 align-top font-serif font-extrabold text-base text-green-700">
                                    ₱{{ number_format((float) $wdr->requested_amount, 2) }}
                                </td>
                                <td class="py-3.5 px-4 align-top">
                                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase {{ $wdr->status === 'completed' ? 'bg-green-100 text-green-700' : ($wdr->status === 'rejected' ? 'bg-red-100 text-danger' : 'bg-amber-100 text-amber-800') }}">
                                        {{ $wdr->status }}
                                    </span>
                                    @if($wdr->payout_proof_reference)
                                        <div class="text-[11px] font-mono text-green-700 font-bold mt-1">Ref: {{ $wdr->payout_proof_reference }}</div>
                                    @endif
                                    @if($wdr->rejection_reason)
                                        <div class="text-[11px] text-danger mt-1">Reason: "{{ $wdr->rejection_reason }}"</div>
                                    @endif
                                </td>
                                <td class="py-3.5 px-5 align-top text-right">
                                    @if(in_array($wdr->status, ['pending', 'approved'], true))
                                        <div class="inline-flex items-center gap-2">
                                            <button type="button"
                                                    @click="openWithdrawalDecision({
                                                        id: {{ $wdr->id }},
                                                        reference: '{{ $wdr->withdrawal_reference }}',
                                                        role: '{{ $wdr->requester_type }}',
                                                        name: '{{ addslashes($wdr->requester_name) }}',
                                                        amount: '{{ number_format((float) $wdr->requested_amount, 2) }}',
                                                        method: '{{ $wdr->payout_method }}',
                                                        account_name: '{{ addslashes($wdr->account_name) }}',
                                                        account_number: '{{ addslashes($wdr->account_number) }}'
                                                    }, 'approve')"
                                                    class="px-3.5 py-1.5 rounded-xl bg-green-600 hover:bg-green-700 text-white font-bold cursor-pointer">
                                                Approve &amp; Pay
                                            </button>
                                            <button type="button"
                                                    @click="openWithdrawalDecision({
                                                        id: {{ $wdr->id }},
                                                        reference: '{{ $wdr->withdrawal_reference }}',
                                                        role: '{{ $wdr->requester_type }}',
                                                        name: '{{ addslashes($wdr->requester_name) }}',
                                                        amount: '{{ number_format((float) $wdr->requested_amount, 2) }}',
                                                        method: '{{ $wdr->payout_method }}',
                                                        account_name: '{{ addslashes($wdr->account_name) }}',
                                                        account_number: '{{ addslashes($wdr->account_number) }}'
                                                    }, 'reject')"
                                                    class="px-3.5 py-1.5 rounded-xl bg-red-50 hover:bg-red-100 text-danger border border-red-200 font-bold cursor-pointer">
                                                Reject
                                            </button>
                                        </div>
                                    @else
                                        <span class="text-[11px] text-text-muted">{{ $wdr->paid_at?->format('M d, Y') ?? $wdr->rejected_at?->format('M d, Y') }}</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if(method_exists($withdrawalRequests, 'hasPages') && $withdrawalRequests->hasPages())
                <div class="px-5 py-3 border-t border-border-subtle">
                    {{ $withdrawalRequests->links() }}
                </div>
            @endif
        @endif
    </div>

    <div x-show="activeTab === 'gcash_payments'" x-cloak class="bg-surface rounded-3xl border border-border-subtle shadow-xs overflow-hidden">
        <div class="p-5 border-b border-border-subtle bg-surface-subtle/50">
            <h2 class="text-base font-bold text-primary-dark">Buyer GCash Payment Submissions &amp; Escrow Refunds</h2>
            <p class="text-xs text-text-muted">Verify Buyer GCash reference numbers and payment screenshots, flag unverifiable receipts, or disburse refunds for cancelled/returned prepaid orders.</p>
        </div>

        @if($gcashSubmissions->isEmpty())
            <div class="p-12 text-center text-xs text-text-muted">No GCash payment submissions recorded yet.</div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-xs">
                    <thead>
                        <tr class="border-b border-border-subtle bg-surface-subtle/40 text-text-muted uppercase text-[11px] font-bold">
                            <th class="py-3.5 px-5">Seller Order #</th>
                            <th class="py-3.5 px-4">Buyer &amp; Store</th>
                            <th class="py-3.5 px-4">GCash Sender &amp; Ref #</th>
                            <th class="py-3.5 px-4">Amount</th>
                            <th class="py-3.5 px-4">Receipt</th>
                            <th class="py-3.5 px-4">Status / Refund</th>
                            <th class="py-3.5 px-5 text-right">Admin Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border-subtle">
                        @foreach($gcashSubmissions as $sub)
                            @php
                                $needsRefund = $sub->refund_status !== 'refunded' && (
                                    $sub->status === 'refund_required_seller_timeout'
                                    || $sub->refund_status === 'pending_admin_refund'
                                    || in_array($sub->sellerOrder?->status, ['cancelled', 'rejected', 'returned_to_seller'], true)
                                );
                                $refundDestNumber = $sub->sellerOrder?->extra?->refund_account_number ?: $sub->sender_gcash_number;
                                $refundDestName = $sub->sellerOrder?->extra?->refund_account_name ?: ($sub->sellerOrder?->buyer?->full_name ?? 'Buyer');
                            @endphp
                            <tr class="hover:bg-surface-subtle/30">
                                <td class="py-3.5 px-5 font-mono font-bold text-primary-dark">
                                    {{ $sub->sellerOrder->seller_order_number ?? ('#' . $sub->seller_order_id) }}
                                    <div class="text-[11px] font-normal text-text-muted">{{ $sub->paid_at?->format('M d, Y h:i A') }}</div>
                                </td>
                                <td class="py-3.5 px-4">
                                    <div class="font-bold text-text-main">{{ $sub->sellerOrder?->buyer?->full_name ?? 'Buyer' }}</div>
                                    <div class="text-[11px] text-text-muted">Store: {{ $sub->sellerProfile->business_name ?? 'Store' }}</div>
                                </td>
                                <td class="py-3.5 px-4 font-mono">
                                    <div>Sender: <strong>{{ $sub->sender_gcash_number }}</strong></div>
                                    <div class="text-blue-800 font-bold">Ref: #{{ $sub->reference_number }} ({{ $sub->reference_code }})</div>
                                </td>
                                <td class="py-3.5 px-4 font-bold text-primary">₱{{ number_format((float) $sub->submitted_amount, 2) }}</td>
                                <td class="py-3.5 px-4">
                                    @if($sub->screenshot_path)
                                        <button type="button"
                                                @click="screenshotModalUrl = '{{ asset('storage/' . ltrim($sub->screenshot_path, '/')) }}'; screenshotModalOpen = true"
                                                class="px-3 py-1.5 rounded-xl bg-blue-50 text-blue-800 border border-blue-200 font-bold cursor-pointer">
                                            View Screenshot
                                        </button>
                                    @endif
                                </td>
                                <td class="py-3.5 px-4">
                                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase {{ $sub->status === 'verified' ? 'bg-green-100 text-green-700' : ($sub->status === 'refunded' ? 'bg-purple-100 text-purple-800' : ($sub->is_suspicious ? 'bg-red-100 text-danger' : 'bg-amber-100 text-amber-800')) }}">
                                        {{ str_replace('_', ' ', $sub->status) }}
                                    </span>
                                    @if($sub->refund_status === 'refunded' && $sub->refund_reference)
                                        <div class="text-[11px] font-mono text-purple-800 font-bold mt-1">Refund Ref: {{ $sub->refund_reference }}</div>
                                    @elseif($needsRefund)
                                        <div class="text-[10px] font-extrabold text-danger uppercase mt-1">Refund Required</div>
                                    @endif
                                </td>
                                <td class="py-3.5 px-5 text-right">
                                    <div class="inline-flex flex-wrap items-center justify-end gap-1.5">
                                        @if(!in_array($sub->status, ['verified', 'refunded'], true) && !$needsRefund)
                                            <form action="{{ route('admin.commissions.gcash-payments.verify', $sub->id) }}" method="POST" class="inline">
                                                @csrf
                                                <input type="hidden" name="decision" value="verify">
                                                <button type="submit" class="px-3 py-1.5 rounded-xl bg-green-600 text-white font-bold cursor-pointer">Verify</button>
                                            </form>
                                            <form action="{{ route('admin.commissions.gcash-payments.verify', $sub->id) }}" method="POST" class="inline">
                                                @csrf
                                                <input type="hidden" name="decision" value="flag_suspicious">
                                                <input type="hidden" name="admin_remarks" value="Unverifiable GCash reference number or receipt screenshot.">
                                                <button type="submit" class="px-3 py-1.5 rounded-xl bg-red-50 text-danger border border-red-200 font-bold cursor-pointer">Flag</button>
                                            </form>
                                        @endif

                                        @if($needsRefund)
                                            <button type="button"
                                                    @click="openGcashRefundModal({
                                                        id: {{ $sub->id }},
                                                        order_no: '{{ $sub->sellerOrder->seller_order_number ?? ('#' . $sub->seller_order_id) }}',
                                                        buyer_name: '{{ addslashes($refundDestName) }}',
                                                        gcash_number: '{{ addslashes($refundDestNumber) }}',
                                                        amount: '{{ number_format((float) $sub->submitted_amount, 2) }}'
                                                    })"
                                                    class="px-3.5 py-1.5 rounded-xl bg-purple-600 hover:bg-purple-700 text-white font-bold cursor-pointer">
                                                Disburse Refund
                                            </button>
                                        @elseif($sub->status === 'verified')
                                            <span class="text-[11px] text-green-700 font-bold">Verified</span>
                                        @elseif($sub->status === 'refunded')
                                            <span class="text-[11px] text-purple-800 font-bold">Refunded</span>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    <div x-show="activeTab === 'commissions'" x-cloak class="bg-surface rounded-3xl border border-border-subtle shadow-xs overflow-hidden">
        <div class="p-5 border-b border-border-subtle bg-surface-subtle/50">
            <h2 class="text-base font-bold text-primary-dark">Completed Order Commission Split Ledger (90% Seller / 10% Admin &bull; 90% Hub / 10% Rider)</h2>
        </div>

        @if($commissionRecords->isEmpty())
            <div class="p-12 text-center text-xs text-text-muted">No commission records generated yet.</div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-xs">
                    <thead>
                        <tr class="border-b border-border-subtle bg-surface-subtle/40 text-text-muted uppercase text-[11px] font-bold">
                            <th class="py-3.5 px-5">Order #</th>
                            <th class="py-3.5 px-4">Seller Store</th>
                            <th class="py-3.5 px-4">Product Base</th>
                            <th class="py-3.5 px-4">Admin 10%</th>
                            <th class="py-3.5 px-4">Seller 90%</th>
                            <th class="py-3.5 px-4">Shipping (90% Hub / 10% Rider)</th>
                            <th class="py-3.5 px-5 text-right">Date</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border-subtle">
                        @foreach($commissionRecords as $rec)
                            <tr class="hover:bg-surface-subtle/30">
                                <td class="py-3.5 px-5 font-mono font-bold text-primary-dark">
                                    {{ $rec->sellerOrder->seller_order_number ?? ('#' . $rec->seller_order_id) }}
                                </td>
                                <td class="py-3.5 px-4 font-semibold text-text-main">{{ $rec->sellerProfile->business_name ?? 'Store' }}</td>
                                <td class="py-3.5 px-4">₱{{ number_format((float) $rec->product_subtotal, 2) }}</td>
                                <td class="py-3.5 px-4 font-extrabold text-primary">₱{{ number_format((float) $rec->admin_commission_amount, 2) }}</td>
                                <td class="py-3.5 px-4 font-bold text-green-700">₱{{ number_format((float) $rec->seller_net_amount, 2) }}</td>
                                <td class="py-3.5 px-4">
                                    <div>Ship: ₱{{ number_format((float) $rec->shipping_fee, 2) }}</div>
                                    <div class="text-[10px] text-text-muted">
                                        Hub: ₱{{ number_format((float) $rec->logistics_commission_amount + (float) $rec->logistics_operational_amount, 2) }} &bull;
                                        Rider: ₱{{ number_format((float) $rec->rider_commission_amount, 2) }}
                                    </div>
                                </td>
                                <td class="py-3.5 px-5 text-right text-text-muted">{{ $rec->created_at?->format('M d, Y h:i A') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if(method_exists($commissionRecords, 'hasPages') && $commissionRecords->hasPages())
                <div class="px-5 py-3 border-t border-border-subtle">
                    {{ $commissionRecords->links() }}
                </div>
            @endif
        @endif
    </div>

    <div x-show="activeTab === 'vouchers'" x-cloak class="space-y-4">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            @foreach($platformVouchers as $pv)
                <div class="p-5 rounded-3xl bg-surface border border-border-subtle shadow-xs flex items-center justify-between gap-4 text-xs">
                    <div class="space-y-1">
                        <div class="flex items-center gap-2">
                            <span class="font-mono font-extrabold text-sm px-2.5 py-0.5 rounded bg-brand-light/60 text-primary-dark">{{ $pv->code }}</span>
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase {{ $pv->scope === 'shipping' ? 'bg-emerald-100 text-emerald-800' : 'bg-purple-100 text-purple-800' }}">
                                {{ $pv->scope }}
                            </span>
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase {{ $pv->status === 'active' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' }}">
                                {{ $pv->status }}
                            </span>
                        </div>
                        <h4 class="font-bold text-sm text-text-main">{{ $pv->title }}</h4>
                        <p class="text-text-muted">{{ $pv->badge_label }} &bull; Min Spend ₱{{ number_format((float) $pv->min_spend, 2) }} &bull; Used {{ $pv->used_count }}/{{ $pv->total_usage_limit }}</p>
                    </div>
                    @if($pv->status === 'active')
                        <form action="{{ route('admin.commissions.vouchers.end', $pv->id) }}" method="POST">
                            @csrf
                            <button type="submit" class="px-3.5 py-1.5 rounded-xl bg-red-50 text-danger border border-red-200 font-bold cursor-pointer">
                                End Now
                            </button>
                        </form>
                    @endif
                </div>
            @endforeach
        </div>
    </div>

    <div x-show="activeTab === 'arrears'" x-cloak class="bg-surface rounded-3xl border border-border-subtle shadow-xs overflow-hidden">
        <div class="p-5 border-b border-border-subtle bg-surface-subtle/50">
            <h2 class="text-base font-bold text-primary-dark">Seller Direct-GCash Arrears Remittances</h2>
        </div>

        @if($arrearsRemittances->isEmpty())
            <div class="p-10 text-center text-xs text-text-muted">No Seller arrears remittances submitted.</div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-xs">
                    <thead>
                        <tr class="border-b border-border-subtle bg-surface-subtle/40 text-text-muted uppercase text-[11px] font-bold">
                            <th class="py-3.5 px-5">Reference #</th>
                            <th class="py-3.5 px-4">Seller Store</th>
                            <th class="py-3.5 px-4">Amount Remitted</th>
                            <th class="py-3.5 px-4">GCash Ref #</th>
                            <th class="py-3.5 px-4">Status</th>
                            <th class="py-3.5 px-5 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border-subtle">
                        @foreach($arrearsRemittances as $ar)
                            <tr>
                                <td class="py-3.5 px-5 font-mono font-bold text-primary-dark">{{ $ar->remittance_reference }}</td>
                                <td class="py-3.5 px-4 font-semibold">{{ $ar->sellerProfile->business_name ?? ('Seller #' . $ar->seller_profile_id) }}</td>
                                <td class="py-3.5 px-4 font-bold text-green-700">₱{{ number_format((float) $ar->amount_remitted, 2) }}</td>
                                <td class="py-3.5 px-4 font-mono">{{ $ar->gcash_reference_number }} ({{ $ar->sender_gcash_number }})</td>
                                <td class="py-3.5 px-4 uppercase font-bold">{{ $ar->status }}</td>
                                <td class="py-3.5 px-5 text-right">
                                    @if($ar->status === 'pending')
                                        <form action="{{ route('admin.commissions.arrears.verify', $ar->id) }}" method="POST" class="inline">
                                            @csrf
                                            <input type="hidden" name="decision" value="approve">
                                            <button type="submit" class="px-3 py-1.5 rounded-xl bg-green-600 text-white font-bold cursor-pointer">Approve</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    <template x-teleport="body">
        <div x-show="decideModalOpen && selectedWithdrawal"
             x-cloak
             class="fixed inset-0 z-[200] flex items-center justify-center p-4">
            <div @click="decideModalOpen = false" class="absolute inset-0 bg-black/60 backdrop-blur-xs cursor-pointer"></div>
            <div @click.stop class="relative z-10 bg-surface rounded-3xl max-w-md w-full p-6 shadow-2xl border border-border-subtle space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-border-subtle">
                    <h3 class="text-base font-bold"
                        :class="decideMode === 'approve' ? 'text-green-700' : 'text-danger'"
                        x-text="decideMode === 'approve' ? 'Approve & Disburse Withdrawal' : 'Reject Withdrawal & Refund E-Wallet'"></h3>
                    <button type="button" @click="decideModalOpen = false" class="p-1 text-text-muted hover:text-text-main cursor-pointer">&times;</button>
                </div>

                <div class="p-4 rounded-2xl bg-surface-subtle border border-border-subtle space-y-1.5 text-xs">
                    <div class="flex justify-between"><span class="text-text-muted">Reference:</span> <strong class="font-mono" x-text="selectedWithdrawal?.reference"></strong></div>
                    <div class="flex justify-between"><span class="text-text-muted">Requester:</span> <strong x-text="selectedWithdrawal?.role + ' • ' + selectedWithdrawal?.name"></strong></div>
                    <div class="flex justify-between"><span class="text-text-muted">Amount:</span> <strong class="text-green-700" x-text="'₱' + selectedWithdrawal?.amount"></strong></div>
                    <div class="flex justify-between"><span class="text-text-muted">Destination:</span> <strong class="font-mono" x-text="selectedWithdrawal?.method + ' • ' + selectedWithdrawal?.account_name + ' (' + selectedWithdrawal?.account_number + ')'"></strong></div>
                </div>

                <form :action="`{{ url('/admin/commissions/withdrawals') }}/${selectedWithdrawal?.id}/decide`" method="POST" class="space-y-4 text-xs">
                    @csrf
                    <input type="hidden" name="decision" :value="decideMode">

                    <div x-show="decideMode === 'approve'">
                        <label class="block font-bold text-text-muted uppercase mb-1">Disbursement Proof Reference # <span class="text-danger">*</span></label>
                        <input type="text" name="payout_proof_reference" x-model="proofReference" :required="decideMode === 'approve'" maxlength="100" class="w-full p-3 rounded-xl border border-border-subtle font-mono font-bold">
                    </div>

                    <div>
                        <label class="block font-bold text-text-muted uppercase mb-1"
                               x-text="decideMode === 'approve' ? 'Admin Remarks (Optional)' : 'Rejection Reason (Required — Refunds Wallet) *'"></label>
                        <textarea name="remarks" x-model="decisionRemarks" :required="decideMode === 'reject'" rows="3" placeholder="Provide audit remarks..." class="w-full p-3 rounded-xl border border-border-subtle"></textarea>
                    </div>

                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" @click="decideModalOpen = false" class="px-4 py-2 rounded-xl bg-surface-subtle text-text-muted font-bold cursor-pointer">Cancel</button>
                        <button type="submit"
                                :class="decideMode === 'approve' ? 'bg-green-600 hover:bg-green-700' : 'bg-danger hover:opacity-90'"
                                class="px-5 py-2 rounded-xl text-white font-bold cursor-pointer">
                            <span x-text="decideMode === 'approve' ? 'Confirm Disbursement' : 'Confirm Rejection & Refund'"></span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </template>

    <template x-teleport="body">
        <div x-show="refundModalOpen && selectedRefundSub"
             x-cloak
             class="fixed inset-0 z-[200] flex items-center justify-center p-4">
            <div @click="refundModalOpen = false" class="absolute inset-0 bg-black/60 backdrop-blur-xs cursor-pointer"></div>
            <div @click.stop class="relative z-10 bg-surface rounded-3xl max-w-md w-full p-6 shadow-2xl border border-border-subtle space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-border-subtle">
                    <h3 class="text-base font-bold text-purple-800">Disburse Buyer GCash Escrow Refund</h3>
                    <button type="button" @click="refundModalOpen = false" class="p-1 text-text-muted hover:text-text-main cursor-pointer">&times;</button>
                </div>

                <div class="p-4 rounded-2xl bg-surface-subtle border border-border-subtle space-y-1.5 text-xs">
                    <div class="flex justify-between"><span class="text-text-muted">Order Number:</span> <strong class="font-mono" x-text="selectedRefundSub?.order_no"></strong></div>
                    <div class="flex justify-between"><span class="text-text-muted">Buyer Recipient:</span> <strong x-text="selectedRefundSub?.buyer_name"></strong></div>
                    <div class="flex justify-between"><span class="text-text-muted">Refund GCash #:</span> <strong class="font-mono text-primary-dark" x-text="selectedRefundSub?.gcash_number"></strong></div>
                    <div class="flex justify-between"><span class="text-text-muted">Refund Amount:</span> <strong class="text-purple-800" x-text="'₱' + selectedRefundSub?.amount"></strong></div>
                </div>

                <form :action="`{{ url('/admin/commissions/gcash-payments') }}/${selectedRefundSub?.id}/refund`" method="POST" class="space-y-4 text-xs">
                    @csrf
                    <div>
                        <label class="block font-bold text-text-muted uppercase mb-1">GCash Refund Reference # <span class="text-danger">*</span></label>
                        <input type="text" name="refund_reference" x-model="refundReference" required minlength="4" maxlength="80" class="w-full p-3 rounded-xl border border-border-subtle font-mono font-bold">
                    </div>

                    <div>
                        <label class="block font-bold text-text-muted uppercase mb-1">Admin Refund Remarks</label>
                        <textarea name="admin_remarks" x-model="refundRemarks" rows="2" class="w-full p-3 rounded-xl border border-border-subtle"></textarea>
                    </div>

                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" @click="refundModalOpen = false" class="px-4 py-2 rounded-xl bg-surface-subtle text-text-muted font-bold cursor-pointer">Cancel</button>
                        <button type="submit" class="px-5 py-2 rounded-xl bg-purple-600 hover:bg-purple-700 text-white font-bold cursor-pointer">
                            Confirm GCash Refund Disbursed
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </template>

    <template x-teleport="body">
        <div x-show="createVoucherModalOpen"
             x-cloak
             class="fixed inset-0 z-[200] flex items-center justify-center p-4">
            <div @click="createVoucherModalOpen = false" class="absolute inset-0 bg-black/60 backdrop-blur-xs cursor-pointer"></div>
            <div @click.stop class="relative z-10 bg-surface rounded-3xl max-w-lg w-full p-6 shadow-2xl border border-border-subtle space-y-4 max-h-[90vh] overflow-y-auto">
                <div class="flex items-center justify-between pb-3 border-b border-border-subtle">
                    <h3 class="text-base font-bold text-primary-dark">Create Admin Platform / Shipping Voucher</h3>
                    <button type="button" @click="createVoucherModalOpen = false" class="p-1 text-text-muted hover:text-text-main cursor-pointer">&times;</button>
                </div>

                <form action="{{ route('admin.commissions.vouchers.store') }}" method="POST" class="space-y-4 text-xs">
                    @csrf
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block font-bold text-text-muted uppercase mb-1">Voucher Code <span class="text-danger">*</span></label>
                            <input type="text" name="code" x-model="voucherCode" @input="sanitizeVoucherCode()" minlength="4" maxlength="20" required placeholder="e.g. PLAT100" class="w-full p-3 rounded-xl border border-border-subtle font-mono uppercase font-bold">
                        </div>
                        <div>
                            <label class="block font-bold text-text-muted uppercase mb-1">Voucher Scope <span class="text-danger">*</span></label>
                            <select name="scope" x-model="voucherScope" class="w-full p-3 rounded-xl border border-border-subtle font-bold">
                                <option value="platform">Platform Merchandise Voucher</option>
                                <option value="shipping">Free Shipping Voucher</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="block font-bold text-text-muted uppercase mb-1">Title <span class="text-danger">*</span></label>
                        <input type="text" name="title" required maxlength="120" placeholder="e.g. Storkia Payday ₱100 Off" class="w-full p-3 rounded-xl border border-border-subtle">
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <div>
                            <label class="block font-bold text-text-muted uppercase mb-1">Discount Type</label>
                            <select name="discount_type" x-model="discountType" class="w-full p-3 rounded-xl border border-border-subtle font-bold">
                                <option value="fixed">Fixed (₱)</option>
                                <option value="percentage">Percentage (%)</option>
                            </select>
                        </div>
                        <div>
                            <label class="block font-bold text-text-muted uppercase mb-1">Discount Value <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" min="1" name="discount_value" value="50" required class="w-full p-3 rounded-xl border border-border-subtle font-bold">
                        </div>
                        <div>
                            <label class="block font-bold text-text-muted uppercase mb-1">Min Spend (₱) <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" min="0" name="min_spend" value="300" required class="w-full p-3 rounded-xl border border-border-subtle font-bold">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <div>
                            <label class="block font-bold text-text-muted uppercase mb-1">Total Limit</label>
                            <input type="number" min="1" name="total_usage_limit" value="500" required class="w-full p-3 rounded-xl border border-border-subtle">
                        </div>
                        <div>
                            <label class="block font-bold text-text-muted uppercase mb-1">Per Buyer</label>
                            <input type="number" min="1" name="per_buyer_limit" value="2" required class="w-full p-3 rounded-xl border border-border-subtle">
                        </div>
                        <div>
                            <label class="block font-bold text-text-muted uppercase mb-1">Visibility</label>
                            <select name="visibility" class="w-full p-3 rounded-xl border border-border-subtle font-bold">
                                <option value="claimable">Claimable</option>
                                <option value="code_only">Secret Code</option>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block font-bold text-text-muted uppercase mb-1">Starts At</label>
                            <input type="datetime-local" name="starts_at" value="{{ now()->format('Y-m-d\TH:i') }}" required class="w-full p-3 rounded-xl border border-border-subtle">
                        </div>
                        <div>
                            <label class="block font-bold text-text-muted uppercase mb-1">Ends At</label>
                            <input type="datetime-local" name="ends_at" value="{{ now()->addMonths(2)->format('Y-m-d\TH:i') }}" required class="w-full p-3 rounded-xl border border-border-subtle">
                        </div>
                    </div>

                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" @click="createVoucherModalOpen = false" class="px-4 py-2 rounded-xl bg-surface-subtle text-text-muted font-bold cursor-pointer">Cancel</button>
                        <button type="submit" class="px-5 py-2 rounded-xl bg-primary text-white font-bold cursor-pointer">Publish Voucher</button>
                    </div>
                </form>
            </div>
        </div>
    </template>

    <template x-teleport="body">
        <div x-show="screenshotModalOpen"
             x-cloak
             @click="screenshotModalOpen = false"
             class="fixed inset-0 z-[210] flex items-center justify-center p-4 bg-black/75 backdrop-blur-xs">
            <img :src="screenshotModalUrl" alt="GCash Receipt" class="max-w-full max-h-[88vh] rounded-2xl shadow-2xl border border-white/20" @click.stop>
        </div>
    </template>

</div>
@endsection