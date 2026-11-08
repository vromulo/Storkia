@extends('buyer.account-settings', [
    'pageTitle' => 'My Reports',
    'pageSubtitle' => 'Track the reports you have sent about sellers.',
    'breadcrumbSection' => 'My Reports',
])

@section('option-content')
@php
    $tabs = [
        'all'    => 'All',
        'open'   => 'Open',
        'closed' => 'Closed',
    ];

    // A notification can deep-link here with ?report=ID: that card is highlighted and scrolled into view.
    $highlightId = (int) request()->query('report', 0);
@endphp

<div class="space-y-6 select-none w-full min-w-0 max-w-full overflow-x-hidden">

    <!-- Filter tabs -->
    <div class="flex flex-wrap items-center gap-2">
        @foreach($tabs as $key => $label)
            <a href="{{ route('user.seller-reports', $key === 'all' ? [] : ['status' => $key]) }}"
               class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-bold transition-colors
                      {{ $status === $key ? 'bg-primary text-white' : 'bg-surface-subtle text-text-muted hover:text-primary' }}">
                {{ $label }}
                <span class="px-1.5 py-0.5 rounded-full text-[10px] {{ $status === $key ? 'bg-white/20 text-white' : 'bg-surface text-text-muted' }}">
                    {{ number_format($counts[$key] ?? 0) }}
                </span>
            </a>
        @endforeach
    </div>

    @if($reports->isEmpty())
        <div class="bg-surface rounded-3xl border border-border-subtle p-12 text-center space-y-3">
            @if(($counts['all'] ?? 0) === 0)
                <h3 class="text-base font-bold text-text-main">You have not reported any sellers</h3>
                <p class="text-xs text-text-muted max-w-md mx-auto">
                    If a seller sells fake items, scams buyers or behaves badly, you can report their store from their
                    store page, a product page or an order page. Your reports and their status will show up here.
                </p>
                <a href="{{ route('sellers.index') }}" class="inline-flex px-5 py-2.5 rounded-xl bg-primary text-white text-xs font-bold hover:bg-primary-dark transition-colors">
                    Browse Official Stores
                </a>
            @else
                <h3 class="text-base font-bold text-text-main">No {{ $status }} reports</h3>
                <p class="text-xs text-text-muted max-w-md mx-auto">There is nothing to show for this filter.</p>
                <a href="{{ route('user.seller-reports') }}" class="inline-flex px-5 py-2.5 rounded-xl bg-primary text-white text-xs font-bold hover:bg-primary-dark transition-colors">
                    Show all reports
                </a>
            @endif
        </div>
    @else
        <div class="space-y-4">
            @foreach($reports as $report)
                @php
                    $store  = $report->sellerProfile;
                    $photos = $report->evidence_urls;
                    $isHighlighted = $highlightId === (int) $report->id;
                    $isLong = mb_strlen($report->description) > 180;

                    $badge = match ($report->status) {
                        \App\Models\SellerReport::STATUS_RESOLVED     => 'bg-green-600 text-white',
                        \App\Models\SellerReport::STATUS_UNDER_REVIEW => 'bg-blue-600 text-white',
                        \App\Models\SellerReport::STATUS_DISMISSED    => 'bg-stone-500 text-white',
                        default                                       => 'bg-amber-500 text-white',
                    };

                    $noteBox = match ($report->status) {
                        \App\Models\SellerReport::STATUS_RESOLVED     => 'bg-green-50 border-green-200 text-green-800',
                        \App\Models\SellerReport::STATUS_UNDER_REVIEW => 'bg-blue-50 border-blue-200 text-blue-800',
                        \App\Models\SellerReport::STATUS_DISMISSED    => 'bg-stone-50 border-stone-200 text-stone-700',
                        default                                       => 'bg-amber-50 border-amber-200 text-amber-900',
                    };

                    // Deliberately generic: the buyer is told THAT action was taken, not what it was.
                    $statusNote = match ($report->status) {
                        \App\Models\SellerReport::STATUS_RESOLVED     => 'Storkia reviewed your report and took action on this store. Thank you for helping keep Storkia safe.',
                        \App\Models\SellerReport::STATUS_UNDER_REVIEW => 'Storkia is reviewing your report. This can take a little while.',
                        \App\Models\SellerReport::STATUS_DISMISSED    => 'Storkia reviewed your report and did not find a policy violation at this time. If you have new evidence, you are welcome to send a new report.',
                        default                                       => 'Your report is in the queue. Storkia will review it soon.',
                    };
                @endphp

                <article id="report-{{ $report->id }}"
                         x-data="{ expanded: false }"
                         @if($isHighlighted) x-init="$nextTick(() => $el.scrollIntoView({ behavior: 'smooth', block: 'center' }))" @endif
                         class="bg-surface rounded-3xl border shadow-xs overflow-hidden {{ $isHighlighted ? 'border-primary ring-2 ring-primary/30' : 'border-border-subtle' }}">

                    <!-- Header -->
                    <div class="px-5 py-3.5 border-b border-border-subtle flex flex-wrap items-center justify-between gap-2">
                        <div class="min-w-0">
                            <p class="text-[11px] text-text-muted">Report No.</p>
                            <p class="text-sm font-mono font-extrabold text-primary-dark select-text">{{ $report->report_number }}</p>
                        </div>
                        <div class="text-right">
                            <span class="px-2.5 py-1 rounded-full text-[10px] font-extrabold uppercase {{ $badge }}">{{ $report->status_label }}</span>
                            <p class="text-[11px] text-text-muted mt-1">Sent {{ $report->created_at->format('M d, Y \a\t h:i A') }}</p>
                        </div>
                    </div>

                    <div class="p-5 space-y-4">

                        <!-- Seller -->
                        <div class="flex items-center gap-3">
                            @if($store)
                                <a href="{{ route('sellers.show', $store->id) }}" class="w-12 h-12 rounded-full overflow-hidden border border-border-subtle bg-surface-subtle flex items-center justify-center shrink-0">
                                    @if($store->logo_path)
                                        <img src="{{ Storage::url($store->logo_path) }}" alt="{{ $store->business_name }}" class="w-full h-full object-cover">
                                    @else
                                        <span class="text-lg font-extrabold text-primary">{{ strtoupper(substr($store->business_name, 0, 1)) }}</span>
                                    @endif
                                </a>
                                <div class="min-w-0">
                                    <a href="{{ route('sellers.show', $store->id) }}" class="text-sm font-bold text-text-main hover:text-primary truncate block">
                                        {{ $store->business_name }}
                                    </a>
                                    <p class="text-[11px] text-text-muted truncate">{{ $store->line_of_business }} &bull; {{ $store->municipality }}, {{ $store->province }}</p>
                                </div>
                            @else
                                <div class="w-12 h-12 rounded-full border border-border-subtle bg-surface-subtle shrink-0"></div>
                                <p class="text-sm font-bold text-text-muted">This store is no longer available</p>
                            @endif
                        </div>

                        <!-- What was reported -->
                        <div class="flex flex-wrap items-center gap-2 text-[11px]">
                            <span class="px-2.5 py-1 rounded-full bg-danger/10 text-danger font-bold">{{ $report->category_label }}</span>
                            @if($report->sellerOrder)
                                <a href="{{ route('user.orders.show', $report->seller_order_id) }}"
                                   class="px-2.5 py-1 rounded-full bg-surface-subtle text-text-muted hover:text-primary font-bold transition-colors">
                                    Order #{{ $report->sellerOrder->seller_order_number }}
                                </a>
                            @endif
                        </div>

                        <div>
                            <p class="text-xs text-text-main leading-relaxed whitespace-pre-line break-words select-text"
                               :class="expanded ? '' : 'line-clamp-3'">{{ $report->description }}</p>
                            @if($isLong)
                                <button type="button" @click="expanded = !expanded"
                                        class="mt-1 text-[11px] font-bold text-primary hover:underline cursor-pointer"
                                        x-text="expanded ? 'Show less' : 'Show more'"></button>
                            @endif
                        </div>

                        @if(count($photos))
                            <div class="flex flex-wrap gap-2">
                                @foreach($photos as $photoUrl)
                                    <a href="{{ $photoUrl }}" target="_blank" rel="noopener"
                                       class="w-16 h-16 rounded-lg overflow-hidden border border-border-subtle bg-surface-subtle">
                                        <img src="{{ $photoUrl }}" alt="Evidence photo" loading="lazy" class="w-full h-full object-cover">
                                    </a>
                                @endforeach
                            </div>
                        @endif

                        <!-- Status -->
                        <div class="p-3.5 rounded-2xl border text-xs leading-relaxed {{ $noteBox }}">
                            <p>{{ $statusNote }}</p>
                            @if($report->isClosed() && $report->resolved_at)
                                <p class="mt-1 text-[11px] opacity-80">Closed on {{ $report->resolved_at->format('M d, Y') }}</p>
                            @endif
                        </div>
                    </div>
                </article>
            @endforeach
        </div>

        <div class="pt-2">
            {{ $reports->links() }}
        </div>
    @endif

</div>
@endsection