@extends('layouts.seller', ['title' => 'Storkia - Seller E-Wallet, Payouts & Financial Report'])

@section('content')
@php
    $payoutRequests = $payoutRequests ?? collect();
    $hasPendingPayout = $payoutRequests->whereIn('status', ['pending', 'approved'])->isNotEmpty();
    $availableBalance = (float) ($metrics['wallet_available'] ?? $metrics['settled_total'] ?? 0);
    $arrearsAmount = (float) ($metrics['wallet_arrears'] ?? ($availableBalance < 0 ? abs($availableBalance) : 0));
    $netWithdrawableAfterArrears = max(0.0, round($availableBalance - $arrearsAmount, 2));
    $canWithdraw = $netWithdrawableAfterArrears >= 100 && !$hasPendingPayout;

    $resolvedFromDate = $fromDate ?? now()->startOfMonth()->toDateString();
    $resolvedToDate = $toDate ?? now()->toDateString();

    $settlementsConfig = [
        'canWithdraw'     => (bool) $canWithdraw,
        'maxWithdrawable' => round($netWithdrawableAfterArrears, 2),
        'requestedAmount' => max(100, floor($netWithdrawableAfterArrears * 100) / 100),
        'accountName'     => (string) (auth()->user()?->full_name ?? ''),
        'fromDate'        => $resolvedFromDate,
        'toDate'          => $resolvedToDate,
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
            showAllPayouts: false,
            showAllCommissions: false,
            fromDate: cfg.fromDate || '',
            toDate: cfg.toDate || '',
            activeCalField: null,
            calYear: new Date().getFullYear(),
            calMonth: new Date().getMonth(),

            notify(msg, type = 'danger') {
                if (!msg) return;
                window.dispatchEvent(new CustomEvent('toast', {
                    detail: {
                        title: type === 'danger' ? 'Withdrawal Notice' : 'E-Wallet',
                        message: msg,
                        type: type
                    }
                }));
            },

            openCalendar(field) {
                if (this.activeCalField === field) {
                    this.activeCalField = null;
                    return;
                }
                this.activeCalField = field;
                const currentIso = field === 'from' ? this.fromDate : this.toDate;
                const d = currentIso ? new Date(currentIso + 'T00:00:00') : new Date();
                if (!isNaN(d.getTime())) {
                    this.calYear = d.getFullYear();
                    this.calMonth = d.getMonth();
                }
            },

            get calMonthYearLabel() {
                const months = ['January','February','March','April','May','June','July','August','September','October','November','December'];
                return (months[this.calMonth] || '') + ' ' + this.calYear;
            },

            get calendarDays() {
                const firstDay = new Date(this.calYear, this.calMonth, 1).getDay();
                const daysInMonth = new Date(this.calYear, this.calMonth + 1, 0).getDate();
                const cells = [];
                for (let i = 0; i < firstDay; i++) {
                    cells.push({ day: null, iso: '' });
                }
                const targetIso = this.activeCalField === 'from' ? this.fromDate : this.toDate;
                for (let d = 1; d <= daysInMonth; d++) {
                    const m = String(this.calMonth + 1).padStart(2, '0');
                    const dayStr = String(d).padStart(2, '0');
                    const iso = `${this.calYear}-${m}-${dayStr}`;
                    cells.push({
                        day: d,
                        iso: iso,
                        selected: iso === targetIso
                    });
                }
                return cells;
            },

            prevCalMonth() {
                if (this.calMonth === 0) {
                    this.calMonth = 11;
                    this.calYear--;
                } else {
                    this.calMonth--;
                }
            },

            nextCalMonth() {
                if (this.calMonth === 11) {
                    this.calMonth = 0;
                    this.calYear++;
                } else {
                    this.calMonth++;
                }
            },

            pickCalDate(cell) {
                if (!cell || !cell.iso) return;
                if (this.activeCalField === 'from') {
                    this.fromDate = cell.iso;
                } else if (this.activeCalField === 'to') {
                    this.toDate = cell.iso;
                }
                this.activeCalField = null;
            },

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
                    this.notify(this.payoutError, 'danger');
                    return;
                }
                if (amt > this.maxWithdrawable + 0.001) {
                    this.payoutError = 'Requested amount cannot exceed your net withdrawable balance of \u20B1' + this.maxWithdrawable.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + '.';
                    this.notify(this.payoutError, 'danger');
                    return;
                }
                if (!this.accountName || this.accountName.trim().length < 2) {
                    this.payoutError = 'Please enter the GCash Account Holder Name (at least 2 characters).';
                    this.notify(this.payoutError, 'danger');
                    return;
                }
                if (!/^09\d{9}$/.test(this.accountNumber)) {
                    this.payoutError = 'Please enter a valid 11-digit GCash mobile number starting with 09 (e.g., 09171234567).';
                    this.notify(this.payoutError, 'danger');
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
            <a :href="`{{ route('seller.reports.formal-pdf') }}?from_date=${fromDate}&to_date=${toDate}&preset=custom`"
               target="_blank"
               class="px-4 py-2.5 rounded-2xl bg-primary-dark hover:bg-primary text-white text-xs font-bold shadow-xs transition-colors cursor-pointer">
                Open Formal Printable PDF Statement
            </a>

            <button type="button"
                    @click="openPayoutModal()"
                    :disabled="!canWithdraw"
                    class="px-4 py-2.5 rounded-2xl text-xs font-bold shadow-xs transition-colors {{ $canWithdraw ? 'bg-primary hover:bg-primary-dark text-white cursor-pointer' : 'bg-surface-subtle text-text-muted border border-border-subtle cursor-not-allowed' }}">
                {{ $hasPendingPayout ? 'Withdrawal Request Pending Admin Review' : 'Request Withdrawal (Min &#8369;100)' }}
            </button>

            @if($arrearsAmount > 0)
                @if($availableBalance > 0)
                    <form action="{{ route('seller.arrears.wallet-offset') }}" method="POST" class="m-0">
                        @csrf
                        <button type="submit"
                                class="px-4 py-2.5 rounded-2xl bg-brand-light hover:bg-secondary text-primary-dark border border-primary/30 text-xs font-bold shadow-xs transition-colors cursor-pointer">
                            Offset Arrears from E-Wallet (&#8369;{{ number_format(min($availableBalance, $arrearsAmount), 2) }})
                        </button>
                    </form>
                @endif

                <button type="button"
                        @click="arrearsModalOpen = true"
                        class="px-4 py-2.5 rounded-2xl bg-danger hover:opacity-90 text-white text-xs font-bold shadow-xs transition-opacity cursor-pointer">
                    Remit Arrears via GCash (&#8369;{{ number_format($arrearsAmount, 2) }})
                </button>
            @endif
        </div>
    </div>

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
            <p class="text-2xl font-serif font-bold text-primary-dark mt-2">&#8369;{{ number_format($metrics['wallet_pending_escrow'] ?? $metrics['pending_settlement'] ?? 0, 2) }}</p>
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
            <p class="text-2xl font-serif font-bold text-primary mt-2">&#8369;{{ number_format($metrics['net_seller_earnings'] ?? 0, 2) }}</p>
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
                        @foreach($payoutRequests as $pIdx => $pr)
                            @php
                                $refCode = $pr->withdrawal_reference ?? $pr->payout_reference ?? ('WDR-' . $pr->id);
                                $statusBadge = match($pr->status) {
                                    'completed' => 'bg-success/15 text-success border-success/25',
                                    'rejected'  => 'bg-danger/10 text-danger border-danger/25',
                                    default     => 'bg-brand-light/70 text-primary-dark border-border-subtle',
                                };
                            @endphp
                            <tr x-show="{{ $pIdx }} < 5 || showAllPayouts" class="hover:bg-surface-subtle/30">
                                <td class="py-3.5 px-5 font-mono font-bold text-primary-dark select-text">{{ $refCode }}</td>
                                <td class="py-3.5 px-4">
                                    <span class="px-2 py-0.5 rounded bg-brand-light/40 text-primary-dark font-bold text-[10px] mr-1">{{ $pr->payout_method }}</span>
                                    <span class="font-semibold text-text-main">{{ $pr->account_name }}</span>
                                    <span class="font-mono text-text-muted ml-1">({{ $pr->account_number }})</span>
                                </td>
                                <td class="py-3.5 px-4 font-bold text-primary">&#8369;{{ number_format((float) $pr->requested_amount, 2) }}</td>
                                <td class="py-3.5 px-4">
                                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase border {{ $statusBadge }}">
                                        {{ $pr->status }}
                                    </span>
                                </td>
                                <td class="py-3.5 px-4 text-text-muted max-w-xs">
                                    @if(!empty($pr->payout_proof_reference))
                                        <div class="font-mono font-bold text-success select-text">Ref: {{ $pr->payout_proof_reference }}</div>
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

            @if($payoutRequests->count() > 5)
                <div class="p-4 border-t border-border-subtle bg-surface-subtle/30 flex justify-center">
                    <button type="button"
                            @click="showAllPayouts = !showAllPayouts"
                            class="inline-flex items-center gap-2 px-5 py-2 rounded-full bg-primary/10 hover:bg-primary text-primary hover:text-white border border-primary/30 text-xs font-extrabold transition-colors cursor-pointer">
                        <span x-text="showAllPayouts ? 'See Less Withdrawal Requests' : 'See More Withdrawal Requests (' + ({{ $payoutRequests->count() }} - 5) + ' more)'"></span>
                    </button>
                </div>
            @endif
        @endif
    </div>

    <div class="bg-surface rounded-3xl border border-border-subtle shadow-xs overflow-visible">
        <div class="p-5 border-b border-border-subtle bg-surface-subtle/50 rounded-t-3xl flex flex-wrap items-center justify-between gap-3">
            <h2 class="text-base font-bold text-primary-dark">Per-Order Commission &amp; E-Wallet Earnings Ledger (FIFO)</h2>

            <form method="GET" action="{{ route('seller.settlements.index') }}" class="flex flex-wrap items-center gap-2 text-xs relative" @click.away="activeCalField = null">
                <input type="hidden" name="from_date" :value="fromDate">
                <input type="hidden" name="to_date" :value="toDate">

                <button type="button"
                        @click="openCalendar('from')"
                        class="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl border border-border-subtle hover:border-primary bg-surface font-mono font-bold text-primary-dark transition-colors cursor-pointer">
                    <svg class="w-3.5 h-3.5 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    <span x-text="fromDate"></span>
                </button>

                <span class="text-text-muted font-semibold">to</span>

                <button type="button"
                        @click="openCalendar('to')"
                        class="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl border border-border-subtle hover:border-primary bg-surface font-mono font-bold text-primary-dark transition-colors cursor-pointer">
                    <svg class="w-3.5 h-3.5 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    <span x-text="toDate"></span>
                </button>

                <button type="submit" class="px-4 py-2 rounded-xl bg-primary-dark hover:bg-primary text-white font-bold transition-colors cursor-pointer">
                    Filter
                </button>

                <div x-show="activeCalField !== null"
                     x-cloak
                     x-transition
                     class="absolute right-0 top-full mt-2 w-72 bg-surface border border-border-subtle rounded-2xl shadow-2xl p-4 z-50">
                    <div class="flex items-center justify-between mb-3">
                        <button type="button" @click="prevCalMonth()" class="p-1.5 rounded-lg hover:bg-surface-subtle text-text-main cursor-pointer">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                        </button>
                        <div class="text-center">
                            <span class="text-[10px] font-extrabold uppercase text-primary block" x-text="activeCalField === 'from' ? 'Select Start Date' : 'Select End Date'"></span>
                            <span class="text-xs font-extrabold text-primary-dark" x-text="calMonthYearLabel"></span>
                        </div>
                        <button type="button" @click="nextCalMonth()" class="p-1.5 rounded-lg hover:bg-surface-subtle text-text-main cursor-pointer">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                        </button>
                    </div>
                    <div class="grid grid-cols-7 gap-1 text-[10px] font-bold text-text-muted text-center mb-1">
                        <span>Su</span><span>Mo</span><span>Tu</span><span>We</span><span>Th</span><span>Fr</span><span>Sa</span>
                    </div>
                    <div class="grid grid-cols-7 gap-1">
                        <template x-for="(cell, cIdx) in calendarDays" :key="'set_cal_' + cIdx">
                            <div>
                                <template x-if="cell.day !== null">
                                    <button type="button"
                                            @click="pickCalDate(cell)"
                                            :class="cell.selected ? 'bg-primary text-white font-extrabold' : 'text-text-main hover:bg-brand-light/40 font-semibold'"
                                            class="w-8 h-8 rounded-lg text-xs flex items-center justify-center transition-colors cursor-pointer"
                                            x-text="cell.day"></button>
                                </template>
                            </div>
                        </template>
                    </div>
                </div>
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
                        @foreach($commissionRecords as $cIdx => $rec)
                            @php
                                $sellerNetProfit = (float) ($rec->seller_net_earning ?? $rec->seller_net_amount ?? 0);
                            @endphp
                            <tr x-show="{{ $cIdx }} < 6 || showAllCommissions" class="hover:bg-surface-subtle/30">
                                <td class="py-3.5 px-5 font-mono font-bold text-primary-dark">
                                    <a href="{{ route('seller.orders.show', $rec->seller_order_id) }}" class="hover:underline cursor-pointer">
                                        {{ $rec->sellerOrder->seller_order_number ?? '#' . $rec->seller_order_id }}
                                    </a>
                                </td>
                                <td class="py-3.5 px-4">&#8369;{{ number_format((float) $rec->product_subtotal, 2) }}</td>
                                <td class="py-3.5 px-4 text-danger font-semibold">-&#8369;{{ number_format((float) $rec->admin_commission_amount, 2) }}</td>
                                <td class="py-3.5 px-4 font-extrabold text-primary">&#8369;{{ number_format($sellerNetProfit, 2) }}</td>
                                <td class="py-3.5 px-4">
                                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase {{ $rec->status === 'settled' ? 'bg-success/15 text-success' : 'bg-brand-light/60 text-primary-dark' }}">
                                        Credited to E-Wallet ({{ $rec->status }})
                                    </span>
                                </td>
                                <td class="py-3.5 px-5 text-right text-text-muted">{{ $rec->created_at?->format('M d, Y h:i A') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if($commissionRecords->count() > 6)
                <div class="p-4 border-t border-border-subtle bg-surface-subtle/30 rounded-b-3xl flex justify-center">
                    <button type="button"
                            @click="showAllCommissions = !showAllCommissions"
                            class="inline-flex items-center gap-2 px-5 py-2 rounded-full bg-primary/10 hover:bg-primary text-primary hover:text-white border border-primary/30 text-xs font-extrabold transition-colors cursor-pointer">
                        <span x-text="showAllCommissions ? 'See Less Ledger Entries' : 'See More Ledger Entries (' + ({{ $commissionRecords->count() }} - 6) + ' more)'"></span>
                    </button>
                </div>
            @endif
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
                        <p class="text-xs text-text-muted">Net Withdrawable: <strong class="text-primary">&#8369;{{ number_format($netWithdrawableAfterArrears, 2) }}</strong></p>
                    </div>
                    <button type="button"
                            @click="if (!isSubmittingPayout) { payoutModalOpen = false; payoutConfirmStep = false; }"
                            class="p-1 text-text-muted hover:text-text-main text-lg font-bold cursor-pointer">&times;</button>
                </div>

                @if($arrearsAmount > 0)
                    <div class="p-3 rounded-2xl bg-brand-light/40 border border-primary/30 text-primary-dark text-[11px] font-semibold">
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
                                   class="w-full p-3 rounded-xl border border-border-subtle bg-surface-subtle text-text-muted font-bold cursor-not-allowed select-none outline-none">
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

                        <div class="flex justify-end gap-2 pt-2 border-t border-border-subtle">
                            <button type="button"
                                    @click="payoutModalOpen = false; payoutConfirmStep = false;"
                                    class="px-4 py-2.5 rounded-xl bg-surface-subtle hover:bg-brand-light/30 text-text-muted hover:text-text-main font-bold transition-colors cursor-pointer">
                                Cancel
                            </button>
                            <button type="button"
                                    @click="reviewPayoutRequest()"
                                    class="px-5 py-2.5 rounded-xl bg-primary hover:bg-primary-dark text-white font-bold shadow-xs transition-colors cursor-pointer">
                                Review Withdrawal Request &rarr;
                            </button>
                        </div>
                    </div>

                    <div x-show="payoutConfirmStep" x-cloak class="space-y-4">
                        <div class="p-3.5 rounded-2xl bg-brand-light/40 border border-primary/30 text-primary-dark space-y-1">
                            <div class="font-extrabold text-xs uppercase tracking-wider">Please Confirm Your GCash Details</div>
                            <p class="text-[11px] leading-relaxed text-text-main">
                                Please verify that the <strong>GCash Account Holder Name</strong> and <strong>11-Digit GCash Number</strong> below are accurate before proceeding. Approved funds will be transferred to this account.
                            </p>
                        </div>

                        <div class="p-4 rounded-2xl bg-surface-subtle border border-border-subtle space-y-2.5 text-xs">
                            <div class="flex items-center justify-between">
                                <span class="text-text-muted">Withdrawal Amount:</span>
                                <strong class="text-base font-serif font-extrabold text-primary"
                                        x-text="'\u20B1' + Number(requestedAmount || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })"></strong>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-text-muted">Payout Channel:</span>
                                <strong class="px-2 py-0.5 rounded bg-primary/15 text-primary-dark font-bold text-[10px]">GCash</strong>
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
                                    class="px-4 py-2.5 rounded-xl bg-surface-subtle hover:bg-brand-light/30 text-text-muted hover:text-text-main font-bold transition-colors cursor-pointer">
                                &larr; Back to Edit
                            </button>
                            <button type="submit"
                                    :disabled="isSubmittingPayout"
                                    class="px-5 py-2.5 rounded-xl bg-primary hover:bg-primary-dark text-white font-bold shadow-xs transition-colors cursor-pointer disabled:opacity-50">
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
                        <button type="button" @click="arrearsModalOpen = false" class="p-1 text-text-muted hover:text-text-main text-lg font-bold cursor-pointer">&times;</button>
                    </div>

                    @if($availableBalance > 0)
                        <div class="p-3.5 rounded-2xl bg-brand-light/35 border border-primary/25 flex items-center justify-between gap-3 text-xs">
                            <div>
                                <strong class="text-primary-dark block">Instant Offset from Available E-Wallet</strong>
                                <span class="text-text-main text-[11px]">Use &#8369;{{ number_format(min($availableBalance, $arrearsAmount), 2) }} from your Available E-Wallet</span>
                            </div>
                            <form action="{{ route('seller.arrears.wallet-offset') }}" method="POST" class="m-0">
                                @csrf
                                <button type="submit" class="px-3.5 py-2 rounded-xl bg-primary hover:bg-primary-dark text-white font-bold transition-colors cursor-pointer shrink-0">
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
                            <input type="number" step="0.01" min="1" name="amount_remitted" value="{{ number_format($arrearsAmount, 2, '.', '') }}" required class="w-full p-3 rounded-xl border border-border-subtle bg-surface font-bold outline-none focus:border-primary">
                        </div>
                        <div>
                            <label class="block font-bold text-text-muted uppercase mb-1">Sender GCash Number <span class="text-danger">*</span></label>
                            <input type="text" name="sender_gcash_number" maxlength="11" placeholder="09171234567" required class="w-full p-3 rounded-xl border border-border-subtle bg-surface font-mono outline-none focus:border-primary">
                        </div>
                        <div>
                            <label class="block font-bold text-text-muted uppercase mb-1">GCash Reference Number <span class="text-danger">*</span></label>
                            <input type="text" name="gcash_reference_number" maxlength="80" required class="w-full p-3 rounded-xl border border-border-subtle bg-surface font-mono uppercase outline-none focus:border-primary">
                        </div>
                        <div>
                            <label class="block font-bold text-text-muted uppercase mb-1">Receipt Screenshot (Max 5MB) <span class="text-danger">*</span></label>
                            <input type="file" name="screenshot" accept="image/jpeg,image/png,image/webp" required class="w-full p-2 rounded-xl border border-border-subtle bg-surface cursor-pointer">
                        </div>
                        <div class="flex justify-end gap-2 pt-2">
                            <button type="button" @click="arrearsModalOpen = false" class="px-4 py-2 rounded-xl bg-surface-subtle hover:bg-brand-light/30 text-text-muted hover:text-text-main font-bold transition-colors cursor-pointer">Cancel</button>
                            <button type="submit" :disabled="isSubmittingArrears" class="px-5 py-2 rounded-xl bg-danger hover:opacity-90 text-white font-bold transition-opacity cursor-pointer">Submit Remittance</button>
                        </div>
                    </form>
                </div>
            </div>
        </template>
    @endif
</div>
@endsection