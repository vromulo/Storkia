@php
    $period = $period ?? request()->query('period', '7d');
    $offset = isset($offset) ? max(0, (int) $offset) : max(0, (int) request()->query('offset', 0));
    $refDateParam = $refDate ?? request()->query('ref_date', '');
    if (!isset($kpis) || !isset($chartSeries)) {
        $fallbackPayload = \App\Livewire\Admin\Dashboard::buildDashboardPayload($period, $offset, $refDateParam ?: null);
        $periodLabel = $periodLabel ?? $fallbackPayload['periodLabel'];
        $dateRangeLabel = $dateRangeLabel ?? $fallbackPayload['dateRangeLabel'];
        $refDate = $fallbackPayload['refDate'];
        $maxDate = $fallbackPayload['maxDate'];
        $canGoNext = $fallbackPayload['canGoNext'];
        $kpis = $kpis ?? $fallbackPayload['kpis'];
        $roleCounts = $roleCounts ?? $fallbackPayload['roleCounts'];
        $chartSeries = $chartSeries ?? $fallbackPayload['chartSeries'];
        $recentOrders = $recentOrders ?? $fallbackPayload['recentOrders'];
        $recentWithdrawals = $recentWithdrawals ?? $fallbackPayload['recentWithdrawals'];
    }
    $dateRangeLabel = $dateRangeLabel ?? now()->subDays(6)->format('M d, Y') . ' – ' . now()->format('M d, Y');
    $maxDate = $maxDate ?? now()->toDateString();
    $refDate = $refDate ?? $maxDate;
    $canGoNext = $canGoNext ?? ($offset > 0);
@endphp

