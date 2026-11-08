@extends('layouts.admin', ['title' => 'Storkia Admin - Seller Report ' . $report->report_number])

@section('content')
@php
    $reporter      = $report->reporter;
    $sellerOrder   = $report->sellerOrder;
    $evidenceUrls  = $report->evidence_urls;
    $isClosed      = $report->isClosed();
    $storeName     = $sellerProfile?->business_name ?? 'Seller no longer exists';

    $badgeClass = match ($report->status) {
        \App\Models\SellerReport::STATUS_RESOLVED     => 'bg-green-100 text-green-700 border-green-200',
        \App\Models\SellerReport::STATUS_UNDER_REVIEW => 'bg-blue-100 text-blue-700 border-blue-200',
        \App\Models\SellerReport::STATUS_DISMISSED    => 'bg-gray-100 text-gray-700 border-gray-200',
        default                                       => 'bg-amber-100 text-amber-800 border-amber-200',
    };

    // The seller's current account standing, read from the same sanction row the Accounts page uses.
    $sellerStatusLabel = 'Active';
    $sellerStatusClass = 'bg-green-100 text-green-700 border-green-200';
    if ($sanction && $sanction->isPermanentlyBanned()) {
        $sellerStatusLabel = 'Permanently banned';
        $sellerStatusClass = 'bg-red-100 text-red-700 border-red-200';
    } elseif ($sanction && $sanction->account_status === 'temp_banned') {
        $sellerStatusLabel = 'Suspended' . ($sanction->temp_ban_ends_at ? ' until ' . $sanction->temp_ban_ends_at->format('M d, Y') : '');
        $sellerStatusClass = 'bg-red-100 text-red-700 border-red-200';
    } elseif ($sellerIsBlocked) {
        $sellerStatusLabel = 'Blocked (appeal pending)';
        $sellerStatusClass = 'bg-amber-100 text-amber-800 border-amber-200';
    }

    // Which decision form the admin was last using, so a validation error reopens the right one.
    $resolveSubmitted = old('action') !== null
        || old('seller_message') !== null
        || $errors->has('action')
        || $errors->has('seller_message');
    $dismissSubmitted = ! $resolveSubmitted && $errors->has('admin_note');
    $initialMode = $resolveSubmitted ? 'resolve' : ($dismissSubmitted ? 'dismiss' : '');

    $reviewedBy = $report->reviewed_by_admin_id ? \App\Models\Admin::find($report->reviewed_by_admin_id) : null;
    $resolvedBy = $report->resolved_by_admin_id ? \App\Models\Admin::find($report->resolved_by_admin_id) : null;
@endphp

