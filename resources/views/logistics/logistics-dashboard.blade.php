@extends('layouts.logistics', ['title' => 'Storkia - Logistics Dashboard'])

@section('content')
@php
    $period = $period ?? request()->query('period', '7d');
    $offset = isset($offset) ? max(0, (int) $offset) : max(0, (int) request()->query('offset', 0));
    $periodLabel = $periodLabel ?? 'Last 7 Days';
    $dateRangeLabel = $dateRangeLabel ?? now()->subDays(6)->format('M d, Y') . ' – ' . now()->format('M d, Y');
    $maxDate = $maxDate ?? now()->toDateString();
    $refDate = $refDate ?? $maxDate;
    $canGoNext = $canGoNext ?? ($offset > 0);
    $chartSeries = $chartSeries ?? [];
@endphp

<div x-data="{
        activeMetric: 'revenue',
        hoverIndex: null,
        leaveTimer: null,
        series: @js($chartSeries),
        metricMeta: {
            revenue: {
                label: 'Hub Shipping Revenue Share (90% of Shipping Fees)',
                unitLabel: 'PHP (\u20B1)',
                prefix: '\u20B1',
                decimals: 2,
                color: '#CF4173'
            },
            gross_shipping: {
                label: 'Gross Shipping Fee Volume (100% Hub + Rider)',
                unitLabel: 'PHP (\u20B1)',
                prefix: '\u20B1',
                decimals: 2,
                color: '#0D9488'
            },
            shipments: {
                label: 'Dispatched Hub Shipments Volume',
                unitLabel: 'Shipments',
                prefix: '',
                decimals: 0,
                color: '#5D3140'
            }
        },
        setHover(idx) {
            if (this.leaveTimer) {
                clearTimeout(this.leaveTimer);
                this.leaveTimer = null;
            }
            this.hoverIndex = idx;
        },
        clearHover() {
            if (this.leaveTimer) clearTimeout(this.leaveTimer);
            this.leaveTimer = setTimeout(() => {
                this.hoverIndex = null;
            }, 90);
        },
        onGraphMouseMove(event) {
            const pts = this.getCoordinates();
            if (!pts.length) return;
            const svg = event.currentTarget;
            const rect = svg.getBoundingClientRect();
            const relX = ((event.clientX - rect.left) / Math.max(1, rect.width)) * 760;
            let closestIdx = 0;
            let minDiff = Infinity;
            pts.forEach((pt, idx) => {
                const diff = Math.abs(pt.x - relX);
                if (diff < minDiff) {
                    minDiff = diff;
                    closestIdx = idx;
                }
            });
            this.setHover(closestIdx);
        },
        get values() {
            return this.series.map(s => Number(s[this.activeMetric] || 0));
        },
        get maxVal() {
            const m = Math.max(...this.values, 0);
            return m > 0 ? m * 1.18 : 100;
        },
        get totalMetricValue() {
            return this.values.reduce((acc, v) => acc + v, 0);
        },
        get avgMetricValue() {
            return this.series.length > 0 ? (this.totalMetricValue / this.series.length) : 0;
        },
        get peakBucket() {
            if (!this.series.length) return { label: 'N/A', full_date: 'N/A', val: 0 };
            let best = this.series[0];
            let bestVal = Number(best[this.activeMetric] || 0);
            this.series.forEach(item => {
                const v = Number(item[this.activeMetric] || 0);
                if (v > bestVal) {
                    best = item;
                    bestVal = v;
                }
            });
            return { label: best.label, full_date: best.full_date || best.label, val: bestVal };
        },
        get totalHubRevenue90() {
            return this.series.reduce((sum, s) => sum + Number(s.revenue || 0), 0);
        },
        get totalGrossShipping() {
            return this.series.reduce((sum, s) => sum + Number(s.gross_shipping || 0), 0);
        },
        get totalShipmentsCount() {
            return this.series.reduce((sum, s) => sum + Number(s.shipments || 0), 0);
        },
        formatMetric(val, metricKey = null) {
            const key = metricKey || this.activeMetric;
            const meta = this.metricMeta[key] || this.metricMeta.revenue;
            return meta.prefix + Number(val || 0).toLocaleString('en-US', {
                minimumFractionDigits: meta.decimals,
                maximumFractionDigits: meta.decimals
            });
        },
        yAxisTicks() {
            const max = this.maxVal;
            const rows = [
                { y: 28, val: max },
                { y: 68, val: max * 0.75 },
                { y: 108, val: max * 0.50 },
                { y: 148, val: max * 0.25 },
                { y: 189, val: 0 }
            ];
            return rows.map(r => ({
                y: r.y,
                text: this.formatMetric(r.val)
            }));
        },
        shouldShowXLabel(idx) {
            const len = this.series.length;
            if (len <= 12) return true;
            if (len === 24) return idx === 0 || idx === 23 || idx % 3 === 0;
            return idx === 0 || idx === len - 1 || idx % Math.ceil(len / 8) === 0;
        },
        getCoordinates() {
            const n = this.series.length;
            if (!n) return [];
            const width = 760, height = 225, padLeft = 72, padRight = 24, padTop = 28, padBottom = 36;
            const usableW = width - padLeft - padRight;
            const usableH = height - padTop - padBottom;
            const max = this.maxVal;
            return this.series.map((item, idx) => {
                const val = Number(item[this.activeMetric] || 0);
                const x = n === 1 ? (padLeft + usableW / 2) : padLeft + (idx / (n - 1)) * usableW;
                const y = padTop + usableH - ((val / max) * usableH);
                return {
                    x: Math.round(x * 10) / 10,
                    y: Math.round(y * 10) / 10,
                    val: val,
                    formatted: this.formatMetric(val),
                    label: item.label,
                    full_date: item.full_date || item.label,
                    raw: item
                };
            });
        },
        getLinePath() {
            const pts = this.getCoordinates();
            if (!pts.length) return '';
            if (pts.length === 1) return `M 72 ${pts[0].y} L 736 ${pts[0].y}`;
            let d = `M ${pts[0].x} ${pts[0].y}`;
            for (let i = 0; i < pts.length - 1; i++) {
                const p0 = pts[i], p1 = pts[i + 1];
                const midX = (p0.x + p1.x) / 2;
                d += ` C ${midX} ${p0.y}, ${midX} ${p1.y}, ${p1.x} ${p1.y}`;
            }
            return d;
        },
        getAreaPath() {
            const pts = this.getCoordinates();
            if (!pts.length) return '';
            const base = 189;
            if (pts.length === 1) return `M 72 ${pts[0].y} L 736 ${pts[0].y} L 736 ${base} L 72 ${base} Z`;
            return `${this.getLinePath()} L ${pts[pts.length - 1].x} ${base} L ${pts[0].x} ${base} Z`;
        }
     }"
     class="max-w-7xl mx-auto space-y-8 select-none">

    @if($latestApp && $latestApp->status === 'pending')
        <div class="p-6 bg-yellow-50 border border-yellow-200 rounded-3xl shadow-xs">
            <h3 class="text-base font-bold text-yellow-900">Hub Application Under Review</h3>
            <p class="text-xs sm:text-sm text-yellow-800 mt-1">
                Your application for <strong>{{ $latestApp->business_name }}</strong> (revision v{{ $latestApp->version }}) is pending administrative approval. Dispatch and operational routing will unlock once verified.
            </p>
        </div>
    @elseif($latestApp && $latestApp->status === 'rejected')
        <div class="p-6 bg-red-50 border border-red-200 rounded-3xl shadow-xs flex justify-between items-center">
            <div>
                <h3 class="text-base font-bold text-red-900">Application Rejected</h3>
                <p class="text-xs sm:text-sm text-red-800 mt-1">Reason: <em>"{{ $latestApp->rejection_reason }}"</em></p>
            </div>
            <a href="{{ route('logistics.reapply') }}" class="px-5 py-2.5 bg-primary text-white text-xs font-bold rounded-xl shadow-md">
                Update &amp; Re-apply
            </a>
        </div>
    @endif

    <div class="flex flex-col xl:flex-row xl:items-end justify-between gap-4">
        <div>
            <div class="flex flex-wrap items-center gap-2.5">
                <h1 class="text-3xl font-bold text-primary-dark">Logistics Operations</h1>
                @if($profile)
                    <span class="px-3 py-1 rounded-full text-xs font-bold bg-brand-light/50 text-primary-dark border border-border-subtle font-mono">
                        {{ $dateRangeLabel }}
                    </span>
                @endif
            </div>
            <p class="text-primary mt-1 font-medium text-sm">
                Hub Manager: {{ auth()->user()->first_name ?? 'User' }} &bull;
                {{ auth()->user()->activeLogisticsHub()?->hub_name ?? ($profile->business_name ?? '') }} ({{ $periodLabel }})
            </p>
        </div>

        @if($profile)
            <div class="flex flex-wrap items-center gap-2">
                <div class="inline-flex items-center gap-1 p-1.5 rounded-2xl bg-surface border border-border-subtle shadow-2xs">
                    @foreach([
                        'today' => 'Today (12AM–11PM)',
                        '7d'    => '7 Days',
                        '30d'   => '30 Days',
                        'year'  => 'Year',
                    ] as $pKey => $pText)
                        <a href="{{ route('logistics.logistics-dashboard', ['period' => $pKey]) }}"
                           class="px-3 py-2 rounded-xl text-xs font-bold transition-all {{ $period === $pKey ? 'bg-primary-dark text-white shadow-xs' : 'text-text-muted hover:text-text-main hover:bg-surface-subtle' }}">
                            {{ $pText }}
                        </a>
                    @endforeach
                </div>

                <div class="inline-flex items-center gap-1.5 p-1.5 rounded-2xl bg-surface border border-border-subtle shadow-2xs text-xs">
                    <a href="{{ route('logistics.logistics-dashboard', ['period' => $period, 'offset' => $offset + 1]) }}"
                       title="Browse previous {{ $period === 'today' ? 'day' : ($period === 'year' ? 'year' : 'period') }}"
                       class="px-3 py-1.5 rounded-xl bg-surface-subtle hover:bg-brand-light/40 text-text-main font-bold transition-colors">
                        &larr; Prev
                    </a>

                    <form method="GET" action="{{ route('logistics.logistics-dashboard') }}" class="inline-flex items-center m-0">
                        <input type="hidden" name="period" value="{{ $period }}">
                        <input type="date"
                               name="ref_date"
                               value="{{ $refDate }}"
                               max="{{ $maxDate }}"
                               onchange="this.form.submit()"
                               title="Jump to any past date (future dates disabled)"
                               class="px-2.5 py-1 rounded-xl border border-border-subtle bg-surface text-xs font-mono font-bold text-primary-dark outline-none focus:border-primary cursor-pointer">
                    </form>

                    @if($canGoNext)
                        <a href="{{ route('logistics.logistics-dashboard', ['period' => $period, 'offset' => max(0, $offset - 1)]) }}"
                           class="px-3 py-1.5 rounded-xl bg-surface-subtle hover:bg-brand-light/40 text-text-main font-bold transition-colors">
                            Next &rarr;
                        </a>
                        <a href="{{ route('logistics.logistics-dashboard', ['period' => $period]) }}"
                           class="px-2.5 py-1.5 rounded-xl bg-primary/10 text-primary hover:bg-primary hover:text-white font-bold transition-colors">
                            Current
                        </a>
                    @else
                        <span class="px-3 py-1.5 rounded-xl bg-gray-100 text-gray-400 font-bold cursor-not-allowed" title="Future dates are disabled">
                            Next &rarr;
                        </span>
                    @endif
                </div>

                <a href="{{ route('logistics.finance.index') }}"
                   class="px-4 py-2.5 rounded-2xl bg-primary hover:bg-primary-dark text-white text-xs font-bold shadow-xs transition-colors">
                    E-Wallet &amp; Reports
                </a>
            </div>
        @endif
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-4">
        <div class="p-5 bg-gradient-to-br from-primary-dark to-primary text-white rounded-2xl shadow-sm">
            <h3 class="text-[11px] font-bold uppercase text-white/80">Real-Money E-Wallet (90%)</h3>
            <p class="text-2xl font-serif font-extrabold mt-1.5">&#8369;{{ number_format($walletAvailable ?? 0, 2) }}</p>
            <span class="text-[10px] text-white/85 mt-1 block">10% Comm + 80% Ops Released</span>
        </div>

        <div class="p-5 bg-surface rounded-2xl shadow-sm border border-border-subtle">
            <h3 class="text-[11px] font-bold uppercase text-text-muted">Pending Escrow (Delivered)</h3>
            <p class="text-2xl font-serif font-bold text-amber-600 mt-1.5">&#8369;{{ number_format($walletPendingEscrow ?? 0, 2) }}</p>
            <span class="text-[10px] text-text-muted mt-1 block">Awaiting Buyer Confirmation</span>
        </div>

        <a href="{{ $profile ? route('logistics.shipments.index', ['status' => 'to_pickup']) : '#' }}"
           class="p-5 bg-surface rounded-2xl shadow-sm border border-border-subtle hover:border-primary/50 transition-all">
            <h3 class="text-[11px] font-bold uppercase text-text-muted">Parcels to Pick Up</h3>
            <p class="text-2xl font-serif font-bold text-primary-dark mt-1.5">{{ $pendingPickupsCount ?? 0 }}</p>
            <span class="text-[11px] text-primary font-semibold mt-1 inline-block">Assign Courier &rarr;</span>
        </a>

        <a href="{{ $profile ? route('logistics.shipments.index', ['status' => 'at_hub']) : '#' }}"
           class="p-5 bg-surface rounded-2xl shadow-sm border border-border-subtle hover:border-primary/50 transition-all">
            <h3 class="text-[11px] font-bold uppercase text-text-muted">Sorting Hub Queue</h3>
            <p class="text-2xl font-serif font-bold text-primary-dark mt-1.5">{{ $atHubCount ?? 0 }}</p>
            <span class="text-[11px] text-primary font-semibold mt-1 inline-block">Verify &amp; Dispatch &rarr;</span>
        </a>

        <a href="{{ $profile ? route('logistics.shipments.index', ['status' => 'out_for_delivery']) : '#' }}"
           class="p-5 bg-surface rounded-2xl shadow-sm border border-border-subtle hover:border-primary/50 transition-all">
            <h3 class="text-[11px] font-bold uppercase text-text-muted">Out for Delivery</h3>
            <p class="text-2xl font-serif font-bold text-blue-700 mt-1.5">{{ $inTransitCount ?? 0 }}</p>
            <span class="text-[11px] text-text-muted mt-1 inline-block">Failed/RTS: {{ $failedDeliveriesCount ?? 0 }}</span>
        </a>

        <a href="{{ $profile ? route('logistics.finance.index') : '#' }}"
           class="p-5 bg-surface rounded-2xl shadow-sm border border-border-subtle hover:border-primary/50 transition-all">
            <h3 class="text-[11px] font-bold uppercase text-text-muted">Pending Remittances</h3>
            <p class="text-2xl font-serif font-bold text-green-700 mt-1.5">{{ $pendingRemittancesCount ?? 0 }}</p>
            <span class="text-[11px] text-primary font-semibold mt-1 inline-block">Verify COD &rarr;</span>
        </a>
    </div>

    @if($profile)
        <div class="bg-surface rounded-3xl border border-border-subtle p-6 sm:p-7 shadow-xs space-y-5">
            <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-3 border-b border-border-subtle pb-4">
                <div>
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="text-[10px] font-extrabold uppercase tracking-wider text-primary">
                            Sorting Hub Statistical Graph ({{ $periodLabel }})
                        </span>
                        <span class="px-2.5 py-0.5 rounded-full bg-surface-subtle border border-border-subtle text-[11px] font-mono font-bold text-primary-dark">
                            {{ $dateRangeLabel }}
                        </span>
                    </div>
                    <h2 class="text-lg font-bold text-primary-dark mt-1" x-text="metricMeta[activeMetric].label"></h2>
                    <p class="text-xs text-text-muted mt-0.5">
                        Measuring: <strong class="text-text-main" x-text="metricMeta[activeMetric].unitLabel"></strong> across <strong class="text-text-main" x-text="series.length"></strong> time intervals
                    </p>
                </div>

                <div class="inline-flex flex-wrap items-center gap-1.5 bg-surface-subtle p-1.5 rounded-2xl border border-border-subtle text-xs shrink-0">
                    <button type="button"
                            @click="activeMetric = 'revenue'"
                            :class="activeMetric === 'revenue' ? 'bg-primary text-white font-bold shadow-2xs' : 'text-text-muted hover:text-text-main font-semibold'"
                            class="px-3.5 py-1.5 rounded-xl transition-all cursor-pointer">
                        Hub 90% Revenue (&#8369;)
                    </button>
                    <button type="button"
                            @click="activeMetric = 'gross_shipping'"
                            :class="activeMetric === 'gross_shipping' ? 'bg-teal-600 text-white font-bold shadow-2xs' : 'text-text-muted hover:text-text-main font-semibold'"
                            class="px-3.5 py-1.5 rounded-xl transition-all cursor-pointer">
                        Gross Shipping (&#8369;)
                    </button>
                    <button type="button"
                            @click="activeMetric = 'shipments'"
                            :class="activeMetric === 'shipments' ? 'bg-primary-dark text-white font-bold shadow-2xs' : 'text-text-muted hover:text-text-main font-semibold'"
                            class="px-3.5 py-1.5 rounded-xl transition-all cursor-pointer">
                        Shipments Volume
                    </button>
                </div>
            </div>

            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                <div class="p-3.5 rounded-2xl border transition-all cursor-pointer"
                     :class="activeMetric === 'revenue' ? 'bg-primary/5 border-primary/40' : 'bg-surface-subtle/60 border-border-subtle'"
                     @click="activeMetric = 'revenue'">
                    <span class="text-[10px] font-bold uppercase text-text-muted block">Hub 90% Revenue</span>
                    <strong class="text-base sm:text-lg font-serif font-extrabold text-primary block mt-0.5"
                            x-text="formatMetric(totalHubRevenue90, 'revenue')"></strong>
                    <span class="text-[10px] text-text-muted font-mono">{{ $dateRangeLabel }}</span>
                </div>
                <div class="p-3.5 rounded-2xl border transition-all cursor-pointer"
                     :class="activeMetric === 'gross_shipping' ? 'bg-teal-50 border-teal-500/40' : 'bg-surface-subtle/60 border-border-subtle'"
                     @click="activeMetric = 'gross_shipping'">
                    <span class="text-[10px] font-bold uppercase text-text-muted block">Gross Shipping Fees</span>
                    <strong class="text-base sm:text-lg font-serif font-extrabold text-teal-700 block mt-0.5"
                            x-text="formatMetric(totalGrossShipping, 'gross_shipping')"></strong>
                    <span class="text-[10px] text-text-muted">90% Hub / 10% Rider</span>
                </div>
                <div class="p-3.5 rounded-2xl border transition-all cursor-pointer"
                     :class="activeMetric === 'shipments' ? 'bg-brand-light/40 border-primary-dark/40' : 'bg-surface-subtle/60 border-border-subtle'"
                     @click="activeMetric = 'shipments'">
                    <span class="text-[10px] font-bold uppercase text-text-muted block">Dispatched Shipments</span>
                    <strong class="text-base sm:text-lg font-serif font-extrabold text-primary-dark block mt-0.5"
                            x-text="totalShipmentsCount.toLocaleString() + ' Parcel(s)'"></strong>
                    <span class="text-[10px] text-text-muted" x-text="'Avg: ' + formatMetric(avgMetricValue) + ' / slot'"></span>
                </div>
                <div class="p-3.5 rounded-2xl bg-surface-subtle/60 border border-border-subtle">
                    <span class="text-[10px] font-bold uppercase text-text-muted block">Peak Dispatch Interval</span>
                    <strong class="text-base sm:text-lg font-serif font-extrabold text-text-main block mt-0.5"
                            x-text="formatMetric(peakBucket.val)"></strong>
                    <span class="text-[10px] text-primary font-bold block truncate" x-text="'Date: ' + peakBucket.label"></span>
                </div>
            </div>

            <div class="relative w-full pt-2">
                <div x-show="hoverIndex !== null && getCoordinates()[hoverIndex]"
                     x-cloak
                     class="pointer-events-none absolute top-0 z-20 -translate-x-1/2 px-3 py-1.5 rounded-xl bg-primary-dark/95 text-white text-[11px] font-mono shadow-lg whitespace-nowrap border border-white/15 transition-all duration-75"
                     :style="hoverIndex !== null && getCoordinates()[hoverIndex] ? `left: clamp(14%, ${(getCoordinates()[hoverIndex].x / 760) * 100}%, 86%);` : ''">
                    <span class="font-sans font-extrabold text-brand-light" x-text="getCoordinates()[hoverIndex]?.full_date"></span>
                    <span class="mx-1.5 text-white/40">|</span>
                    <span class="font-extrabold" x-text="getCoordinates()[hoverIndex]?.formatted"></span>
                </div>

                <svg viewBox="0 0 760 225"
                     @mousemove="onGraphMouseMove($event)"
                     @mouseleave="clearHover()"
                     class="w-full h-60 overflow-visible cursor-crosshair">
                    <defs>
                        <linearGradient id="storkiaLogisticsAreaGrad" x1="0" y1="0" x2="0" y2="1">
                            <stop offset="0%" :stop-color="metricMeta[activeMetric].color" stop-opacity="0.42" />
                            <stop offset="70%" :stop-color="metricMeta[activeMetric].color" stop-opacity="0.08" />
                            <stop offset="100%" :stop-color="metricMeta[activeMetric].color" stop-opacity="0.0" />
                        </linearGradient>
                    </defs>

                    <template x-for="(tick, tIdx) in yAxisTicks()" :key="'log_tick_' + tIdx">
                        <g>
                            <line x1="72" :y1="tick.y" x2="736" :y2="tick.y" stroke="#E7E5E4" stroke-dasharray="4 4" stroke-width="1" />
                            <text x="66" :y="tick.y + 3" text-anchor="end" class="text-[9px] fill-stone-500 font-mono font-bold" x-text="tick.text"></text>
                        </g>
                    </template>

                    <path :d="getAreaPath()" fill="url(#storkiaLogisticsAreaGrad)" class="transition-all duration-300 pointer-events-none" />
                    <path :d="getLinePath()" fill="none" :stroke="metricMeta[activeMetric].color" stroke-width="3" stroke-linecap="round" class="transition-all duration-300 pointer-events-none" />

                    <template x-for="(pt, idx) in getCoordinates()" :key="idx">
                        <g class="pointer-events-none">
                            <line x-show="hoverIndex === idx"
                                  :x1="pt.x" y1="20"
                                  :x2="pt.x" y2="189"
                                  :stroke="metricMeta[activeMetric].color"
                                  stroke-dasharray="3 3"
                                  stroke-width="1.5" />

                            <circle :cx="pt.x"
                                    :cy="pt.y"
                                    :r="hoverIndex === idx ? 6.5 : 3.5"
                                    :fill="hoverIndex === idx ? metricMeta[activeMetric].color : '#FFF'"
                                    :stroke="metricMeta[activeMetric].color"
                                    stroke-width="2.5"
                                    class="transition-all duration-100" />

                            <text x-show="pt.val > 0 && (series.length <= 12 || hoverIndex === idx)"
                                  :x="pt.x"
                                  :y="Math.max(18, pt.y - 10)"
                                  text-anchor="middle"
                                  class="text-[9px] font-mono font-extrabold fill-stone-800"
                                  x-text="pt.formatted"></text>

                            <text x-show="shouldShowXLabel(idx)"
                                  :x="pt.x"
                                  y="212"
                                  text-anchor="middle"
                                  class="text-[10px] fill-stone-600 font-bold"
                                  x-text="pt.label"></text>
                        </g>
                    </template>
                </svg>

                <div class="mt-2 h-11 px-4 rounded-2xl transition-colors duration-150 flex items-center justify-between gap-2 text-xs shadow-xs"
                     :class="hoverIndex !== null && getCoordinates()[hoverIndex] ? 'bg-primary-dark text-white' : 'bg-surface-subtle border border-border-subtle text-text-main'">
                    <template x-if="hoverIndex !== null && getCoordinates()[hoverIndex]">
                        <div class="w-full flex flex-wrap items-center justify-between gap-2 font-mono">
                            <span class="font-sans font-bold" x-text="'Scope: ' + getCoordinates()[hoverIndex].full_date"></span>
                            <div class="flex flex-wrap items-center gap-4">
                                <span x-text="'Hub 90% Share: \u20B1' + Number(getCoordinates()[hoverIndex].raw.revenue || 0).toLocaleString('en-US', {minimumFractionDigits: 2})"></span>
                                <span x-text="'Gross Ship: \u20B1' + Number(getCoordinates()[hoverIndex].raw.gross_shipping || 0).toLocaleString('en-US', {minimumFractionDigits: 2})"></span>
                                <span x-text="'Shipments: ' + Number(getCoordinates()[hoverIndex].raw.shipments || 0)"></span>
                            </div>
                        </div>
                    </template>
                    <template x-if="hoverIndex === null || !getCoordinates()[hoverIndex]">
                        <div class="w-full flex items-center justify-between gap-2 text-text-muted">
                            <span class="font-semibold">Hover over the graph line or any date card below to inspect exact interval numbers.</span>
                            <span class="font-mono font-bold text-primary-dark hidden sm:inline">{{ $dateRangeLabel }}</span>
                        </div>
                    </template>
                </div>
            </div>

            <div class="pt-3 border-t border-border-subtle" @mouseleave="clearHover()">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-[11px] font-extrabold uppercase tracking-wider text-text-muted">Date &amp; Statistical Numbers Breakdown</span>
                    <span class="text-[11px] text-text-muted font-mono">{{ $dateRangeLabel }}</span>
                </div>
                <div class="grid grid-cols-2 sm:grid-cols-4 md:grid-cols-6 lg:grid-cols-7 gap-2 max-h-48 overflow-y-auto custom-scrollbar pr-1">
                    <template x-for="(row, rIdx) in series" :key="'log_stat_' + rIdx">
                        <div @mouseenter="setHover(rIdx)"
                             :class="hoverIndex === rIdx ? 'border-primary bg-primary/5 ring-1 ring-primary/30' : 'border-border-subtle bg-surface-subtle/50 hover:border-primary/40'"
                             class="p-2.5 rounded-xl border text-xs transition-colors cursor-pointer">
                            <div class="font-bold text-primary-dark truncate" :title="row.full_date" x-text="row.label"></div>
                            <div class="font-mono font-extrabold text-primary text-[11px] mt-1"
                                 x-text="formatMetric(row[activeMetric])"></div>
                            <div class="text-[10px] text-text-muted font-mono"
                                 x-text="Number(row.shipments || 0) + ' parcel(s)'"></div>
                        </div>
                    </template>
                </div>
            </div>
        </div>
    @endif

    @if($profile && isset($recentShipments) && $recentShipments->isNotEmpty())
        <div class="bg-surface rounded-3xl border border-border-subtle shadow-xs overflow-hidden">
            <div class="p-5 border-b border-border-subtle flex flex-wrap items-center justify-between gap-2">
                <div>
                    <h2 class="text-base font-bold text-primary-dark">Assigned Hub Shipments Queue (Active FIFO at Top &bull; Delivered/Returned at Bottom — {{ $totalShipmentsCount ?? $recentShipments->count() }} Total)</h2>
                    <p class="text-xs text-text-muted">Active shipments are prioritized at the top in FIFO sequence; delivered or returned shipments automatically move to the bottom.</p>
                </div>
                <a href="{{ route('logistics.shipments.index') }}" class="text-xs font-bold text-primary hover:underline">Open Full Shipments Console &rarr;</a>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-xs">
                    <thead>
                        <tr class="border-b border-border-subtle bg-surface-subtle/50 text-text-muted uppercase text-[11px] font-bold">
                            <th class="py-3 px-5">Tracking #</th>
                            <th class="py-3 px-4">Seller Store</th>
                            <th class="py-3 px-4">Courier / Rider</th>
                            <th class="py-3 px-4">COD Amount</th>
                            <th class="py-3 px-4">Status</th>
                            <th class="py-3 px-5 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border-subtle">
                        @foreach($recentShipments as $sh)
                            @php
                                $isTerminal = method_exists($sh, 'isTerminalStatus')
                                    ? $sh->isTerminalStatus()
                                    : in_array($sh->status, ['delivered', 'returned_to_seller'], true);
                            @endphp
                            <tr class="hover:bg-surface-subtle/30 {{ $isTerminal ? 'bg-surface-subtle/20 opacity-85' : '' }}">
                                <td class="py-3.5 px-5">
                                    <div class="flex items-center gap-2">
                                        <span class="font-mono font-bold text-primary-dark">{{ $sh->tracking_number }}</span>
                                        @if(! $isTerminal)
                                            <span class="px-2 py-0.5 rounded-full bg-primary/10 text-primary border border-primary/20 text-[10px] font-extrabold">
                                                Active
                                            </span>
                                        @else
                                            <span class="px-2 py-0.5 rounded-full bg-gray-100 text-gray-600 border border-gray-200 text-[10px] font-bold">
                                                Closed
                                            </span>
                                        @endif
                                    </div>
                                    <div class="text-[11px] text-text-muted">
                                        {{ $isTerminal ? 'Closed: ' . ($sh->delivered_at ?? $sh->returned_at ?? $sh->updated_at)?->format('M d, Y h:i A') : 'Placed: ' . $sh->created_at?->format('M d, Y h:i A') }}
                                    </div>
                                </td>
                                <td class="py-3.5 px-4 font-semibold text-text-main">{{ $sh->sellerProfile->business_name ?? 'N/A' }}</td>
                                <td class="py-3.5 px-4 text-text-muted">
                                    C: {{ $sh->currentCourier?->full_name ?? '-' }} / R: {{ $sh->currentRider?->full_name ?? '-' }}
                                </td>
                                <td class="py-3.5 px-4 font-bold text-primary">&#8369;{{ number_format((float) $sh->cod_amount, 2) }}</td>
                                <td class="py-3.5 px-4">
                                    <span class="px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-brand-light/40 text-primary-dark">
                                        {{ $sh->status_label }}
                                    </span>
                                </td>
                                <td class="py-3.5 px-5 text-right">
                                    <a href="{{ route('logistics.shipments.show', $sh->id) }}" class="text-primary font-bold hover:underline">Manage</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if(method_exists($recentShipments, 'hasPages') && $recentShipments->hasPages())
                <div class="px-5 py-3 border-t border-border-subtle">
                    {{ $recentShipments->links() }}
                </div>
            @endif
        </div>
    @endif
</div>
@endsection