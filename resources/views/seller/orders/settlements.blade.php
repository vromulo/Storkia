@extends('layouts.seller', ['title' => 'Storkia - Seller E-Wallet, Payouts & Financial Report'])

@section('content')
@php
    $payoutRequests = $payoutRequests ?? collect();
    $hasPendingPayout = $payoutRequests->whereIn('status', ['pending', 'approved'])->isNotEmpty();
    $availableBalance = (float) ($metrics['wallet_available'] ?? $metrics['settled_total'] ?? 0);
    $arrearsAmount = (float) ($metrics['wallet_arrears'] ?? ($availableBalance < 0 ? abs($availableBalance) : 0));
    $netWithdrawableAfterArrears = max(0.0, round($availableBalance - $arrearsAmount, 2));
    $canWithdraw = $netWithdrawableAfterArrears >= 100 && !$hasPendingPayout;

    $settlementsConfig = [
        'canWithdraw'     => (bool) $canWithdraw,
        'maxWithdrawable' => round($netWithdrawableAfterArrears, 2),
        'requestedAmount' => max(100, floor($netWithdrawableAfterArrears * 100) / 100),
        'accountName'     => (string) (auth()->user()?->full_name ?? ''),
    ];
@endphp

<script>
    window.storkiaSellerSettlementsState = function (cfg) {
        return {
            payoutModalOpen: false,
            payoutConfirmStep: false,
            payoutError: '',
            arrearsModalOpen: false,
            payoutMethod: 'GCASH',
            canWithdraw: Boolean(cfg.canWithdraw),
            maxWithdrawable: Number(cfg.maxWithdrawable || 0),
            requestedAmount: Number(cfg.requestedAmount || 100),
            accountName: cfg.accountName || '',
            accountNumber: '',
            isSubmittingPayout: false,
            isSubmittingArrears: false,

            openPayoutModal() {
                if (!this.canWithdraw) return;
                this.payoutError = '';
                this.payoutConfirmStep = false;
                this.isSubmittingPayout = false;
                this.payoutModalOpen = true;
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

            reviewPayoutRequest() {
                this.payoutError = '';
                this.sanitizeAccountNumber();
                const amt = parseFloat(this.requestedAmount);
                if (isNaN(amt) || amt < 100) {
                    this.payoutError = 'Minimum withdrawal amount is \u20B1100.00.';
                    return;
                }
                if (amt > this.maxWithdrawable + 0.001) {
                    this.payoutError = 'Requested amount cannot exceed your net withdrawable balance of \u20B1' + this.maxWithdrawable.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + '.';
                    return;
                }
                if (!this.accountName || this.accountName.trim().length < 2) {
                    this.payoutError = 'Please enter the GCash Account Holder Name (at least 2 characters).';
                    return;
                }
                if (!/^09\d{9}$/.test(this.accountNumber)) {
                    this.payoutError = 'Please enter a valid 11-digit GCash mobile number starting with 09 (e.g., 09171234567).';
                    return;
                }
                this.payoutConfirmStep = true;
            },

            confirmAndSubmitPayout() {
                if (this.isSubmittingPayout) return;
                this.isSubmittingPayout = true;
                this.$refs.sellerPayoutForm.submit();
            }
        };
    };
</script>

<div x-data="storkiaSellerSettlementsState(@js($settlementsConfig))" class="max-w-7xl mx-auto space-y-8 select-none">

    <div class="flex flex-col lg:flex-row lg:items-end justify-between gap-4">
        <div>
            <h1 class="text-3xl font-bold text-primary-dark">Seller E-Wallet, Payouts &amp; Financial Report</h1>
            <p class="text-sm text-text-muted mt-1">
                Split-Base Policy: You earn <strong>90%</strong> of your Commissionable Product Subtotal (10% Admin Commission). Approved withdrawals are disbursed by Storkia Admin to your designated GCash account.
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-2.5">
            <a href="{{ route('seller.reports.formal-pdf', ['from_date' => $fromDate ?? now()->startOfMonth()->toDateString(), 'to_date' => $toDate ?? now()->toDateString(), 'preset' => 'custom']) }}"
               target="_blank"
               class="px-4 py-2.5 rounded-2xl bg-primary-dark hover:bg-text-main text-white text-xs font-bold shadow-xs transition-colors">
                Open Formal Printable PDF Statement
            </a>

            <button type="button"
                    @click="openPayoutModal()"
                    :disabled="!canWithdraw"
                    class="px-4 py-2.5 rounded-2xl text-xs font-bold shadow-xs transition-colors {{ $canWithdraw ? 'bg-green-600 hover:bg-green-700 text-white cursor-pointer' : 'bg-gray-200 text-gray-500 cursor-not-allowed' }}">
                {{ $hasPendingPayout ? 'Withdrawal Request Pending Admin Review' : 'Request Withdrawal (Min &#8369;100)' }}
            </button>

            @if($arrearsAmount > 0)
                @if($availableBalance > 0)
                    <form action="{{ route('seller.arrears.wallet-offset') }}" method="POST" class="m-0">
                        @csrf
                        <button type="submit"
                                class="px-4 py-2.5 rounded-2xl bg-amber-600 hover:bg-amber-700 text-white text-xs font-bold shadow-xs cursor-pointer">
                            Offset Arrears from E-Wallet (&#8369;{{ number_format(min($availableBalance, $arrearsAmount), 2) }})
                        </button>
                    </form>
                @endif

                <button type="button"
                        @click="arrearsModalOpen = true"
                        class="px-4 py-2.5 rounded-2xl bg-danger text-white text-xs font-bold shadow-xs cursor-pointer">
                    Remit Arrears via GCash (&#8369;{{ number_format($arrearsAmount, 2) }})
                </button>
            @endif
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

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
        <div class="p-5 bg-gradient-to-br from-primary-dark to-primary text-white rounded-2xl shadow-sm">
            <span class="text-[11px] font-bold text-white/80 uppercase">Available Real-Money E-Wallet</span>
            <p class="text-2xl font-serif font-extrabold mt-2">&#8369;{{ number_format($availableBalance, 2) }}</p>
            <span class="text-[10px] text-white/85 mt-1 block">
                @if($arrearsAmount > 0)
                    Arrears: &#8369;{{ number_format($arrearsAmount, 2) }} &bull; Net Withdrawable: &#8369;{{ number_format($netWithdrawableAfterArrears, 2) }}
                @else
                    Withdrawable when &ge; &#8369;100.00
                @endif
            </span>
        </div>
        <div class="p-5 bg-surface rounded-2xl border border-border-subtle shadow-xs">
            <span class="text-[11px] font-bold text-text-muted uppercase">Pending Escrow Balance</span>
            <p class="text-2xl font-serif font-bold text-amber-600 mt-2">&#8369;{{ number_format($metrics['wallet_pending_escrow'] ?? $metrics['pending_settlement'] ?? 0, 2) }}</p>
            <span class="text-[10px] text-text-muted mt-1 block">Delivered &bull; Awaiting Buyer Confirm</span>
        </div>
        <div class="p-5 bg-surface rounded-2xl border border-border-subtle shadow-xs">
            <span class="text-[11px] font-bold text-text-muted uppercase">Gross Product Subtotal</span>
            <p class="text-2xl font-serif font-bold text-text-main mt-2">&#8369;{{ number_format($metrics['gross_product_sales'] ?? 0, 2) }}</p>
        </div>
        <div class="p-5 bg-surface rounded-2xl border border-border-subtle shadow-xs">
            <span class="text-[11px] font-bold text-text-muted uppercase">Admin Commission (10%)</span>
            <p class="text-2xl font-serif font-bold text-danger mt-2">-&#8369;{{ number_format($metrics['admin_commission_total'] ?? 0, 2) }}</p>
        </div>
        <div class="p-5 bg-surface rounded-2xl border border-border-subtle shadow-xs">
            <span class="text-[11px] font-bold text-text-muted uppercase">Total Net Earnings (90%)</span>
            <p class="text-2xl font-serif font-bold text-green-700 mt-2">&#8369;{{ number_format($metrics['net_seller_earnings'] ?? 0, 2) }}</p>
        </div>
    </div>

    <div class="bg-surface rounded-3xl border border-border-subtle shadow-xs overflow-hidden">
        <div class="p-5 border-b border-border-subtle bg-surface-subtle/50 flex flex-wrap items-center justify-between gap-2">
            <div>
                <h2 class="text-base font-bold text-primary-dark">E-Wallet Withdrawal Requests &amp; Status History</h2>
                <p class="text-xs text-text-muted">Track your submitted payout requests, Admin approval references, or rejection reasons.</p>
            </div>
        </div>
        @if($payoutRequests->isEmpty())
            <div class="p-8 text-center text-xs text-text-muted">
                No withdrawal requests submitted yet. Once your net Available E-Wallet Balance reaches &#8369;100.00, click "Request Withdrawal" above.
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-xs">
                    <thead>
                        <tr class="border-b border-border-subtle bg-surface-subtle/40 text-text-muted uppercase text-[11px] font-bold">
                            <th class="py-3 px-5">Withdrawal Ref</th>
                            <th class="py-3 px-4">Payout Destination</th>
                            <th class="py-3 px-4">Requested Amount</th>
                            <th class="py-3 px-4">Status</th>
                            <th class="py-3 px-4">Admin Remarks / Proof Ref</th>
                            <th class="py-3 px-5 text-right">Requested At</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border-subtle">
                        @foreach($payoutRequests as $pr)
                            @php
                                $refCode = $pr->withdrawal_reference ?? $pr->payout_reference ?? ('WDR-' . $pr->id);
                                $statusBadge = match($pr->status) {
                                    'completed' => 'bg-green-100 text-green-700 border-green-200',
                                    'rejected'  => 'bg-red-100 text-danger border-red-200',
                                    default     => 'bg-amber-100 text-amber-800 border-amber-300',
                                };
                            @endphp
                            <tr class="hover:bg-surface-subtle/30">
                                <td class="py-3.5 px-5 font-mono font-bold text-primary-dark">{{ $refCode }}</td>
                                <td class="py-3.5 px-4">
                                    <span class="px-2 py-0.5 rounded bg-brand-light/40 text-primary-dark font-bold text-[10px] mr-1">{{ $pr->payout_method }}</span>
                                    <span class="font-semibold text-text-main">{{ $pr->account_name }}</span>
                                    <span class="font-mono text-text-muted ml-1">({{ $pr->account_number }})</span>
                                </td>
                                <td class="py-3.5 px-4 font-bold text-green-700">&#8369;{{ number_format((float) $pr->requested_amount, 2) }}</td>
                                <td class="py-3.5 px-4">
                                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase border {{ $statusBadge }}">
                                        {{ $pr->status }}
                                    </span>
                                </td>
                                <td class="py-3.5 px-4 text-text-muted max-w-xs">
                                    @if(!empty($pr->payout_proof_reference))
                                        <div class="font-mono font-bold text-green-700">Ref: {{ $pr->payout_proof_reference }}</div>
                                    @endif
                                    @if(!empty($pr->rejection_reason))
                                        <div class="text-danger font-semibold">Rejected: "{{ $pr->rejection_reason }}" (Refunded to Wallet)</div>
                                    @elseif(!empty($pr->admin_remarks))
                                        <div>{{ $pr->admin_remarks }}</div>
                                    @else
                                        <span class="italic">Awaiting Admin verification &amp; disbursement</span>
                                    @endif
                                </td>
                                <td class="py-3.5 px-5 text-right text-text-muted">{{ \Carbon\Carbon::parse($pr->created_at)->format('M d, Y h:i A') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    <div class="bg-surface rounded-3xl border border-border-subtle shadow-xs overflow-hidden">
        <div class="p-5 border-b border-border-subtle bg-surface-subtle/50 flex flex-wrap items-center justify-between gap-3">
            <h2 class="text-base font-bold text-primary-dark">Per-Order Commission &amp; E-Wallet Earnings Ledger (FIFO)</h2>
            <form method="GET" action="{{ route('seller.settlements.index') }}" class="flex items-center gap-2 text-xs">
                <input type="date" name="from_date" value="{{ $fromDate ?? now()->startOfMonth()->toDateString() }}" class="px-2.5 py-1.5 rounded-xl border border-border-subtle bg-surface">
                <span>to</span>
                <input type="date" name="to_date" value="{{ $toDate ?? now()->toDateString() }}" class="px-2.5 py-1.5 rounded-xl border border-border-subtle bg-surface">
                <button type="submit" class="px-3.5 py-1.5 rounded-xl bg-primary-dark text-white font-bold cursor-pointer">Filter</button>
            </form>
        </div>

        @if($commissionRecords->isEmpty())
            <div class="p-10 text-center text-xs text-text-muted">
                Commission records are generated automatically when orders are delivered and completed.
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-xs">
                    <thead>
                        <tr class="border-b border-border-subtle bg-surface-subtle/40 text-text-muted uppercase text-[11px] font-bold">
                            <th class="py-3.5 px-5">Seller Order #</th>
                            <th class="py-3.5 px-4">Commissionable Subtotal</th>
                            <th class="py-3.5 px-4">Admin 10%</th>
                            <th class="py-3.5 px-4">Your Net Profit (90%)</th>
                            <th class="py-3.5 px-4">E-Wallet Status</th>
                            <th class="py-3.5 px-5 text-right">Recorded At</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border-subtle">
                        @foreach($commissionRecords as $rec)
                            @php
                                $sellerNetProfit = (float) ($rec->seller_net_earning ?? $rec->seller_net_amount ?? 0);
                            @endphp
                            <tr class="hover:bg-surface-subtle/30">
                                <td class="py-3.5 px-5 font-mono font-bold text-primary-dark">
                                    <a href="{{ route('seller.orders.show', $rec->seller_order_id) }}" class="hover:underline">
                                        {{ $rec->sellerOrder->seller_order_number ?? '#' . $rec->seller_order_id }}
                                    </a>
                                </td>
                                <td class="py-3.5 px-4">&#8369;{{ number_format((float) $rec->product_subtotal, 2) }}</td>
                                <td class="py-3.5 px-4 text-danger font-semibold">-&#8369;{{ number_format((float) $rec->admin_commission_amount, 2) }}</td>
                                <td class="py-3.5 px-4 font-extrabold text-green-700">&#8369;{{ number_format($sellerNetProfit, 2) }}</td>
                                <td class="py-3.5 px-4">
                                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase {{ $rec->status === 'settled' ? 'bg-green-100 text-green-700' : 'bg-blue-100 text-blue-700' }}">
                                        Credited to E-Wallet ({{ $rec->status }})
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

    <template x-teleport="body">
        <div x-show="payoutModalOpen"
             x-cloak
             class="fixed inset-0 z-[195] flex items-center justify-center p-4">
            <div @click="if (!isSubmittingPayout) { payoutModalOpen = false; payoutConfirmStep = false; }" class="absolute inset-0 bg-black/60 backdrop-blur-xs cursor-pointer"></div>
            <div @click.stop class="relative z-10 bg-surface rounded-3xl max-w-md w-full p-6 shadow-2xl border border-border-subtle space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-border-subtle">
                    <div>
                        <h3 class="text-base font-bold text-primary-dark" x-text="payoutConfirmStep ? 'Confirm Withdrawal Details' : 'Request E-Wallet Withdrawal'"></h3>
                        <p class="text-xs text-text-muted">Net Withdrawable: <strong class="text-green-700">&#8369;{{ number_format($netWithdrawableAfterArrears, 2) }}</strong></p>
                    </div>
                    <button type="button"
                            @click="if (!isSubmittingPayout) { payoutModalOpen = false; payoutConfirmStep = false; }"
                            class="p-1 text-text-muted hover:text-text-main cursor-pointer">&times;</button>
                </div>

                @if($arrearsAmount > 0)
                    <div class="p-3 rounded-2xl bg-amber-50 border border-amber-300 text-amber-950 text-[11px] font-semibold">
                        Note: Your outstanding arrears of &#8369;{{ number_format($arrearsAmount, 2) }} will be automatically offset from your Available E-Wallet balance upon submitting this withdrawal.
                    </div>
                @endif

                <form x-ref="sellerPayoutForm"
                      action="{{ route('seller.payouts.store') }}"
                      method="POST"
                      @submit.prevent="if (!payoutConfirmStep) { reviewPayoutRequest(); } else { confirmAndSubmitPayout(); }"
                      class="space-y-4 text-xs">
                    @csrf
                    <input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                    <input type="hidden" name="payout_method" value="GCASH">

                    <div x-show="!payoutConfirmStep" class="space-y-4">
                        <div>
                            <label class="block font-bold text-text-muted uppercase mb-1">Withdrawal Amount (&#8369;) <span class="text-danger">*</span></label>
                            <input type="number"
                                   step="0.01"
                                   min="100"
                                   max="{{ number_format($netWithdrawableAfterArrears, 2, '.', '') }}"
                                   name="requested_amount"
                                   x-model="requestedAmount"
                                   required
                                   class="w-full p-3 rounded-xl border border-border-subtle bg-surface font-bold text-sm text-primary-dark outline-none focus:border-primary">
                            <span class="text-[11px] text-text-muted mt-1 block">Minimum &#8369;100.00 &bull; Max &#8369;{{ number_format($netWithdrawableAfterArrears, 2) }}</span>
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

                        <template x-if="payoutError">
                            <div class="p-3 rounded-xl bg-red-50 border border-red-200 text-danger font-bold text-xs" x-text="payoutError"></div>
                        </template>

                        <div class="flex justify-end gap-2 pt-2 border-t border-border-subtle">
                            <button type="button"
                                    @click="payoutModalOpen = false; payoutConfirmStep = false;"
                                    class="px-4 py-2.5 rounded-xl bg-surface-subtle text-text-muted font-bold cursor-pointer">
                                Cancel
                            </button>
                            <button type="button"
                                    @click="reviewPayoutRequest()"
                                    class="px-5 py-2.5 rounded-xl bg-green-600 hover:bg-green-700 text-white font-bold shadow-xs cursor-pointer">
                                Review Withdrawal Request &rarr;
                            </button>
                        </div>
                    </div>

                    <div x-show="payoutConfirmStep" x-cloak class="space-y-4">
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
                                    @click="payoutConfirmStep = false"
                                    :disabled="isSubmittingPayout"
                                    class="px-4 py-2.5 rounded-xl bg-surface-subtle text-text-muted hover:text-text-main font-bold cursor-pointer">
                                &larr; Back to Edit
                            </button>
                            <button type="submit"
                                    :disabled="isSubmittingPayout"
                                    class="px-5 py-2.5 rounded-xl bg-green-600 hover:bg-green-700 text-white font-bold shadow-xs cursor-pointer disabled:opacity-50">
                                <span x-show="!isSubmittingPayout">Confirm &amp; Submit Request</span>
                                <span x-show="isSubmittingPayout" x-cloak>Submitting...</span>
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </template>

    @if($arrearsAmount > 0)
        <template x-teleport="body">
            <div x-show="arrearsModalOpen"
                 x-cloak
                 class="fixed inset-0 z-[195] flex items-center justify-center p-4">
                <div @click="arrearsModalOpen = false" class="absolute inset-0 bg-black/60 backdrop-blur-xs cursor-pointer"></div>
                <div @click.stop class="relative z-10 bg-surface rounded-3xl max-w-md w-full p-6 shadow-2xl border border-border-subtle space-y-4">
                    <div class="flex items-center justify-between pb-3 border-b border-border-subtle">
                        <div>
                            <h3 class="text-base font-bold text-danger">Settle Platform Commission Arrears</h3>
                            <p class="text-xs text-text-muted">Outstanding Arrears: <strong>&#8369;{{ number_format($arrearsAmount, 2) }}</strong></p>
                        </div>
                        <button type="button" @click="arrearsModalOpen = false" class="p-1 text-text-muted hover:text-text-main cursor-pointer">&times;</button>
                    </div>

                    @if($availableBalance > 0)
                        <div class="p-3.5 rounded-2xl bg-emerald-50 border border-emerald-200 flex items-center justify-between gap-3 text-xs">
                            <div>
                                <strong class="text-emerald-950 block">Instant Offset from Available E-Wallet</strong>
                                <span class="text-emerald-800 text-[11px]">Use &#8369;{{ number_format(min($availableBalance, $arrearsAmount), 2) }} from your Available E-Wallet</span>
                            </div>
                            <form action="{{ route('seller.arrears.wallet-offset') }}" method="POST" class="m-0">
                                @csrf
                                <button type="submit" class="px-3.5 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold cursor-pointer shrink-0">
                                    Offset Now
                                </button>
                            </form>
                        </div>
                    @endif

                    <form action="{{ route('seller.arrears.remit') }}"
                          method="POST"
                          enctype="multipart/form-data"
                          @submit="isSubmittingArrears = true"
                          class="space-y-4 text-xs">
                        @csrf
                        <div>
                            <label class="block font-bold text-text-muted uppercase mb-1">Amount Remitted via GCash (&#8369;) <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" min="1" name="amount_remitted" value="{{ number_format($arrearsAmount, 2, '.', '') }}" required class="w-full p-3 rounded-xl border border-border-subtle font-bold">
                        </div>
                        <div>
                            <label class="block font-bold text-text-muted uppercase mb-1">Sender GCash Number <span class="text-danger">*</span></label>
                            <input type="text" name="sender_gcash_number" maxlength="11" placeholder="09171234567" required class="w-full p-3 rounded-xl border border-border-subtle font-mono">
                        </div>
                        <div>
                            <label class="block font-bold text-text-muted uppercase mb-1">GCash Reference Number <span class="text-danger">*</span></label>
                            <input type="text" name="gcash_reference_number" maxlength="80" required class="w-full p-3 rounded-xl border border-border-subtle font-mono uppercase">
                        </div>
                        <div>
                            <label class="block font-bold text-text-muted uppercase mb-1">Receipt Screenshot (Max 5MB) <span class="text-danger">*</span></label>
                            <input type="file" name="screenshot" accept="image/jpeg,image/png,image/webp" required class="w-full p-2 rounded-xl border border-border-subtle">
                        </div>
                        <div class="flex justify-end gap-2 pt-2">
                            <button type="button" @click="arrearsModalOpen = false" class="px-4 py-2 rounded-xl bg-surface-subtle text-text-muted font-bold cursor-pointer">Cancel</button>
                            <button type="submit" :disabled="isSubmittingArrears" class="px-5 py-2 rounded-xl bg-danger text-white font-bold cursor-pointer">Submit Remittance</button>
                        </div>
                    </form>
                </div>
            </div>
        </template>
    @endif
</div>
@endsection