<div x-data="{
        selectedPeriod: '{{ $period }}',
        activeMetric: '{{ $chartMetric ?? 'gmv' }}',
        hoverIndex: null,
        leaveTimer: null,
        series: @js($chartSeries),
        metricMeta: {
            gmv: { label: 'Gross Merchandise Volume (GMV)', unitLabel: 'PHP (\u20B1)', prefix: '\u20B1', decimals: 2, color: '#CF4173', stopColor: '#CF4173' },
            admin_commission: { label: 'Admin 10% Commission Revenue', unitLabel: 'PHP (\u20B1)', prefix: '\u20B1', decimals: 2, color: '#5D3140', stopColor: '#5D3140' },
            shipping: { label: 'Gross Shipping Fee Volume', unitLabel: 'PHP (\u20B1)', prefix: '\u20B1', decimals: 2, color: '#0D9488', stopColor: '#0D9488' },
            orders: { label: 'Total Orders Placed', unitLabel: 'Orders', prefix: '', decimals: 0, color: '#D97706', stopColor: '#D97706' }
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
        get currentValues() {
            return this.series.map(s => Number(s[this.activeMetric] || 0));
        },
        get maxVal() {
            const m = Math.max(...this.currentValues, 0);
            return m > 0 ? m * 1.18 : 100;
        },
        get periodTotal() {
            return this.currentValues.reduce((a, b) => a + b, 0);
        },
        get periodAverage() {
            return this.series.length > 0 ? (this.periodTotal / this.series.length) : 0;
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
        sumByKey(key) {
            return this.series.reduce((sum, s) => sum + Number(s[key] || 0), 0);
        },
        formatMetric(val, key = null) {
            const meta = this.metricMeta[key || this.activeMetric] || this.metricMeta.gmv;
            return meta.prefix + Number(val || 0).toLocaleString('en-US', {
                minimumFractionDigits: meta.decimals,
                maximumFractionDigits: meta.decimals
            });
        },
        yAxisTicks() {
            const max = this.maxVal;
            const rows = [
                { y: 28, val: max },
                { y: 70, val: max * 0.75 },
                { y: 112, val: max * 0.50 },
                { y: 152, val: max * 0.25 },
                { y: 192, val: 0 }
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
            if (n === 0) return [];
            const width = 760;
            const padLeft = 72;
            const padRight = 24;
            const padTop = 28;
            const padBottom = 33;
            const usableW = width - padLeft - padRight;
            const usableH = 225 - padTop - padBottom;
            const max = this.maxVal;

            return this.series.map((item, idx) => {
                const val = Number(item[this.activeMetric] || 0);
                const x = n === 1 ? (padLeft + usableW / 2) : padLeft + (idx / (n - 1)) * usableW;
                const y = padTop + usableH - ((val / max) * usableH);
                return {
                    x: Math.round(x * 10) / 10,
                    y: Math.round(y * 10) / 10,
                    label: item.label,
                    full_date: item.full_date || item.label,
                    value: val,
                    formatted: this.formatMetric(val),
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
                const p0 = pts[i];
                const p1 = pts[i + 1];
                const midX = (p0.x + p1.x) / 2;
                d += ` C ${midX} ${p0.y}, ${midX} ${p1.y}, ${p1.x} ${p1.y}`;
            }
            return d;
        },
        getAreaPath() {
            const pts = this.getCoordinates();
            if (!pts.length) return '';
            const baseline = 192;
            if (pts.length === 1) {
                return `M 72 ${pts[0].y} L 736 ${pts[0].y} L 736 ${baseline} L 72 ${baseline} Z`;
            }
            const line = this.getLinePath();
            const first = pts[0];
            const last = pts[pts.length - 1];
            return `${line} L ${last.x} ${baseline} L ${first.x} ${baseline} Z`;
        }
     }"
     class="max-w-7xl mx-auto space-y-8 select-none">

    <div class="flex flex-col xl:flex-row xl:items-end justify-between gap-4">
        <div>
            <div class="flex flex-wrap items-center gap-2.5">
                <span class="px-3 py-1 rounded-full bg-primary/10 text-primary text-xs font-extrabold uppercase tracking-wider">
                    Executive Command Center
                </span>
                <span class="px-3 py-1 rounded-full bg-brand-light/50 text-primary-dark text-xs font-bold border border-border-subtle">
                    {{ $periodLabel }}
                </span>
                <span class="px-3 py-1 rounded-full bg-surface border border-border-subtle text-text-main text-xs font-mono font-bold">
                    {{ $dateRangeLabel }}
                </span>
            </div>
            <h1 class="text-2xl sm:text-3xl font-bold text-primary-dark mt-2">Platform Overview &amp; Real-Time Analytics</h1>
            <p class="text-xs sm:text-sm text-text-muted mt-0.5">
                Monitor marketplace GMV, 10% Admin Commission, 90% Seller &amp; Logistics Hub shares, pending verifications, and live shipments.
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <!-- Period Mode Selector -->
            <div class="inline-flex items-center gap-1 p-1.5 rounded-2xl bg-surface border border-border-subtle shadow-2xs">
                @foreach([
                    'today' => 'Today (12AM–11PM)',
                    '7d'    => '7 Days',
                    '30d'   => '30 Days',
                    'year'  => 'Year',
                ] as $pKey => $pText)
                    <a href="{{ route('admin.dashboard', ['period' => $pKey]) }}"
                       class="px-3 py-2 rounded-xl text-xs font-bold transition-all cursor-pointer {{ $period === $pKey ? 'bg-primary-dark text-white shadow-xs' : 'text-text-muted hover:text-text-main hover:bg-surface-subtle' }}">
                        {{ $pText }}
                    </a>
                @endforeach
            </div>

            <!-- Historical Date Navigator (Past Allowed, Future Strictly Blocked) -->
            <div class="inline-flex items-center gap-1.5 p-1.5 rounded-2xl bg-surface border border-border-subtle shadow-2xs text-xs">
                <a href="{{ route('admin.dashboard', ['period' => $period, 'offset' => $offset + 1]) }}"
                   title="Browse previous {{ $period === 'today' ? 'day' : ($period === 'year' ? 'year' : 'period') }}"
                   class="px-3 py-1.5 rounded-xl bg-surface-subtle hover:bg-brand-light/40 text-text-main font-bold transition-colors">
                    &larr; Prev
                </a>

                <form method="GET" action="{{ route('admin.dashboard') }}" class="inline-flex items-center m-0">
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
                    <a href="{{ route('admin.dashboard', ['period' => $period, 'offset' => max(0, $offset - 1)]) }}"
                       class="px-3 py-1.5 rounded-xl bg-surface-subtle hover:bg-brand-light/40 text-text-main font-bold transition-colors">
                        Next &rarr;
                    </a>
                    <a href="{{ route('admin.dashboard', ['period' => $period]) }}"
                       class="px-2.5 py-1.5 rounded-xl bg-primary/10 text-primary hover:bg-primary hover:text-white font-bold transition-colors">
                        Current
                    </a>
                @else
                    <span class="px-3 py-1.5 rounded-xl bg-gray-100 text-gray-400 font-bold cursor-not-allowed" title="Future dates are disabled">
                        Next &rarr;
                    </span>
                @endif
            </div>

            <a href="{{ route('admin.reports.formal-pdf', ['role' => 'Admin']) }}"
               target="_blank"
               class="px-4 py-2.5 rounded-2xl bg-primary hover:bg-primary-dark text-white text-xs font-extrabold shadow-xs transition-colors inline-flex items-center gap-1.5">
                <span>Formal PDF &nearr;</span>
            </a>
        </div>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-4">
        <div class="p-5 rounded-3xl bg-gradient-to-br from-primary-dark via-primary to-secondary text-white shadow-md">
            <span class="text-[10px] font-extrabold uppercase tracking-wider text-white/80 block">Admin E-Wallet (10%)</span>
            <p class="text-2xl font-serif font-extrabold mt-1.5">&#8369;{{ number_format($kpis['admin_wallet_available'] ?? 0, 2) }}</p>
            <span class="text-[11px] text-white/85 mt-1 block">Escrow: &#8369;{{ number_format($kpis['admin_wallet_pending'] ?? 0, 2) }}</span>
        </div>

        <div class="p-5 rounded-3xl bg-surface border border-border-subtle shadow-xs">
            <span class="text-[10px] font-bold uppercase tracking-wider text-text-muted block">Period GMV</span>
            <p class="text-2xl font-serif font-bold text-text-main mt-1.5">&#8369;{{ number_format($kpis['period_gmv'] ?? 0, 2) }}</p>
            <span class="text-[11px] text-green-700 font-semibold mt-1 block">All-Time: &#8369;{{ number_format($kpis['all_time_gmv'] ?? 0, 2) }}</span>
        </div>

        <div class="p-5 rounded-3xl bg-surface border border-border-subtle shadow-xs">
            <span class="text-[10px] font-bold uppercase tracking-wider text-text-muted block">Period Admin 10%</span>
            <p class="text-2xl font-serif font-bold text-primary mt-1.5">&#8369;{{ number_format($kpis['period_admin_commission'] ?? 0, 2) }}</p>
            <span class="text-[11px] text-text-muted mt-1 block">Seller 90%: &#8369;{{ number_format($kpis['period_seller_net'] ?? 0, 2) }}</span>
        </div>

        <div class="p-5 rounded-3xl bg-surface border border-border-subtle shadow-xs">
            <span class="text-[10px] font-bold uppercase tracking-wider text-text-muted block">Period Orders</span>
            <p class="text-2xl font-serif font-bold text-primary-dark mt-1.5">{{ number_format($kpis['period_orders_total'] ?? 0) }}</p>
            <span class="text-[11px] text-emerald-700 font-semibold mt-1 block">
                Done: {{ $kpis['period_orders_completed'] ?? 0 }} &bull; Cancel: {{ $kpis['period_orders_cancelled'] ?? 0 }}
            </span>
        </div>

        <a href="{{ route('admin.commissions.index', ['tab' => 'withdrawals']) }}"
           class="p-5 rounded-3xl bg-surface border border-border-subtle hover:border-primary/50 shadow-xs transition-all block">
            <span class="text-[10px] font-bold uppercase tracking-wider text-text-muted block">Pending Withdrawals</span>
            <p class="text-2xl font-serif font-bold text-amber-600 mt-1.5">{{ $kpis['pending_withdrawals'] ?? 0 }}</p>
            <span class="text-[11px] text-primary font-semibold mt-1 block">&#8369;{{ number_format($kpis['pending_withdrawals_amt'] ?? 0, 2) }} queued &rarr;</span>
        </a>

        <a href="{{ route('admin.applications.sellers') }}"
           class="p-5 rounded-3xl bg-surface border border-border-subtle hover:border-primary/50 shadow-xs transition-all block">
            <span class="text-[10px] font-bold uppercase tracking-wider text-text-muted block">Pending Verifications</span>
            <p class="text-2xl font-serif font-bold text-danger mt-1.5">{{ ($kpis['pending_apps_total'] ?? 0) + ($kpis['pending_compliance'] ?? 0) }}</p>
            <span class="text-[11px] text-text-muted mt-1 block">
                Apps: {{ $kpis['pending_apps_total'] ?? 0 }} &bull; Products: {{ $kpis['pending_compliance'] ?? 0 }}
            </span>
        </a>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
        <div class="lg:col-span-8 bg-surface rounded-3xl border border-border-subtle p-6 sm:p-7 shadow-xs space-y-5">
            <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-4 border-b border-border-subtle pb-4">
                <div>
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="text-[10px] font-extrabold uppercase tracking-wider text-primary">
                            Platform Statistical Graph ({{ $periodLabel }})
                        </span>
                        <span class="px-2.5 py-0.5 rounded-full bg-surface-subtle border border-border-subtle text-[11px] font-mono font-bold text-primary-dark">
                            {{ $dateRangeLabel }}
                        </span>
                    </div>
                    <h2 class="text-lg font-bold text-primary-dark mt-1" x-text="metricMeta[activeMetric].label"></h2>
                    <p class="text-xs text-text-muted mt-0.5">
                        Period Total: <strong class="text-primary font-mono" x-text="formatMetric(periodTotal)"></strong>
                        &bull; Avg / Slot: <strong class="text-text-main font-mono" x-text="formatMetric(periodAverage)"></strong>
                        &bull; Peak: <strong class="text-primary-dark font-mono" x-text="formatMetric(peakBucket.val) + ' (' + peakBucket.label + ')'"></strong>
                    </p>
                </div>

                <div class="flex flex-wrap items-center gap-1.5 bg-surface-subtle p-1.5 rounded-2xl border border-border-subtle text-xs shrink-0">
                    <button type="button"
                            @click="activeMetric = 'gmv'"
                            :class="activeMetric === 'gmv' ? 'bg-primary text-white font-bold shadow-2xs' : 'text-text-muted hover:text-text-main font-semibold'"
                            class="px-3 py-1.5 rounded-xl transition-all cursor-pointer">
                        GMV (&#8369;)
                    </button>
                    <button type="button"
                            @click="activeMetric = 'admin_commission'"
                            :class="activeMetric === 'admin_commission' ? 'bg-primary-dark text-white font-bold shadow-2xs' : 'text-text-muted hover:text-text-main font-semibold'"
                            class="px-3 py-1.5 rounded-xl transition-all cursor-pointer">
                        Admin 10%
                    </button>
                    <button type="button"
                            @click="activeMetric = 'shipping'"
                            :class="activeMetric === 'shipping' ? 'bg-teal-600 text-white font-bold shadow-2xs' : 'text-text-muted hover:text-text-main font-semibold'"
                            class="px-3 py-1.5 rounded-xl transition-all cursor-pointer">
                        Shipping
                    </button>
                    <button type="button"
                            @click="activeMetric = 'orders'"
                            :class="activeMetric === 'orders' ? 'bg-amber-600 text-white font-bold shadow-2xs' : 'text-text-muted hover:text-text-main font-semibold'"
                            class="px-3 py-1.5 rounded-xl transition-all cursor-pointer">
                        Orders
                    </button>
                </div>
            </div>

            <!-- Numerical Summary Strip -->
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                <div class="p-3.5 rounded-2xl border transition-all cursor-pointer"
                     :class="activeMetric === 'gmv' ? 'bg-primary/5 border-primary/40' : 'bg-surface-subtle/60 border-border-subtle'"
                     @click="activeMetric = 'gmv'">
                    <span class="text-[10px] font-bold uppercase text-text-muted block">Period GMV</span>
                    <strong class="text-base sm:text-lg font-serif font-extrabold text-primary block mt-0.5"
                            x-text="formatMetric(sumByKey('gmv'), 'gmv')"></strong>
                    <span class="text-[10px] text-text-muted font-mono">100% Merchandise</span>
                </div>

                <div class="p-3.5 rounded-2xl border transition-all cursor-pointer"
                     :class="activeMetric === 'admin_commission' ? 'bg-brand-light/40 border-primary-dark/40' : 'bg-surface-subtle/60 border-border-subtle'"
                     @click="activeMetric = 'admin_commission'">
                    <span class="text-[10px] font-bold uppercase text-text-muted block">Admin 10% Share</span>
                    <strong class="text-base sm:text-lg font-serif font-extrabold text-primary-dark block mt-0.5"
                            x-text="formatMetric(sumByKey('admin_commission'), 'admin_commission')"></strong>
                    <span class="text-[10px] text-text-muted">Platform Fee</span>
                </div>

                <div class="p-3.5 rounded-2xl border transition-all cursor-pointer"
                     :class="activeMetric === 'shipping' ? 'bg-teal-50 border-teal-500/40' : 'bg-surface-subtle/60 border-border-subtle'"
                     @click="activeMetric = 'shipping'">
                    <span class="text-[10px] font-bold uppercase text-text-muted block">Shipping Volume</span>
                    <strong class="text-base sm:text-lg font-serif font-extrabold text-teal-700 block mt-0.5"
                            x-text="formatMetric(sumByKey('shipping'), 'shipping')"></strong>
                    <span class="text-[10px] text-text-muted">90% Hub / 10% Rider</span>
                </div>

                <div class="p-3.5 rounded-2xl border transition-all cursor-pointer"
                     :class="activeMetric === 'orders' ? 'bg-amber-50 border-amber-500/40' : 'bg-surface-subtle/60 border-border-subtle'"
                     @click="activeMetric = 'orders'">
                    <span class="text-[10px] font-bold uppercase text-text-muted block">Orders Placed</span>
                    <strong class="text-base sm:text-lg font-serif font-extrabold text-amber-700 block mt-0.5"
                            x-text="sumByKey('orders').toLocaleString() + ' Order(s)'"></strong>
                    <span class="text-[10px] text-text-muted" x-text="'Peak: ' + peakBucket.label"></span>
                </div>
            </div>

            <!-- Interactive SVG Graph with Continuous Line Hover & Floating Scope Tooltip -->
            <div class="relative w-full pt-2">
                <!-- Floating Graph Line Tooltip -->
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
                     class="w-full h-60 overflow-visible select-none cursor-crosshair">
                    <defs>
                        <linearGradient id="storkiaAdminAreaGrad" x1="0" y1="0" x2="0" y2="1">
                            <stop offset="0%" :stop-color="metricMeta[activeMetric].stopColor" stop-opacity="0.42" />
                            <stop offset="65%" :stop-color="metricMeta[activeMetric].stopColor" stop-opacity="0.10" />
                            <stop offset="100%" :stop-color="metricMeta[activeMetric].stopColor" stop-opacity="0.0" />
                        </linearGradient>
                    </defs>

                    <template x-for="(tick, tIdx) in yAxisTicks()" :key="'adm_tick_' + tIdx">
                        <g>
                            <line x1="72" :y1="tick.y" x2="736" :y2="tick.y" stroke="#E7E5E4" stroke-dasharray="4 4" stroke-width="1" />
                            <text x="66" :y="tick.y + 3" text-anchor="end" class="text-[9px] fill-stone-500 font-mono font-bold" x-text="tick.text"></text>
                        </g>
                    </template>

                    <path :d="getAreaPath()" fill="url(#storkiaAdminAreaGrad)" class="transition-all duration-300 pointer-events-none" />

                    <path :d="getLinePath()"
                          fill="none"
                          :stroke="metricMeta[activeMetric].color"
                          stroke-width="3"
                          stroke-linecap="round"
                          stroke-linejoin="round"
                          class="transition-all duration-300 pointer-events-none" />

                    <template x-for="(pt, idx) in getCoordinates()" :key="idx">
                        <g class="pointer-events-none">
                            <line x-show="hoverIndex === idx"
                                  :x1="pt.x" y1="22"
                                  :x2="pt.x" y2="192"
                                  :stroke="metricMeta[activeMetric].color"
                                  stroke-dasharray="3 3"
                                  stroke-width="1.5" />
                            <circle :cx="pt.x"
                                    :cy="pt.y"
                                    :r="hoverIndex === idx ? 6.5 : 3.5"
                                    :fill="hoverIndex === idx ? metricMeta[activeMetric].color : '#FFFFFF'"
                                    :stroke="metricMeta[activeMetric].color"
                                    stroke-width="2.5"
                                    class="transition-all duration-100" />
                            <text x-show="pt.value > 0 && (series.length <= 12 || hoverIndex === idx)"
                                  :x="pt.x"
                                  :y="Math.max(18, pt.y - 10)"
                                  text-anchor="middle"
                                  class="text-[9px] font-mono font-extrabold fill-stone-800"
                                  x-text="pt.formatted"></text>
                            <text x-show="shouldShowXLabel(idx)"
                                  :x="pt.x"
                                  y="214"
                                  text-anchor="middle"
                                  class="text-[10px] fill-stone-600 font-bold"
                                  x-text="pt.label"></text>
                        </g>
                    </template>
                </svg>

                <!-- Permanent Fixed-Height Live Scope Bar (Zero Layout Shift on Hover) -->
                <div class="mt-2 h-11 px-4 rounded-2xl transition-colors duration-150 flex items-center justify-between gap-2 text-xs shadow-xs"
                     :class="hoverIndex !== null && getCoordinates()[hoverIndex] ? 'bg-primary-dark text-white' : 'bg-surface-subtle border border-border-subtle text-text-main'">
                    <template x-if="hoverIndex !== null && getCoordinates()[hoverIndex]">
                        <div class="w-full flex flex-wrap items-center justify-between gap-2">
                            <span class="font-bold" x-text="'Scope: ' + getCoordinates()[hoverIndex].full_date"></span>
                            <div class="flex flex-wrap items-center gap-4 font-mono">
                                <span x-text="'GMV: \u20B1' + Number(getCoordinates()[hoverIndex].raw.gmv || 0).toLocaleString('en-US', {minimumFractionDigits: 2})"></span>
                                <span x-text="'Admin 10%: \u20B1' + Number(getCoordinates()[hoverIndex].raw.admin_commission || 0).toLocaleString('en-US', {minimumFractionDigits: 2})"></span>
                                <span x-text="'Shipping: \u20B1' + Number(getCoordinates()[hoverIndex].raw.shipping || 0).toLocaleString('en-US', {minimumFractionDigits: 2})"></span>
                                <span x-text="'Orders: ' + Number(getCoordinates()[hoverIndex].raw.orders || 0)"></span>
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

            <!-- Date & Statistical Numbers Breakdown Cards (Zero Glitch) -->
            <div class="pt-3 border-t border-border-subtle" @mouseleave="clearHover()">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-[11px] font-extrabold uppercase tracking-wider text-text-muted">Date &amp; Statistical Numbers Breakdown</span>
                    <span class="text-[11px] text-text-muted font-mono">{{ $dateRangeLabel }}</span>
                </div>
                <div class="grid grid-cols-2 sm:grid-cols-4 md:grid-cols-6 lg:grid-cols-7 gap-2 max-h-48 overflow-y-auto custom-scrollbar pr-1">
                    <template x-for="(row, rIdx) in series" :key="'adm_stat_' + rIdx">
                        <div @mouseenter="setHover(rIdx)"
                             :class="hoverIndex === rIdx ? 'border-primary bg-primary/5 ring-1 ring-primary/30' : 'border-border-subtle bg-surface-subtle/50 hover:border-primary/40'"
                             class="p-2.5 rounded-xl border text-xs transition-colors cursor-pointer">
                            <div class="font-bold text-primary-dark truncate" :title="row.full_date" x-text="row.label"></div>
                            <div class="font-mono font-extrabold text-primary text-[11px] mt-1"
                                 x-text="formatMetric(row[activeMetric])"></div>
                            <div class="text-[10px] text-text-muted font-mono"
                                 x-text="Number(row.orders || 0) + ' order(s)'"></div>
                        </div>
                    </template>
                </div>
            </div>
        </div>

        <div class="lg:col-span-4 space-y-6">
            <div class="bg-surface rounded-3xl border border-border-subtle p-6 shadow-xs space-y-4">
                <h3 class="text-base font-bold text-primary-dark border-b border-border-subtle pb-3">Administrative Action Queues</h3>
                <div class="space-y-2.5 text-xs">
                    <a href="{{ route('admin.applications.identity') }}" class="p-3 rounded-2xl bg-surface-subtle hover:bg-brand-light/30 border border-border-subtle flex items-center justify-between transition-colors">
                        <span class="font-bold text-text-main">Buyer Valid ID Verifications</span>
                        <span class="px-2.5 py-0.5 rounded-full font-extrabold {{ ($kpis['pending_identity'] ?? 0) > 0 ? 'bg-red-500 text-white' : 'bg-white text-text-muted border border-border-subtle' }}">
                            {{ $kpis['pending_identity'] ?? 0 }}
                        </span>
                    </a>
                    <a href="{{ route('admin.applications.sellers') }}" class="p-3 rounded-2xl bg-surface-subtle hover:bg-brand-light/30 border border-border-subtle flex items-center justify-between transition-colors">
                        <span class="font-bold text-text-main">Seller Store Applications</span>
                        <span class="px-2.5 py-0.5 rounded-full font-extrabold {{ ($kpis['pending_seller_apps'] ?? 0) > 0 ? 'bg-red-500 text-white' : 'bg-white text-text-muted border border-border-subtle' }}">
                            {{ $kpis['pending_seller_apps'] ?? 0 }}
                        </span>
                    </a>
                    <a href="{{ route('admin.applications.logistics') }}" class="p-3 rounded-2xl bg-surface-subtle hover:bg-brand-light/30 border border-border-subtle flex items-center justify-between transition-colors">
                        <span class="font-bold text-text-main">Logistics Hub Applications</span>
                        <span class="px-2.5 py-0.5 rounded-full font-extrabold {{ ($kpis['pending_logistics_apps'] ?? 0) > 0 ? 'bg-red-500 text-white' : 'bg-white text-text-muted border border-border-subtle' }}">
                            {{ $kpis['pending_logistics_apps'] ?? 0 }}
                        </span>
                    </a>
                    <a href="{{ route('admin.compliance.index') }}" class="p-3 rounded-2xl bg-surface-subtle hover:bg-brand-light/30 border border-border-subtle flex items-center justify-between transition-colors">
                        <span class="font-bold text-text-main">Product Compliance Queue</span>
                        <span class="px-2.5 py-0.5 rounded-full font-extrabold {{ ($kpis['pending_compliance'] ?? 0) > 0 ? 'bg-amber-500 text-white' : 'bg-white text-text-muted border border-border-subtle' }}">
                            {{ $kpis['pending_compliance'] ?? 0 }}
                        </span>
                    </a>
                    <a href="{{ route('admin.complaints.index') }}" class="p-3 rounded-2xl bg-surface-subtle hover:bg-brand-light/30 border border-border-subtle flex items-center justify-between transition-colors">
                        <span class="font-bold text-text-main">Unresolved Return Disputes</span>
                        <span class="px-2.5 py-0.5 rounded-full font-extrabold {{ ($kpis['unresolved_disputes'] ?? 0) > 0 ? 'bg-red-500 text-white' : 'bg-white text-text-muted border border-border-subtle' }}">
                            {{ $kpis['unresolved_disputes'] ?? 0 }}
                        </span>
                    </a>
                    <a href="{{ route('admin.accounts.index', ['role' => 'Appeals']) }}" class="p-3 rounded-2xl bg-surface-subtle hover:bg-brand-light/30 border border-border-subtle flex items-center justify-between transition-colors">
                        <span class="font-bold text-text-main">Buyer 3-Strike Ban Appeals</span>
                        <span class="px-2.5 py-0.5 rounded-full font-extrabold {{ ($kpis['pending_appeals'] ?? 0) > 0 ? 'bg-amber-500 text-white' : 'bg-white text-text-muted border border-border-subtle' }}">
                            {{ $kpis['pending_appeals'] ?? 0 }}
                        </span>
                    </a>
                </div>
            </div>

            <div class="bg-surface rounded-3xl border border-border-subtle p-6 shadow-xs space-y-3">
                <h3 class="text-sm font-bold text-primary-dark">Ecosystem Directory Counts</h3>
                <div class="grid grid-cols-3 gap-2.5 text-center text-xs">
                    <a href="{{ route('admin.accounts.index', ['role' => 'Buyer']) }}" class="p-3 rounded-2xl bg-surface-subtle border border-border-subtle hover:border-primary">
                        <strong class="text-lg font-serif text-primary-dark block">{{ number_format($roleCounts['buyers'] ?? 0) }}</strong>
                        <span class="text-[10px] text-text-muted font-bold uppercase">Buyers</span>
                    </a>
                    <a href="{{ route('admin.accounts.index', ['role' => 'Seller']) }}" class="p-3 rounded-2xl bg-surface-subtle border border-border-subtle hover:border-primary">
                        <strong class="text-lg font-serif text-primary-dark block">{{ number_format($roleCounts['sellers'] ?? 0) }}</strong>
                        <span class="text-[10px] text-text-muted font-bold uppercase">Sellers</span>
                    </a>
                    <a href="{{ route('admin.accounts.index', ['role' => 'Logistics']) }}" class="p-3 rounded-2xl bg-surface-subtle border border-border-subtle hover:border-primary">
                        <strong class="text-lg font-serif text-primary-dark block">{{ number_format($roleCounts['logistics'] ?? 0) }}</strong>
                        <span class="text-[10px] text-text-muted font-bold uppercase">Hubs</span>
                    </a>
                    <a href="{{ route('admin.accounts.index', ['role' => 'Courier']) }}" class="p-3 rounded-2xl bg-surface-subtle border border-border-subtle hover:border-primary">
                        <strong class="text-lg font-serif text-primary-dark block">{{ number_format($roleCounts['couriers'] ?? 0) }}</strong>
                        <span class="text-[10px] text-text-muted font-bold uppercase">Couriers</span>
                    </a>
                    <a href="{{ route('admin.accounts.index', ['role' => 'Rider']) }}" class="p-3 rounded-2xl bg-surface-subtle border border-border-subtle hover:border-primary">
                        <strong class="text-lg font-serif text-primary-dark block">{{ number_format($roleCounts['riders'] ?? 0) }}</strong>
                        <span class="text-[10px] text-text-muted font-bold uppercase">Riders</span>
                    </a>
                    <div class="p-3 rounded-2xl bg-red-50 border border-red-200">
                        <strong class="text-lg font-serif text-danger block">{{ number_format($roleCounts['suspended'] ?? 0) }}</strong>
                        <span class="text-[10px] text-danger font-bold uppercase">Sanctioned</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="bg-surface rounded-3xl border border-border-subtle shadow-xs overflow-hidden">
        <div class="p-5 border-b border-border-subtle bg-surface-subtle/50 flex flex-wrap items-center justify-between gap-2">
            <div>
                <h3 class="text-base font-bold text-primary-dark">Recent Platform Orders &amp; Fulfillment Status</h3>
                <p class="text-xs text-text-muted">Live feed of Seller Orders across all stores and sorting hubs.</p>
            </div>
            <a href="{{ route('admin.reports.sales') }}" class="text-xs font-bold text-primary hover:underline">Open Full Sales Reports &rarr;</a>
        </div>

        @if($recentOrders->isEmpty())
            <div class="p-10 text-center text-xs text-text-muted">No orders recorded yet.</div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-xs">
                    <thead>
                        <tr class="border-b border-border-subtle bg-surface-subtle/40 text-text-muted uppercase text-[11px] font-bold">
                            <th class="py-3.5 px-5">Order #</th>
                            <th class="py-3.5 px-4">Buyer</th>
                            <th class="py-3.5 px-4">Seller Store</th>
                            <th class="py-3.5 px-4">Logistics &amp; Tracking</th>
                            <th class="py-3.5 px-4">Total</th>
                            <th class="py-3.5 px-4">Status</th>
                            <th class="py-3.5 px-5 text-right">Placed At</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border-subtle">
                        @foreach($recentOrders as $so)
                            <tr class="hover:bg-surface-subtle/30">
                                <td class="py-3.5 px-5 font-mono font-bold text-primary-dark">{{ $so->seller_order_number }}</td>
                                <td class="py-3.5 px-4 font-semibold text-text-main">{{ $so->buyer?->full_name ?? 'Buyer' }}</td>
                                <td class="py-3.5 px-4">{{ $so->sellerProfile->business_name ?? 'Store' }}</td>
                                <td class="py-3.5 px-4">
                                    <div>{{ $so->logisticsProfile->business_name ?? 'Pending Hub' }}</div>
                                    @if($so->shipment)
                                        <span class="font-mono text-[11px] text-primary-dark font-bold">{{ $so->shipment->tracking_number }}</span>
                                    @endif
                                </td>
                                <td class="py-3.5 px-4 font-bold text-primary">&#8369;{{ number_format((float) $so->total_amount, 2) }}</td>
                                <td class="py-3.5 px-4">
                                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-brand-light/50 text-primary-dark">
                                        {{ $so->status_label }}
                                    </span>
                                </td>
                                <td class="py-3.5 px-5 text-right text-text-muted">{{ $so->created_at?->format('M d, Y h:i A') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>