<div x-data="{
        photoOpen: false,
        photoUrl: '',
        mode: @js($initialMode),
        action: @js((string) old('action', '')),
        openPhoto(url) {
            this.photoUrl = url;
            this.photoOpen = true;
        }
     }"
     class="p-8 lg:p-10 max-w-6xl mx-auto space-y-6 select-none">

    <!-- Header -->
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.seller-reports.index') }}"
               class="p-2.5 bg-surface rounded-full border border-border-subtle text-text-muted hover:text-primary transition-colors">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                </svg>
            </a>
            <div>
                <div class="flex flex-wrap items-center gap-2.5">
                    <h1 class="text-2xl font-bold text-primary-dark font-mono select-text">{{ $report->report_number }}</h1>
                    <span class="px-3 py-1 rounded-full text-xs font-bold border {{ $badgeClass }}">{{ $report->status_label }}</span>
                    @if($report->admin_action_label)
                        <span class="px-2.5 py-1 rounded-full text-[11px] font-bold bg-surface-subtle text-text-main border border-border-subtle">{{ $report->admin_action_label }}</span>
                    @endif
                </div>
                <p class="text-xs text-text-muted mt-0.5">
                    Reported against <strong>{{ $storeName }}</strong> &bull;
                    Submitted {{ $report->created_at?->format('M d, Y h:i A') }}
                </p>
            </div>
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

    @if($sellerIsBlocked && ! $isClosed)
        <div class="p-4 rounded-2xl bg-amber-50 border border-amber-200 text-amber-900 text-xs sm:text-sm font-semibold">
            This seller is already <strong>{{ strtolower($sellerStatusLabel) }}</strong>. You can still warn them or dismiss this report,
            but suspending or banning again is not available.
        </div>
    @endif

    <!-- Decision panel (open reports only) -->
    @if($canDecide)
        <div class="p-6 rounded-3xl bg-red-50/70 border-2 border-red-200 space-y-5">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="text-base font-bold text-red-950">
                        {{ $canStartReview ? 'Decide: New Report Against This Seller' : 'Decide: Report Under Review' }}
                    </h2>
                    <p class="text-xs text-red-900 mt-0.5 leading-relaxed">
                        Read the report and evidence below, then dismiss it or take action against the seller.
                        The reporter is told only that the report was reviewed. The seller is never told who reported them.
                    </p>
                </div>

                @if($canStartReview)
                    <form action="{{ route('admin.seller-reports.start-review', $report->id) }}" method="POST" class="m-0">
                        @csrf
                        <button type="submit"
                                class="px-4 py-2.5 rounded-xl bg-white border border-blue-300 hover:bg-blue-50 text-blue-800 text-xs font-bold transition-colors cursor-pointer">
                            Mark as Under Review
                        </button>
                    </form>
                @endif
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-xs">
                <button type="button"
                        @click="mode = 'resolve'"
                        :class="mode === 'resolve' ? 'border-danger bg-red-50 text-danger' : 'border-border-subtle bg-white text-text-muted hover:border-danger/50'"
                        class="p-4 rounded-xl border-2 text-left font-bold cursor-pointer transition-colors">
                    Take action against the seller
                    <span class="block font-normal mt-0.5">Warn, suspend for {{ \App\Models\BuyerAccountSanction::TEMP_BAN_DAYS }} days, or permanently ban.</span>
                </button>
                <button type="button"
                        @click="mode = 'dismiss'"
                        :class="mode === 'dismiss' ? 'border-primary-dark bg-white text-primary-dark' : 'border-border-subtle bg-white text-text-muted hover:border-primary/50'"
                        class="p-4 rounded-xl border-2 text-left font-bold cursor-pointer transition-colors">
                    Dismiss the report
                    <span class="block font-normal mt-0.5">No policy violation found. No action is taken.</span>
                </button>
            </div>

            <!-- Take action -->
            <form x-show="mode === 'resolve'"
                  x-cloak
                  action="{{ route('admin.seller-reports.resolve', $report->id) }}"
                  method="POST"
                  @submit="if (action === 'suspend_perm' && ! confirm('Permanently ban this seller? They are signed out immediately.')) { $event.preventDefault(); } else if (action === 'suspend_temp' && ! confirm('Suspend this seller for {{ \App\Models\BuyerAccountSanction::TEMP_BAN_DAYS }} days? They are signed out immediately.')) { $event.preventDefault(); }"
                  class="space-y-4 text-xs">
                @csrf

                <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                    <label class="p-3.5 rounded-xl border cursor-pointer flex items-start gap-2.5 font-bold"
                           :class="action === '{{ \App\Models\SellerReport::ACTION_WARN }}' ? 'border-amber-500 bg-amber-50 text-amber-900' : 'border-border-subtle bg-white text-text-muted'">
                        <input type="radio" name="action" value="{{ \App\Models\SellerReport::ACTION_WARN }}" x-model="action" class="mt-0.5">
                        <span>Warn the seller
                            <span class="block font-normal mt-0.5">The seller is notified. Their account is not changed.</span>
                        </span>
                    </label>

                    <label class="p-3.5 rounded-xl border flex items-start gap-2.5 font-bold {{ $sellerIsBlocked ? 'opacity-50 cursor-not-allowed' : 'cursor-pointer' }}"
                           :class="action === '{{ \App\Models\SellerReport::ACTION_SUSPEND_TEMP }}' ? 'border-danger bg-red-50 text-danger' : 'border-border-subtle bg-white text-text-muted'">
                        <input type="radio" name="action" value="{{ \App\Models\SellerReport::ACTION_SUSPEND_TEMP }}" x-model="action" class="mt-0.5" @disabled($sellerIsBlocked)>
                        <span>Suspend for {{ \App\Models\BuyerAccountSanction::TEMP_BAN_DAYS }} days
                            <span class="block font-normal mt-0.5">The seller is signed out and blocked for {{ \App\Models\BuyerAccountSanction::TEMP_BAN_DAYS }} days.</span>
                        </span>
                    </label>

                    <label class="p-3.5 rounded-xl border flex items-start gap-2.5 font-bold {{ $sellerIsBlocked ? 'opacity-50 cursor-not-allowed' : 'cursor-pointer' }}"
                           :class="action === '{{ \App\Models\SellerReport::ACTION_SUSPEND_PERM }}' ? 'border-danger bg-red-50 text-danger' : 'border-border-subtle bg-white text-text-muted'">
                        <input type="radio" name="action" value="{{ \App\Models\SellerReport::ACTION_SUSPEND_PERM }}" x-model="action" class="mt-0.5" @disabled($sellerIsBlocked)>
                        <span>Permanently ban
                            <span class="block font-normal mt-0.5">The seller is permanently banned and signed out immediately.</span>
                        </span>
                    </label>
                </div>

                <div>
                    <label class="block font-bold text-text-muted mb-1">
                        Message to the seller (10 to 500 characters, they will see this) <span class="text-danger">*</span>
                    </label>
                    <textarea name="seller_message"
                              rows="3"
                              minlength="10"
                              maxlength="500"
                              placeholder="Explain what the seller did wrong and what they must change. Do not mention who reported them."
                              class="w-full p-3 rounded-xl border border-border-subtle bg-white outline-none focus:border-primary">{{ $resolveSubmitted ? old('seller_message') : '' }}</textarea>
                </div>

                <div>
                    <label class="block font-bold text-text-muted mb-1">
                        Internal note (5 to 1000 characters, only admins see this) <span class="text-danger">*</span>
                    </label>
                    <textarea name="admin_note"
                              rows="3"
                              minlength="5"
                              maxlength="1000"
                              placeholder="What did you check, and why did you choose this action?"
                              class="w-full p-3 rounded-xl border border-border-subtle bg-white outline-none focus:border-primary">{{ $resolveSubmitted ? old('admin_note') : '' }}</textarea>
                </div>

                <div class="flex justify-end">
                    <button type="submit"
                            :disabled="action === ''"
                            :class="action !== '' ? 'bg-primary-dark hover:bg-text-main text-white cursor-pointer' : 'bg-gray-300 text-gray-500 cursor-not-allowed'"
                            class="px-6 py-3 rounded-xl font-bold transition-colors">
                        Submit Action
                    </button>
                </div>
            </form>

            <!-- Dismiss -->
            <form x-show="mode === 'dismiss'"
                  x-cloak
                  action="{{ route('admin.seller-reports.dismiss', $report->id) }}"
                  method="POST"
                  class="space-y-4 text-xs">
                @csrf

                <div class="p-3.5 rounded-xl bg-white border border-border-subtle text-text-muted leading-relaxed">
                    The reporter is told that Storkia reviewed the report and did not find a policy violation. They can send a new report if they have new evidence. The seller is not told anything.
                </div>

                <div>
                    <label class="block font-bold text-text-muted mb-1">
                        Internal note (5 to 1000 characters, only admins see this) <span class="text-danger">*</span>
                    </label>
                    <textarea name="admin_note"
                              rows="3"
                              minlength="5"
                              maxlength="1000"
                              placeholder="Why is there no violation? For example: evidence does not match, order was delivered as described..."
                              class="w-full p-3 rounded-xl border border-border-subtle bg-white outline-none focus:border-primary">{{ $dismissSubmitted ? old('admin_note') : '' }}</textarea>
                </div>

                <div class="flex justify-end">
                    <button type="submit" class="px-6 py-3 rounded-xl bg-primary-dark hover:bg-text-main text-white font-bold transition-colors cursor-pointer">
                        Dismiss Report
                    </button>
                </div>
            </form>
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">

        <!-- Left: the report itself -->
        <div class="lg:col-span-7 space-y-6">

            <div class="bg-surface rounded-3xl border border-border-subtle p-6 shadow-xs space-y-4 text-xs">
                <h3 class="text-sm font-bold text-primary-dark">What the Buyer Reported</h3>

                <div class="flex flex-wrap items-center gap-2">
                    <span class="px-3 py-1 rounded-full bg-danger/10 text-danger text-[11px] font-bold">{{ $report->category_label }}</span>
                    @if($sellerOrder)
                        <span class="px-3 py-1 rounded-full bg-surface-subtle border border-border-subtle text-text-main text-[11px] font-bold">
                            Order {{ $sellerOrder->seller_order_number }} &bull; {{ $sellerOrder->status_label }}
                        </span>
                    @endif
                </div>

                <div>
                    <span class="font-bold text-text-muted uppercase text-[10px] block">Buyer's Description</span>
                    <p class="text-sm font-semibold text-text-main mt-1 leading-relaxed whitespace-pre-line break-words select-text">{{ $report->description }}</p>
                </div>

                @if(count($evidenceUrls) > 0)
                    <div>
                        <span class="font-bold text-text-muted uppercase text-[10px] block mb-2">Evidence Photos ({{ count($evidenceUrls) }})</span>
                        <div class="flex flex-wrap items-center gap-3">
                            @foreach($evidenceUrls as $photoUrl)
                                <button type="button"
                                        @click.stop="openPhoto(@js($photoUrl))"
                                        class="w-20 h-20 rounded-xl overflow-hidden border border-border-subtle bg-surface-subtle hover:border-primary transition-all cursor-pointer p-1">
                                    <img src="{{ $photoUrl }}" alt="Report evidence" class="w-full h-full object-cover rounded-lg">
                                </button>
                            @endforeach
                        </div>
                    </div>
                @else
                    <p class="text-text-muted">The buyer did not attach any photos.</p>
                @endif
            </div>

            @if($isClosed)
                <div class="bg-surface rounded-3xl border border-border-subtle p-6 shadow-xs space-y-3 text-xs">
                    <h3 class="text-sm font-bold text-primary-dark">Outcome</h3>
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="px-3 py-1 rounded-full text-[11px] font-bold border {{ $badgeClass }}">{{ $report->status_label }}</span>
                        @if($report->admin_action_label)
                            <span class="px-3 py-1 rounded-full bg-surface-subtle border border-border-subtle text-text-main text-[11px] font-bold">{{ $report->admin_action_label }}</span>
                        @endif
                    </div>
                    <div>
                        <span class="font-bold text-text-muted uppercase text-[10px] block">Internal Note</span>
                        <p class="text-sm text-text-main mt-1 leading-relaxed whitespace-pre-line break-words select-text">{{ $report->admin_note }}</p>
                    </div>
                    <div class="pt-3 border-t border-border-subtle space-y-1 text-text-muted">
                        <p>Submitted {{ $report->created_at?->format('M d, Y h:i A') }}</p>
                        @if($report->reviewed_at)
                            <p>Opened for review {{ $report->reviewed_at->format('M d, Y h:i A') }}{{ $reviewedBy ? ' by ' . $reviewedBy->name : '' }}</p>
                        @endif
                        @if($report->resolved_at)
                            <p>Closed {{ $report->resolved_at->format('M d, Y h:i A') }}{{ $resolvedBy ? ' by ' . $resolvedBy->name : '' }}</p>
                        @endif
                    </div>
                </div>
            @elseif($report->reviewed_at)
                <div class="p-4 rounded-2xl bg-blue-50 border border-blue-200 text-blue-900 text-xs font-semibold">
                    Opened for review {{ $report->reviewed_at->format('M d, Y h:i A') }}{{ $reviewedBy ? ' by ' . $reviewedBy->name : '' }}.
                </div>
            @endif

            <div class="bg-surface rounded-3xl border border-border-subtle p-6 shadow-xs space-y-4 text-xs">
                <div class="flex items-center justify-between gap-3">
                    <h3 class="text-sm font-bold text-primary-dark">Other Reports Against This Seller</h3>
                    <span class="text-text-muted">{{ number_format($sellerReportTotal) }} in total &bull; {{ number_format($sellerUniqueReporters) }} different {{ \Illuminate\Support\Str::plural('buyer', $sellerUniqueReporters) }}</span>
                </div>

                @if($otherReports->isEmpty())
                    <p class="text-text-muted">This is the only report against this seller so far.</p>
                @else
                    <div class="divide-y divide-border-subtle">
                        @foreach($otherReports as $other)
                            @php
                                $otherBadge = match ($other->status) {
                                    \App\Models\SellerReport::STATUS_RESOLVED     => 'bg-green-100 text-green-700 border-green-200',
                                    \App\Models\SellerReport::STATUS_UNDER_REVIEW => 'bg-blue-100 text-blue-700 border-blue-200',
                                    \App\Models\SellerReport::STATUS_DISMISSED    => 'bg-gray-100 text-gray-700 border-gray-200',
                                    default                                       => 'bg-amber-100 text-amber-800 border-amber-200',
                                };
                            @endphp
                            <div class="py-3 flex flex-wrap items-center justify-between gap-2">
                                <div class="min-w-0">
                                    <a href="{{ route('admin.seller-reports.show', $other->id) }}" class="font-mono font-bold text-primary-dark hover:underline">{{ $other->report_number }}</a>
                                    <span class="text-text-muted"> &bull; {{ $other->category_label }}</span>
                                    <div class="text-text-muted">
                                        {{ $other->reporter?->full_name ?? 'Buyer' }} &bull; {{ $other->created_at?->format('M d, Y') }}
                                    </div>
                                </div>
                                <span class="inline-flex px-2.5 py-1 rounded-full text-[11px] font-bold border {{ $otherBadge }}">{{ $other->status_label }}</span>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>

        <!-- Right: who is involved -->
        <div class="lg:col-span-5 space-y-6">

            <div class="bg-surface rounded-3xl border border-border-subtle p-6 shadow-xs space-y-3 text-xs">
                <div class="flex items-center justify-between gap-3">
                    <h3 class="text-sm font-bold text-primary-dark">Reported Seller</h3>
                    <span class="inline-flex px-2.5 py-1 rounded-full text-[11px] font-bold border {{ $sellerStatusClass }}">{{ $sellerStatusLabel }}</span>
                </div>

                <div>
                    <p class="text-base font-bold text-text-main">{{ $storeName }}</p>
                    @if($sellerProfile?->line_of_business)
                        <p class="text-text-muted">{{ $sellerProfile->line_of_business }}</p>
                    @endif
                </div>

                @if($sellerUser)
                    <div class="space-y-1">
                        <div class="flex justify-between gap-3"><span class="text-text-muted">Owner</span><span class="font-bold text-text-main text-right">{{ $sellerUser->full_name }}</span></div>
                        <div class="flex justify-between gap-3"><span class="text-text-muted">Email</span><span class="font-bold text-text-main text-right select-text">{{ $sellerUser->email }}</span></div>
                        @if($sellerProfile?->full_address)
                            <div class="flex justify-between gap-3"><span class="text-text-muted">Location</span><span class="font-bold text-text-main text-right">{{ $sellerProfile->municipality }}, {{ $sellerProfile->province }}</span></div>
                        @endif
                    </div>
                @else
                    <p class="text-text-muted">The seller account no longer exists, so no action can be taken. You can only dismiss this report.</p>
                @endif

                @if($sanction && $sanction->last_abandonment_reason && $sellerIsBlocked)
                    <div class="p-3 rounded-xl bg-red-50 border border-red-200 text-red-900 leading-relaxed">
                        <span class="font-bold block">Current suspension reason</span>
                        {{ $sanction->last_abandonment_reason }}
                    </div>
                @endif

                @if($sellerUser)
                    <div class="pt-3 border-t border-border-subtle">
                        <a href="{{ route('admin.accounts.index', ['role' => 'Seller', 'search' => $sellerProfile?->business_name]) }}"
                           class="inline-flex items-center px-4 py-2 rounded-xl bg-surface border border-border-subtle text-primary-dark hover:border-primary text-xs font-bold transition-colors">
                            Open in Accounts &rarr;
                        </a>
                    </div>
                @endif
            </div>

            <div class="bg-surface rounded-3xl border border-border-subtle p-6 shadow-xs space-y-3 text-xs">
                <h3 class="text-sm font-bold text-primary-dark">Reported By</h3>
                <div>
                    <p class="text-base font-bold text-text-main">{{ $reporter?->full_name ?? 'Buyer' }}</p>
                    <p class="text-text-muted select-text">{{ $reporter?->email }}</p>
                </div>
                <div class="p-3 rounded-xl bg-surface-subtle border border-border-subtle text-text-muted leading-relaxed">
                    This buyer has sent <strong class="text-text-main">{{ number_format($reporterTotal) }}</strong>
                    {{ \Illuminate\Support\Str::plural('report', $reporterTotal) }} in total,
                    <strong class="{{ $reporterDismissed >= 3 ? 'text-danger' : 'text-text-main' }}">{{ number_format($reporterDismissed) }}</strong> of them dismissed.
                </div>
            </div>
        </div>
    </div>

    <!-- Evidence lightbox -->
    <template x-teleport="body">
        <div x-show="photoOpen"
             x-cloak
             @keydown.escape.window="photoOpen = false"
             class="fixed inset-0 z-[195] flex items-center justify-center p-4">
            <div @click="photoOpen = false" class="absolute inset-0 bg-black/75 backdrop-blur-xs cursor-pointer"></div>
            <div @click.stop class="relative z-10 bg-surface rounded-3xl max-w-lg w-full p-6 shadow-2xl border border-border-subtle space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-border-subtle">
                    <h3 class="text-base font-bold text-primary-dark">Report Evidence</h3>
                    <button type="button" @click="photoOpen = false" class="p-1 text-text-muted hover:text-text-main cursor-pointer">&times;</button>
                </div>
                <div class="rounded-2xl overflow-hidden bg-surface-subtle border border-border-subtle flex items-center justify-center max-h-[65vh]">
                    <img :src="photoUrl" alt="Report evidence" class="max-h-[60vh] w-auto object-contain">
                </div>
            </div>
        </div>
    </template>
</div>
@endsection