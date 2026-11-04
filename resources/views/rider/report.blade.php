<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Storkia - Rider Financial & Performance Report</title>
    <link rel="icon" type="image/webp" href="{{ asset('assets/pink-storkia-minimized.webp') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-surface-subtle/50 font-sans antialiased text-text-main min-h-screen pb-16 select-none">
@php
    $withdrawalRequests = $withdrawalRequests ?? collect();
    $hasPendingWithdrawal = $withdrawalRequests->whereIn('status', ['pending', 'approved'])->isNotEmpty();
    $availableBalance = (float) ($riderWallet->available_balance ?? 0);
@endphp

<div x-data="{
    withdrawModalOpen: false,
    withdrawConfirmStep: false,
    withdrawError: '',
    payoutMethod: 'GCASH',
    maxWithdrawable: {{ round(max(0, $availableBalance), 2) }},
    requestedAmount: {{ max(100, floor($availableBalance * 100) / 100) }},
    accountName: @js($rider->full_name),
    accountNumber: @js($rider->contact_no ?? ''),
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
        this.$refs.riderWithdrawForm.submit();
    }
}">
    <header class="bg-surface border-b border-border-subtle sticky top-0 z-40">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 py-3.5 flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-3">
                <a href="{{ route('rider.dashboard') }}" class="px-3.5 py-2 rounded-xl bg-surface-subtle hover:bg-brand-light/40 border border-border-subtle text-xs font-bold text-text-main">
                    &larr; Back to Rider Dashboard
                </a>
                <div>
                    <h1 class="text-base font-bold text-primary-dark">Rider Financial, E-Wallet &amp; Performance Report</h1>
                    <p class="text-xs text-text-muted">{{ $rider->full_name }} &bull; Hub: {{ $rider->assigned_hub?->hub_name ?? ($rider->logisticsProfile->business_name ?? 'Logistics Hub') }}</p>
                </div>
            </div>

            <div class="flex flex-wrap items-end gap-2 text-xs">
                <a href="{{ route('rider.ratings') }}" class="px-3.5 py-2 rounded-xl bg-amber-50 hover:bg-amber-100 text-amber-900 border border-amber-200 font-bold">
                    &#9733; My Ratings
                </a>

                <button type="button"
                        @click="openWithdrawModal()"
                        @if($availableBalance < 100 || $hasPendingWithdrawal) disabled @endif
                        class="px-4 py-2 rounded-xl font-bold shadow-xs {{ ($availableBalance >= 100 && !$hasPendingWithdrawal) ? 'bg-green-600 hover:bg-green-700 text-white cursor-pointer' : 'bg-gray-200 text-gray-500 cursor-not-allowed' }}">
                    {{ $hasPendingWithdrawal ? 'Withdrawal Pending' : 'Withdraw E-Wallet (Min &#8369;100)' }}
                </button>

                <form method="GET" action="{{ route('rider.report') }}" class="flex flex-wrap items-end gap-2">
                    <div>
                        <label class="block text-[10px] font-bold uppercase text-text-muted mb-0.5">From Date</label>
                        <input type="date" name="from_date" value="{{ $fromDate }}" class="px-3 py-1.5 rounded-xl border border-border-subtle bg-surface font-semibold">
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold uppercase text-text-muted mb-0.5">To Date</label>
                        <input type="date" name="to_date" value="{{ $toDate }}" class="px-3 py-1.5 rounded-xl border border-border-subtle bg-surface font-semibold">
                    </div>
                    <button type="submit" class="px-4 py-2 rounded-xl bg-primary-dark text-white font-bold cursor-pointer">
                        Filter
                    </button>
                    <button type="button" onclick="window.print()" class="px-3.5 py-2 rounded-xl bg-surface border border-border-subtle text-text-main font-bold cursor-pointer">
                        Print
                    </button>
                </form>
            </div>
        </div>
    </header>

    <main class="max-w-6xl mx-auto px-4 sm:px-6 pt-6 space-y-6">
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

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="p-5 bg-gradient-to-br from-primary-dark to-primary text-white rounded-2xl shadow-sm">
                <span class="text-[11px] font-bold uppercase text-white/80">Real-Money E-Wallet Balance</span>
                <p class="text-2xl font-serif font-extrabold mt-1">&#8369;{{ number_format((float) $riderWallet->available_balance, 2) }}</p>
                <span class="text-[10px] text-white/85">Withdrawable when &ge; &#8369;100.00</span>
            </div>
            <div class="p-5 bg-surface rounded-2xl border border-border-subtle shadow-xs">
                <span class="text-[11px] font-bold uppercase text-text-muted">Pending Escrow Balance</span>
                <p class="text-2xl font-serif font-bold text-amber-600 mt-1">&#8369;{{ number_format((float) $riderWallet->pending_balance, 2) }}</p>
                <span class="text-[10px] text-text-muted">Delivered &bull; Awaiting Buyer Confirm</span>
            </div>
            <div class="p-5 bg-surface rounded-2xl border border-border-subtle shadow-xs">
                <span class="text-[11px] font-bold uppercase text-text-muted">Period Realized Profit ({{ $fromDate }} &ndash; {{ $toDate }})</span>
                <p class="text-2xl font-serif font-bold text-green-700 mt-1">&#8369;{{ number_format($summary['period_realized_income'], 2) }}</p>
                <span class="text-[10px] text-text-muted">COD Collected: &#8369;{{ number_format($summary['period_cod_collected'], 2) }}</span>
            </div>
            <div class="p-5 bg-surface rounded-2xl border border-border-subtle shadow-xs">
                <span class="text-[11px] font-bold uppercase text-text-muted">Delivery Performance</span>
                <p class="text-2xl font-serif font-bold text-primary-dark mt-1">{{ $summary['delivered_count'] }} / {{ $summary['total_assigned'] }}</p>
                <span class="text-[10px] text-text-muted">Delivered vs. Failed ({{ $summary['failed_count'] }})</span>
            </div>
        </div>

        @if($withdrawalRequests->isNotEmpty())
            <div class="bg-surface rounded-3xl border border-border-subtle shadow-xs overflow-hidden">
                <div class="p-5 border-b border-border-subtle bg-surface-subtle/50">
                    <h2 class="text-base font-bold text-primary-dark">Rider E-Wallet Withdrawal Requests</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse text-xs">
                        <thead>
                            <tr class="border-b border-border-subtle bg-surface-subtle/40 text-text-muted uppercase text-[11px] font-bold">
                                <th class="py-3 px-5">Reference #</th>
                                <th class="py-3 px-4">Destination</th>
                                <th class="py-3 px-4">Amount</th>
                                <th class="py-3 px-4">Status</th>
                                <th class="py-3 px-4">Admin Notes / Proof</th>
                                <th class="py-3 px-5 text-right">Date</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border-subtle">
                            @foreach($withdrawalRequests as $wr)
                                <tr>
                                    <td class="py-3 px-5 font-mono font-bold text-primary-dark">{{ $wr->withdrawal_reference }}</td>
                                    <td class="py-3 px-4">{{ $wr->payout_method }} &bull; {{ $wr->account_name }} ({{ $wr->account_number }})</td>
                                    <td class="py-3 px-4 font-bold text-green-700">&#8369;{{ number_format((float) $wr->requested_amount, 2) }}</td>
                                    <td class="py-3 px-4">
                                        <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase {{ $wr->status === 'completed' ? 'bg-green-100 text-green-700' : ($wr->status === 'rejected' ? 'bg-red-100 text-danger' : 'bg-amber-100 text-amber-800') }}">
                                            {{ $wr->status }}
                                        </span>
                                    </td>
                                    <td class="py-3 px-4 text-text-muted">
                                        {{ $wr->payout_proof_reference ? 'Ref: ' . $wr->payout_proof_reference : ($wr->rejection_reason ?? $wr->admin_remarks ?? 'Pending Admin Review') }}
                                    </td>
                                    <td class="py-3 px-5 text-right text-text-muted">{{ \Carbon\Carbon::parse($wr->created_at)->format('M d, Y h:i A') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        <div class="bg-surface rounded-3xl border border-border-subtle shadow-xs overflow-hidden">
            <div class="p-5 border-b border-border-subtle bg-surface-subtle/50">
                <h2 class="text-base font-bold text-primary-dark">Rider E-Wallet Commission Ledger ({{ $fromDate }} to {{ $toDate }} &bull; FIFO)</h2>
            </div>
            @if($walletTransactions->isEmpty())
                <div class="p-10 text-center text-xs text-text-muted">No Rider commission transactions in the selected date range.</div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse text-xs">
                        <thead>
                            <tr class="border-b border-border-subtle bg-surface-subtle/40 text-text-muted uppercase text-[11px] font-bold">
                                <th class="py-3 px-5">Ref #</th>
                                <th class="py-3 px-4">Order / Shipment</th>
                                <th class="py-3 px-4">Type</th>
                                <th class="py-3 px-4">Amount</th>
                                <th class="py-3 px-4">Wallet Balance After</th>
                                <th class="py-3 px-5 text-right">Date</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border-subtle">
                            @foreach($walletTransactions as $wtx)
                                <tr>
                                    <td class="py-3 px-5 font-mono font-bold text-primary-dark">{{ $wtx->transaction_reference }}</td>
                                    <td class="py-3 px-4 font-mono">{{ $wtx->sellerOrder->seller_order_number ?? '' }} ({{ $wtx->shipment->tracking_number ?? '' }})</td>
                                    <td class="py-3 px-4">
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase {{ $wtx->entry_type === 'commission_released' ? 'bg-green-100 text-green-700' : 'bg-amber-100 text-amber-800' }}">
                                            {{ str_replace('_', ' ', $wtx->entry_type) }}
                                        </span>
                                    </td>
                                    <td class="py-3 px-4 font-bold {{ (float) $wtx->amount >= 0 ? 'text-green-700' : 'text-danger' }}">
                                        {{ (float) $wtx->amount >= 0 ? '+' : '' }}&#8369;{{ number_format((float) $wtx->amount, 2) }}
                                    </td>
                                    <td class="py-3 px-4 font-bold">&#8369;{{ number_format((float) $wtx->available_balance_after, 2) }}</td>
                                    <td class="py-3 px-5 text-right text-text-muted">{{ $wtx->created_at?->format('M d, Y h:i A') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </main>

    <!-- Rider Withdrawal Modal with Required GCash Account Holder Name & Pre-Submission Confirmation Step -->
    <div x-show="withdrawModalOpen" x-cloak class="fixed inset-0 z-[190] flex items-center justify-center p-4">
        <div @click="if (!isSubmittingWithdraw) { withdrawModalOpen = false; withdrawConfirmStep = false; }" class="absolute inset-0 bg-black/60 backdrop-blur-xs cursor-pointer"></div>
        <div @click.stop class="relative z-10 bg-surface rounded-3xl max-w-md w-full p-6 shadow-2xl border border-border-subtle space-y-4">
            <div class="flex items-center justify-between pb-3 border-b border-border-subtle">
                <div>
                    <h3 class="text-base font-bold text-primary-dark" x-text="withdrawConfirmStep ? 'Confirm Rider Withdrawal Details' : 'Request Rider E-Wallet Withdrawal'"></h3>
                    <p class="text-xs text-text-muted">Available Balance: <strong class="text-green-700">&#8369;{{ number_format($availableBalance, 2) }}</strong></p>
                </div>
                <button type="button"
                        @click="if (!isSubmittingWithdraw) { withdrawModalOpen = false; withdrawConfirmStep = false; }"
                        class="p-1 text-text-muted hover:text-text-main cursor-pointer">&times;</button>
            </div>

            <form x-ref="riderWithdrawForm"
                  action="{{ route('rider.withdrawals.store') }}"
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
                               maxlength="11"
                               placeholder="09171234567"
                               required
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
</div>
</body>
</html>