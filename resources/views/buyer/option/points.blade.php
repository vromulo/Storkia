@extends('buyer.account-settings', [
    'pageTitle' => 'My Coins',
    'pageSubtitle' => 'Earn Storkia Coins from daily games and seller SLA compensation, spin the daily wheel, and redeem vouchers for checkout.'
])

@section('option-content')
@php
    $coinsBalance = (int) ($wallet->coins_balance ?? 0);
    $lifetimeEarned = (int) ($wallet->lifetime_coins_earned ?? 0);
    $lifetimeSpent = (int) ($wallet->lifetime_coins_spent ?? 0);
    $spinTickets = (int) ($wallet->spin_tickets ?? 0);
    $nextDailySpinSeconds = (int) ($wallet->secondsUntilNextDailyLoginSpin() ?? 0);
@endphp

<style>
    @keyframes storkiaRewardPop {
        0% { transform: scale(0.5); opacity: 0; }
        70% { transform: scale(1.08); opacity: 1; }
        100% { transform: scale(1); opacity: 1; }
    }
    .animate-reward-pop {
        animation: storkiaRewardPop 0.45s cubic-bezier(0.22, 1, 0.36, 1) forwards;
    }
</style>

<div x-data="{
        activeTab: 'wheel',
        coinsBalance: {{ $coinsBalance }},
        spinTickets: {{ $spinTickets }},
        cooldownSec: {{ $nextDailySpinSeconds }},
        cooldownFormatted: '',
        isSpinning: false,
        wheelRotation: 0,
        spinResultModal: false,
        lastSpinResult: null,
        spinError: '',
        redeemingKey: '',
        redeemSuccessMsg: '',
        redeemErrorMsg: '',
        copiedVoucherCode: '',
        segments: @js($wheelSegments),
        initPointsHub() {
            this.updateCooldownText();
            setInterval(() => {
                if (this.cooldownSec > 0) {
                    this.cooldownSec--;
                    this.updateCooldownText();
                }
            }, 1000);
        },
        updateCooldownText() {
            if (this.cooldownSec <= 0) {
                this.cooldownFormatted = 'Ready on next login';
                return;
            }
            const hrs = String(Math.floor(this.cooldownSec / 3600)).padStart(2, '0');
            const mins = String(Math.floor((this.cooldownSec % 3600) / 60)).padStart(2, '0');
            const secs = String(this.cooldownSec % 60).padStart(2, '0');
            this.cooldownFormatted = `${hrs}h ${mins}m ${secs}s`;
        },
        copyCode(code) {
            if (!code || !navigator.clipboard) return;
            navigator.clipboard.writeText(code);
            this.copiedVoucherCode = code;
            setTimeout(() => {
                if (this.copiedVoucherCode === code) this.copiedVoucherCode = '';
            }, 2000);
        },
        async spinNow() {
            if (this.isSpinning || this.spinTickets <= 0) return;
            this.isSpinning = true;
            this.spinError = '';

            try {
                const res = await fetch('{{ route('user.points.spin') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    }
                });

                const data = await res.json();

                if (!res.ok || !data.success) {
                    const errText = data.errors ? Object.values(data.errors).flat()[0] : (data.message || 'Unable to spin right now.');
                    this.spinError = errText;
                    this.isSpinning = false;
                    return;
                }

                const segCount = this.segments.length || 8;
                const sliceDeg = 360 / segCount;
                const targetIndex = Number(data.segment_index || 0);
                const segmentCenterAngle = (targetIndex * sliceDeg) + (sliceDeg / 2);
                const pointerTarget = (360 - segmentCenterAngle) % 360;
                const currentMod = ((this.wheelRotation % 360) + 360) % 360;
                let delta = pointerTarget - currentMod;
                if (delta < 0) delta += 360;

                const extraSpins = (5 + Math.floor(Math.random() * 2)) * 360;
                this.wheelRotation = this.wheelRotation + extraSpins + delta;

                setTimeout(() => {
                    this.coinsBalance = Number(data.coins_balance ?? this.coinsBalance);
                    this.spinTickets = Number(data.spin_tickets ?? this.spinTickets);
                    if (typeof data.next_daily_spin_sec !== 'undefined') {
                        this.cooldownSec = Number(data.next_daily_spin_sec);
                        this.updateCooldownText();
                    }
                    this.lastSpinResult = data;
                    this.spinResultModal = true;
                    this.isSpinning = false;
                }, 4600);
            } catch (e) {
                this.spinError = 'Network error while spinning the wheel. Please try again.';
                this.isSpinning = false;
            }
        },
        async redeemVoucher(catalogKey, cost) {
            if (this.redeemingKey || this.coinsBalance < cost) return;
            this.redeemingKey = catalogKey;
            this.redeemSuccessMsg = '';
            this.redeemErrorMsg = '';

            try {
                const res = await fetch('{{ route('user.points.redeem') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({ catalog_key: catalogKey })
                });

                const data = await res.json();

                if (!res.ok || !data.success) {
                    const errText = data.errors ? Object.values(data.errors).flat()[0] : (data.message || 'Could not redeem voucher.');
                    this.redeemErrorMsg = errText;
                } else {
                    this.coinsBalance = Number(data.coins_balance ?? this.coinsBalance);
                    this.redeemSuccessMsg = data.message;
                }
            } catch (e) {
                this.redeemErrorMsg = 'Network error while redeeming voucher.';
            } finally {
                this.redeemingKey = '';
            }
        }
     }"
     x-init="initPointsHub()"
     class="space-y-8 select-none w-full min-w-0 max-w-full overflow-x-hidden">

    @if($justGrantedDailySpin)
        <div class="p-4 rounded-2xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs sm:text-sm font-bold flex items-center justify-between gap-3">
            <span>Daily Login Bonus Unlocked: +1 Spin Attempt has been added to your Spin Stack!</span>
            <span class="px-2.5 py-1 rounded-full bg-emerald-600 text-white text-[10px] font-extrabold uppercase shrink-0">+1 Spin</span>
        </div>
    @endif

    @if(session('success'))
        <div class="p-4 rounded-2xl bg-green-50 border border-green-200 text-green-700 text-xs sm:text-sm font-bold">
            {{ session('success') }}
        </div>
    @endif

    <template x-if="redeemSuccessMsg">
        <div class="p-4 rounded-2xl bg-green-50 border border-green-200 text-green-700 text-xs sm:text-sm font-bold flex items-center justify-between gap-3">
            <span x-text="redeemSuccessMsg"></span>
            <a href="{{ route('user.coupons') }}" class="px-3 py-1.5 rounded-xl bg-green-700 text-white text-xs font-extrabold shrink-0">
                Open My Vouchers &rarr;
            </a>
        </div>
    </template>

    <template x-if="redeemErrorMsg || spinError">
        <div class="p-4 rounded-2xl bg-red-50 border border-red-200 text-danger text-xs sm:text-sm font-bold"
             x-text="redeemErrorMsg || spinError"></div>
    </template>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="p-5 rounded-3xl bg-gradient-to-br from-primary-dark via-primary to-secondary text-white shadow-md relative overflow-hidden">
            <div class="flex items-center justify-between">
                <span class="text-[11px] font-extrabold uppercase tracking-wider text-white/85">Available Storkia Coins</span>
                <span class="w-7 h-7 rounded-full bg-white/20 flex items-center justify-center font-serif font-extrabold text-sm">S</span>
            </div>
            <p class="text-3xl sm:text-4xl font-serif font-extrabold mt-2" x-text="coinsBalance.toLocaleString()"></p>
            <span class="text-[11px] text-white/85 mt-1 block">Redeemable for Platform &amp; Shipping Vouchers</span>
        </div>

        <div class="p-5 rounded-3xl bg-surface border border-border-subtle shadow-xs flex flex-col justify-between">
            <span class="text-[11px] font-bold uppercase tracking-wider text-text-muted">Stacked Spin Attempts</span>
            <div class="flex items-baseline gap-2 mt-2">
                <span class="text-3xl font-serif font-extrabold text-primary" x-text="spinTickets"></span>
                <span class="text-xs font-bold text-text-muted">Spin(s) Ready</span>
            </div>
            <span class="text-[11px] text-text-muted mt-1 block">+1 Spin stacks every 24h Daily Login</span>
        </div>

        <div class="p-5 rounded-3xl bg-surface border border-border-subtle shadow-xs flex flex-col justify-between">
            <span class="text-[11px] font-bold uppercase tracking-wider text-text-muted">Next Daily Login Spin (24h)</span>
            <p class="text-xl font-mono font-extrabold text-primary-dark mt-2" x-text="cooldownFormatted"></p>
            <span class="text-[11px] text-text-muted mt-1 block">24-hour daily login spin cooldown</span>
        </div>

        <div class="p-5 rounded-3xl bg-surface border border-border-subtle shadow-xs flex flex-col justify-between">
            <span class="text-[11px] font-bold uppercase tracking-wider text-text-muted">Lifetime Coins Earned</span>
            <p class="text-2xl font-serif font-bold text-text-main mt-2">{{ number_format($lifetimeEarned) }}</p>
            <span class="text-[11px] text-text-muted mt-1 block">Redeemed: {{ number_format($lifetimeSpent) }} Coins</span>
        </div>
    </div>

    <div class="flex flex-wrap items-center gap-2 border-b border-border-subtle pb-3">
        <button type="button"
                @click="activeTab = 'wheel'"
                :class="activeTab === 'wheel' ? 'bg-primary-dark text-white border-primary-dark shadow-xs' : 'bg-surface text-text-muted border-border-subtle hover:text-text-main'"
                class="px-4 py-2.5 rounded-2xl text-xs font-bold border transition-all cursor-pointer">
            1. Daily Spin the Wheel
        </button>
        <button type="button"
                @click="activeTab = 'redeem'"
                :class="activeTab === 'redeem' ? 'bg-primary-dark text-white border-primary-dark shadow-xs' : 'bg-surface text-text-muted border-border-subtle hover:text-text-main'"
                class="px-4 py-2.5 rounded-2xl text-xs font-bold border transition-all cursor-pointer">
            2. Redeem Vouchers with Coins
        </button>
        <button type="button"
                @click="activeTab = 'history'"
                :class="activeTab === 'history' ? 'bg-primary-dark text-white border-primary-dark shadow-xs' : 'bg-surface text-text-muted border-border-subtle hover:text-text-main'"
                class="px-4 py-2.5 rounded-2xl text-xs font-bold border transition-all cursor-pointer">
            3. Points &amp; Coins History ({{ $transactions->total() }})
        </button>
    </div>

    <!-- TAB 1: Upgraded Spin the Wheel with Dedicated Icons & Non-Overlapping Radial Labels -->
    <div x-show="activeTab === 'wheel'" class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-center bg-surface rounded-3xl border border-border-subtle p-6 sm:p-8 shadow-xs">
        <div class="lg:col-span-6 flex flex-col items-center justify-center">
            <div class="relative w-76 h-76 sm:w-96 sm:h-96 flex items-center justify-center">
                <!-- Top Golden/Burgundy Pointer Arrow -->
                <div class="absolute -top-3.5 left-1/2 -translate-x-1/2 z-30 flex flex-col items-center drop-shadow-[0_6px_10px_rgba(0,0,0,0.35)]">
                    <div class="w-4 h-4 rounded-full bg-brand-light border-2 border-primary-dark -mb-2 z-10"></div>
                    <div class="w-0 h-0 border-l-[16px] border-l-transparent border-r-[16px] border-r-transparent border-t-[30px] border-t-primary-dark"></div>
                </div>

                <!-- Outer Decorative Wheel Rim -->
                <div class="w-full h-full rounded-full p-3 sm:p-3.5 bg-gradient-to-br from-primary-dark via-primary to-secondary shadow-[0_20px_50px_rgba(93,49,64,0.28)] border-4 border-white relative flex items-center justify-center">
                    <svg viewBox="0 0 360 360"
                         class="w-full h-full rounded-full overflow-hidden transition-transform duration-[4500ms] ease-[cubic-bezier(0.15,0.85,0.25,1)]"
                         :style="`transform: rotate(${wheelRotation}deg);`">
                        @php
                            $shortTitles = [
                                'coins_5'            => ['main' => '+5 COINS',    'sub' => 'Storkia Coin',  'icon' => 'coin'],
                                'free_3_spins'       => ['main' => '+3 SPINS',    'sub' => 'Free Ticket',   'icon' => 'spin'],
                                'coins_15'           => ['main' => '+15 COINS',   'sub' => 'Storkia Coin',  'icon' => 'coin'],
                                'voucher_ship_25'    => ['main' => '₱25 SHIP',    'sub' => 'Min ₱150',      'icon' => 'truck'],
                                'coins_30'           => ['main' => '+30 COINS',   'sub' => 'Storkia Coin',  'icon' => 'coin'],
                                'voucher_plat_40'    => ['main' => '₱40 OFF',     'sub' => 'Min ₱250',      'icon' => 'ticket'],
                                'coins_60'           => ['main' => '+60 COINS',   'sub' => 'Jackpot Coin',  'icon' => 'jackpot'],
                                'voucher_plat_pct10' => ['main' => '10% OFF',     'sub' => 'Cap ₱80',       'icon' => 'ticket'],
                            ];
                        @endphp

                        @foreach($wheelSegments as $idx => $seg)
                            @php
                                $count = count($wheelSegments);
                                $angle = 360 / $count;
                                $startDeg = ($idx * $angle) - 90;
                                $endDeg = (($idx + 1) * $angle) - 90;
                                $startRad = deg2rad($startDeg);
                                $endRad = deg2rad($endDeg);
                                $r = 174;
                                $cx = 180;
                                $cy = 180;
                                $x1 = $cx + $r * cos($startRad);
                                $y1 = $cy + $r * sin($startRad);
                                $x2 = $cx + $r * cos($endRad);
                                $y2 = $cy + $r * sin($endRad);
                                $midAngle = ($idx * $angle) + ($angle / 2);
                                $meta = $shortTitles[$seg['key']] ?? ['main' => $seg['label'], 'sub' => $seg['sub_label'], 'icon' => 'coin'];
                                $txtColor = $seg['text_color'];
                            @endphp

                            <!-- Slice Wedge Path -->
                            <path d="M {{ $cx }} {{ $cy }} L {{ round($x1, 2) }} {{ round($y1, 2) }} A {{ $r }} {{ $r }} 0 0 1 {{ round($x2, 2) }} {{ round($y2, 2) }} Z"
                                  fill="{{ $seg['color'] }}"
                                  stroke="#FFFFFF"
                                  stroke-width="2.5" />

                            <!-- Slice Icon & Compact Non-Overlapping Label Group -->
                            <g transform="rotate({{ $midAngle }} {{ $cx }} {{ $cy }})">
                                <!-- Outer Rim Stud Dot -->
                                <circle cx="180" cy="14" r="3" fill="{{ $txtColor }}" opacity="0.65" />

                                <!-- Icon Badge Circle inside Outer Wedge (Width here is ~94px, Badge is 30px) -->
                                <circle cx="180" cy="44" r="15" fill="{{ $txtColor }}" fill-opacity="0.16" stroke="{{ $txtColor }}" stroke-width="1.2" />

                                @if($meta['icon'] === 'coin' || $meta['icon'] === 'jackpot')
                                    <circle cx="180" cy="44" r="9" fill="none" stroke="{{ $txtColor }}" stroke-width="1.8" />
                                    <text x="180" y="47.5" fill="{{ $txtColor }}" font-size="10" font-weight="900" text-anchor="middle" class="font-serif">S</text>
                                @elseif($meta['icon'] === 'spin')
                                    <path d="M 174 44 A 6 6 0 1 1 185 47 M 185 42 L 186 48 L 180 47"
                                          fill="none"
                                          stroke="{{ $txtColor }}"
                                          stroke-width="1.8"
                                          stroke-linecap="round"
                                          stroke-linejoin="round" />
                                @elseif($meta['icon'] === 'truck')
                                    <rect x="172" y="40" width="10" height="7" rx="1" fill="none" stroke="{{ $txtColor }}" stroke-width="1.6" />
                                    <path d="M 182 42 H 186 L 188 45 V 47 H 182 Z" fill="none" stroke="{{ $txtColor }}" stroke-width="1.5" />
                                    <circle cx="175" cy="48.5" r="1.6" fill="{{ $txtColor }}" />
                                    <circle cx="185" cy="48.5" r="1.6" fill="{{ $txtColor }}" />
                                @else
                                    <rect x="172" y="39" width="16" height="10" rx="2" fill="none" stroke="{{ $txtColor }}" stroke-width="1.6" />
                                    <line x1="177" y1="39" x2="177" y2="49" stroke="{{ $txtColor }}" stroke-width="1.2" stroke-dasharray="2 1" />
                                @endif

                                <!-- Short Main Prize Text (Max 9 chars -> ~52px wide at radius 102 where wedge is 78px wide: zero overlap!) -->
                                <text x="180"
                                      y="76"
                                      fill="{{ $txtColor }}"
                                      font-size="11"
                                      font-weight="900"
                                      letter-spacing="0.4"
                                      text-anchor="middle"
                                      class="uppercase font-sans">
                                    {{ $meta['main'] }}
                                </text>

                                <!-- Compact Sub-Label (At radius 86 where wedge is 66px wide) -->
                                <text x="180"
                                      y="90"
                                      fill="{{ $txtColor }}"
                                      font-size="8.5"
                                      font-weight="700"
                                      text-anchor="middle"
                                      opacity="0.9"
                                      class="font-sans">
                                    {{ $meta['sub'] }}
                                </text>
                            </g>
                        @endforeach

                        <!-- Center Hub Cap -->
                        <circle cx="180" cy="180" r="36" fill="#5D3140" stroke="#F6D8BD" stroke-width="5" />
                        <circle cx="180" cy="180" r="28" fill="#CF4173" />
                        <text x="180" y="178" fill="#FFFFFF" font-size="11" font-weight="900" letter-spacing="1" text-anchor="middle" class="font-sans">STORKIA</text>
                        <text x="180" y="190" fill="#F6D8BD" font-size="8.5" font-weight="800" letter-spacing="1.2" text-anchor="middle" class="font-sans">SPIN</text>
                    </svg>
                </div>
            </div>

            <div class="mt-6 flex flex-col items-center gap-2">
                <button type="button"
                        @click="spinNow()"
                        :disabled="isSpinning || spinTickets <= 0"
                        :class="(isSpinning || spinTickets <= 0)
                            ? 'bg-gray-200 text-gray-500 cursor-not-allowed'
                            : 'bg-primary hover:bg-primary-dark text-white cursor-pointer shadow-lg shadow-primary/25 active:scale-95'"
                        class="px-8 py-3.5 rounded-2xl text-sm font-extrabold transition-all">
                    <span x-show="!isSpinning && spinTickets > 0" x-text="'SPIN THE WHEEL (' + spinTickets + ' Left)'"></span>
                    <span x-show="isSpinning" x-cloak>Spinning Wheel...</span>
                    <span x-show="!isSpinning && spinTickets <= 0" x-cloak x-text="'Cooldown: ' + cooldownFormatted"></span>
                </button>
                <p class="text-[11px] text-text-muted text-center">
                    Daily login spin attempts stack automatically. Winning <strong>+3 Spins</strong> grants 3 immediate extra spins!
                </p>
            </div>
        </div>

        <div class="lg:col-span-6 space-y-5 text-xs">
            <div class="space-y-1.5">
                <span class="px-2.5 py-0.5 rounded-full bg-primary/10 text-primary text-[10px] font-extrabold uppercase tracking-wider">
                    Wheel Prize Directory &amp; SLA Protection
                </span>
                <h3 class="text-xl font-serif font-bold text-primary-dark">Spin for Free Spins, Vouchers &amp; Storkia Coins</h3>
                <p class="text-text-muted leading-relaxed">
                    Every 24 hours you log in, you receive <strong>+1 Spin Attempt</strong> that stacks in your wallet. All vouchers won on the wheel or redeemed with Storkia Coins are automatically saved to your <strong>Voucher Wallet</strong> for checkout.
                </p>
            </div>

            <!-- Responsive Wheel Prizes Breakdown Grid with Icons -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                @foreach($wheelSegments as $seg)
                    <div class="p-3 rounded-2xl bg-surface-subtle border border-border-subtle flex items-center gap-3">
                        <div class="w-9 h-9 rounded-xl flex items-center justify-center shrink-0 font-extrabold text-xs shadow-2xs"
                             style="background-color: {{ $seg['color'] }}; color: {{ $seg['text_color'] }};">
                            @if($seg['type'] === 'coins')
                                S
                            @elseif($seg['type'] === 'spins')
                                &#8635;
                            @else
                                %
                            @endif
                        </div>
                        <div class="min-w-0">
                            <div class="font-bold text-text-main truncate">{{ $seg['label'] }}</div>
                            <div class="text-[11px] text-text-muted truncate">{{ $seg['sub_label'] }}</div>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="p-3.5 rounded-2xl bg-brand-light/25 border border-primary/20 flex items-center justify-between gap-3">
                <div>
                    <strong class="text-primary-dark block">24-Hour Seller Timeout Protection (+50 Coins)</strong>
                    <p class="text-[11px] text-text-muted mt-0.5">If a seller misses the 24-hour order acceptance deadline, the order auto-cancels and credits you <strong>+50 Storkia Coins</strong>.</p>
                </div>
            </div>

            <div class="pt-1 flex flex-wrap items-center gap-3">
                <button type="button"
                        @click="activeTab = 'redeem'"
                        class="px-5 py-2.5 rounded-xl bg-primary-dark hover:bg-text-main text-white font-bold cursor-pointer transition-colors">
                    Open Coin Voucher Shop &rarr;
                </button>
                <a href="{{ route('user.coupons') }}" class="px-5 py-2.5 rounded-xl bg-surface-subtle hover:bg-brand-light/40 border border-border-subtle text-text-main font-bold transition-colors">
                    View My Voucher Wallet
                </a>
            </div>
        </div>
    </div>

    <!-- TAB 2: Redeem Vouchers with Coins -->
    <div x-show="activeTab === 'redeem'" x-cloak class="space-y-6">
        <div class="bg-surface rounded-3xl border border-border-subtle p-6 shadow-xs space-y-5">
            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-border-subtle pb-4">
                <div>
                    <h3 class="text-base font-bold text-primary-dark">Redeem Storkia Coins for Usable Checkout Vouchers</h3>
                    <p class="text-xs text-text-muted">Redeemed vouchers are immediately added to your Voucher Wallet and selectable on the Checkout page.</p>
                </div>
                <div class="px-3.5 py-1.5 rounded-full bg-brand-light/50 text-primary-dark text-xs font-extrabold border border-border-subtle">
                    Your Balance: <span x-text="coinsBalance.toLocaleString()"></span> Coins
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                @foreach($redeemCatalog as $item)
                    @php
                        $isShipping = $item['scope'] === 'shipping';
                        $stubGradient = $isShipping ? 'from-emerald-600 to-teal-700' : 'from-primary to-primary-dark';
                    @endphp
                    <div class="flex rounded-2xl border border-border-subtle bg-surface overflow-hidden shadow-2xs">
                        <div class="w-32 sm:w-36 bg-gradient-to-br {{ $stubGradient }} text-white p-4 flex flex-col items-center justify-center text-center shrink-0 border-r-2 border-dashed border-white/40">
                            <span class="text-[9px] font-extrabold uppercase px-2 py-0.5 rounded bg-black/25 mb-1">
                                {{ $isShipping ? 'FREE SHIPPING' : 'PLATFORM' }}
                            </span>
                            <div class="text-base font-serif font-extrabold leading-tight">{{ $item['badge'] }}</div>
                            <span class="text-[10px] text-white/90 mt-1">Min. &#8369;{{ number_format((float) $item['min_spend'], 0) }}</span>
                        </div>
                        <div class="flex-1 p-4 flex flex-col justify-between text-xs min-w-0">
                            <div>
                                <div class="flex items-center justify-between gap-2">
                                    <h4 class="font-bold text-sm text-text-main truncate">{{ $item['title'] }}</h4>
                                    <span class="px-2.5 py-0.5 rounded-full bg-amber-100 text-amber-900 font-extrabold text-[11px] shrink-0">
                                        {{ number_format($item['coins_cost']) }} Coins
                                    </span>
                                </div>
                                <p class="text-text-muted text-[11px] mt-1 leading-relaxed">{{ $item['description'] }}</p>
                            </div>
                            <div class="pt-3 mt-3 border-t border-border-subtle flex items-center justify-between gap-2">
                                <span class="text-[11px] text-text-muted">Valid for {{ $item['valid_days'] }} days</span>
                                <button type="button"
                                        @click="redeemVoucher('{{ $item['key'] }}', {{ (int) $item['coins_cost'] }})"
                                        :disabled="coinsBalance < {{ (int) $item['coins_cost'] }} || redeemingKey === '{{ $item['key'] }}'"
                                        :class="coinsBalance >= {{ (int) $item['coins_cost'] }}
                                            ? 'bg-primary hover:bg-primary-dark text-white cursor-pointer'
                                            : 'bg-gray-200 text-gray-500 cursor-not-allowed'"
                                        class="px-4 py-2 rounded-xl font-bold text-xs transition-colors">
                                    <span x-show="redeemingKey !== '{{ $item['key'] }}'"
                                          x-text="coinsBalance >= {{ (int) $item['coins_cost'] }} ? 'Redeem (' + {{ (int) $item['coins_cost'] }} + ' Coins)' : 'Need ' + ({{ (int) $item['coins_cost'] }} - coinsBalance) + ' More'"></span>
                                    <span x-show="redeemingKey === '{{ $item['key'] }}'" x-cloak>Redeeming...</span>
                                </button>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        @if($activeRewardVouchers->isNotEmpty())
            <div class="bg-surface rounded-3xl border border-border-subtle p-6 shadow-xs space-y-4">
                <div class="flex items-center justify-between">
                    <h3 class="text-sm font-bold text-primary-dark">Your Active Claimed &amp; Redeemed Vouchers Ready for Checkout</h3>
                    <a href="{{ route('cart.index') }}" class="text-xs font-bold text-primary hover:underline">Use in Cart / Checkout &rarr;</a>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 text-xs">
                    @foreach($activeRewardVouchers as $claim)
                        @php $v = $claim->voucher; @endphp
                        @if($v)
                            <div class="p-3.5 rounded-2xl bg-surface-subtle border border-border-subtle flex items-center justify-between gap-2">
                                <div class="min-w-0">
                                    <div class="flex items-center gap-1.5">
                                        <span class="font-mono font-extrabold text-primary-dark">{{ $v->code }}</span>
                                        <span class="px-2 py-0.5 rounded bg-primary/10 text-primary font-bold text-[10px]">{{ $v->badge_label }}</span>
                                    </div>
                                    <div class="text-[11px] text-text-main font-semibold truncate mt-0.5">{{ $v->title }}</div>
                                    <div class="text-[10px] text-text-muted">Min. Spend &#8369;{{ number_format((float) $v->min_spend, 2) }}</div>
                                </div>
                                <button type="button"
                                        @click="copyCode('{{ $v->code }}')"
                                        class="px-2.5 py-1.5 rounded-lg bg-white border border-border-subtle hover:border-primary text-primary-dark font-bold text-[11px] cursor-pointer shrink-0">
                                    <span x-text="copiedVoucherCode === '{{ $v->code }}' ? 'Copied!' : 'Copy'"></span>
                                </button>
                            </div>
                        @endif
                    @endforeach
                </div>
            </div>
        @endif
    </div>

    <!-- TAB 3: Points & Coins History -->
    <div x-show="activeTab === 'history'" x-cloak class="bg-surface rounded-3xl border border-border-subtle shadow-xs overflow-hidden">
        <div class="p-5 border-b border-border-subtle bg-surface-subtle/50">
            <h3 class="text-base font-bold text-primary-dark">Points, Coins &amp; Spin Activity Ledger</h3>
            <p class="text-xs text-text-muted">Complete history of Daily Chest rewards, Spin the Wheel prizes, Seller 24h SLA timeout compensations, and Voucher redemptions.</p>
        </div>

        @if($transactions->isEmpty())
            <div class="p-12 text-center text-xs text-text-muted">No points or coin transactions recorded yet.</div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-xs">
                    <thead>
                        <tr class="border-b border-border-subtle bg-surface-subtle/40 text-text-muted uppercase text-[11px] font-bold">
                            <th class="py-3.5 px-5">Reference &amp; Date</th>
                            <th class="py-3.5 px-4">Source</th>
                            <th class="py-3.5 px-4">Details</th>
                            <th class="py-3.5 px-4 text-right">Coins Change</th>
                            <th class="py-3.5 px-4 text-right">Spins Change</th>
                            <th class="py-3.5 px-5 text-right">Balance After</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border-subtle">
                        @foreach($transactions as $tx)
                            <tr class="hover:bg-surface-subtle/30">
                                <td class="py-3.5 px-5 font-mono">
                                    <div class="font-bold text-primary-dark">{{ $tx->reference_code }}</div>
                                    <div class="text-[11px] text-text-muted">{{ $tx->created_at?->format('M d, Y h:i A') }}</div>
                                </td>
                                <td class="py-3.5 px-4">
                                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-extrabold uppercase bg-brand-light/50 text-primary-dark border border-border-subtle">
                                        {{ str_replace('_', ' ', $tx->source_type) }}
                                    </span>
                                </td>
                                <td class="py-3.5 px-4 font-medium text-text-main">
                                    {{ $tx->description }}
                                    @if($tx->voucher)
                                        <span class="font-mono font-bold text-primary ml-1">({{ $tx->voucher->code }})</span>
                                    @endif
                                </td>
                                <td class="py-3.5 px-4 text-right font-mono font-extrabold {{ $tx->coins_delta > 0 ? 'text-green-700' : ($tx->coins_delta < 0 ? 'text-danger' : 'text-text-muted') }}">
                                    {{ $tx->coins_delta > 0 ? '+' . $tx->coins_delta : ($tx->coins_delta < 0 ? $tx->coins_delta : '0') }}
                                </td>
                                <td class="py-3.5 px-4 text-right font-mono font-bold {{ $tx->spins_delta > 0 ? 'text-primary' : 'text-text-muted' }}">
                                    {{ $tx->spins_delta > 0 ? '+' . $tx->spins_delta : ($tx->spins_delta < 0 ? $tx->spins_delta : '0') }}
                                </td>
                                <td class="py-3.5 px-5 text-right font-mono font-bold text-text-main">
                                    {{ number_format((int) $tx->coins_balance_after) }} Coins
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if($transactions->hasPages())
                <div class="px-5 py-3 border-t border-border-subtle">
                    {{ $transactions->links() }}
                </div>
            @endif
        @endif
    </div>

    <template x-teleport="body">
        <div x-show="spinResultModal && lastSpinResult"
             x-cloak
             class="fixed inset-0 z-[210] flex items-center justify-center p-4 bg-black/65 backdrop-blur-xs">
            <div @click.away="spinResultModal = false"
                 class="bg-surface rounded-3xl max-w-md w-full p-6 sm:p-8 shadow-2xl border border-border-subtle text-center space-y-4 animate-reward-pop">
                <div class="w-16 h-16 rounded-full bg-primary/10 text-primary flex items-center justify-center mx-auto border border-primary/20 font-serif font-extrabold text-2xl">
                    &#9733;
                </div>
                <span class="px-3 py-1 rounded-full bg-brand-light/60 text-primary-dark text-[10px] font-extrabold uppercase tracking-widest">
                    Spin the Wheel Reward
                </span>
                <h3 class="text-2xl font-serif font-extrabold text-primary-dark" x-text="lastSpinResult?.segment?.label || 'Congratulations!'"></h3>
                <p class="text-xs sm:text-sm text-text-muted leading-relaxed" x-text="lastSpinResult?.message"></p>

                <template x-if="lastSpinResult?.voucher">
                    <div class="p-4 rounded-2xl bg-surface-subtle border border-primary/30 space-y-1 text-xs">
                        <div class="font-mono font-extrabold text-base text-primary-dark" x-text="lastSpinResult.voucher.code"></div>
                        <div class="font-bold text-primary" x-text="lastSpinResult.voucher.title + ' (' + lastSpinResult.voucher.badge_label + ')'"></div>
                        <div class="text-[11px] text-text-muted" x-text="'Min. Spend \u20B1' + lastSpinResult.voucher.min_spend + ' • Expires ' + lastSpinResult.voucher.ends_at"></div>
                    </div>
                </template>

                <div class="pt-2 flex items-center justify-center gap-3">
                    <button type="button"
                            @click="spinResultModal = false"
                            class="px-6 py-2.5 rounded-xl bg-primary hover:bg-primary-dark text-white text-xs font-extrabold cursor-pointer">
                        Awesome!
                    </button>
                </div>
            </div>
        </div>
    </template>
</div>
@endsection