@extends('layouts.app', ['title' => 'Account Suspension & Appeal Portal - Storkia'])

@section('content')
@php
    $isPermanent = $sanction->isPermanentlyBanned();
    $endsAtTimestamp = $sanction->temp_ban_ends_at ? $sanction->temp_ban_ends_at->timestamp * 1000 : 0;
    $nowTimestamp = now()->timestamp * 1000;
@endphp

<div x-data="{
    endsAt: {{ $endsAtTimestamp }},
    nowMs: {{ $nowTimestamp }},
    remainingSeconds: 0,
    countdownFinished: {{ $sanction->canSubmitAppealNow() ? 'true' : 'false' }},
    initTimer() {
        this.updateClock();
        setInterval(() => {
            this.nowMs += 1000;
            this.updateClock();
        }, 1000);
    },
    updateClock() {
        if (!this.endsAt || this.nowMs >= this.endsAt) {
            this.remainingSeconds = 0;
            this.countdownFinished = true;
            return;
        }
        this.remainingSeconds = Math.max(0, Math.floor((this.endsAt - this.nowMs) / 1000));
    },
    formattedCountdown() {
        const d = Math.floor(this.remainingSeconds / 86400);
        const h = Math.floor((this.remainingSeconds % 86400) / 3600);
        const m = Math.floor((this.remainingSeconds % 3600) / 60);
        const s = this.remainingSeconds % 60;
        return `${d}d : ${String(h).padStart(2, '0')}h : ${String(m).padStart(2, '0')}m : ${String(s).padStart(2, '0')}s`;
    }
}" x-init="initTimer()" class="min-h-[calc(100vh-200px)] bg-surface-subtle/50 py-12 px-4 sm:px-6 lg:px-8 select-none">

    <div class="max-w-2xl mx-auto space-y-6">

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

        <div class="bg-surface rounded-3xl border-2 {{ $isPermanent ? 'border-red-400' : 'border-amber-300' }} p-6 sm:p-8 shadow-md space-y-5">
            <div class="flex items-start justify-between gap-4 border-b border-border-subtle pb-4">
                <div>
                    <span class="px-3 py-1 rounded-full text-[11px] font-extrabold uppercase {{ $isPermanent ? 'bg-red-100 text-danger' : 'bg-amber-100 text-amber-900' }}">
                        {{ $isPermanent ? '2nd Ban Offense — Permanent Account Ban' : '3-Strike COD Abandonment Policy (1st Ban Offense)' }}
                    </span>
                    <h1 class="text-2xl font-serif font-bold text-primary-dark mt-2">
                        {{ $isPermanent ? 'Account Permanently Banned' : 'Account Temporarily Suspended (COD Abandonment)' }}
                    </h1>
                    <p class="text-xs text-text-muted mt-1">
                        Account Holder: <strong>{{ $buyer->full_name }}</strong> ({{ $buyer->email }}) &bull;
                        COD Abandonment Strikes: <strong class="text-danger">{{ $sanction->cod_abandonment_strikes }} / 3</strong>
                    </p>
                </div>
            </div>

            @if($sanction->last_abandonment_reason)
                <div class="p-4 rounded-2xl bg-red-50 border border-red-200 text-xs space-y-1">
                    <span class="font-bold uppercase text-danger text-[10px] block">Latest Recorded COD Parcel Abandonment</span>
                    <p class="font-semibold text-red-950">"{{ $sanction->last_abandonment_reason }}"</p>
                </div>
            @endif

            @if($isPermanent)
                <div class="p-5 rounded-2xl bg-red-100/70 border border-red-300 text-xs text-red-950 space-y-2 leading-relaxed">
                    <p class="font-extrabold text-sm">Why is this ban permanent?</p>
                    <p>
                        Your account was previously reinstated after a 1st temporary suspension and appeal, but recorded another Cash on Delivery (COD) parcel abandonment. Under Storkia's Anti-Abandonment Policy, a <strong>2nd ban offense results in an immediate, permanent ban</strong> and is no longer eligible for appeal.
                    </p>
                </div>
            @else
                <div x-show="!countdownFinished" class="p-5 rounded-2xl bg-amber-50 border border-amber-300 text-center space-y-2">
                    <span class="text-[11px] font-extrabold uppercase tracking-widest text-amber-900 block">
                        7-Day Temporary Suspension Countdown Remaining
                    </span>
                    <div class="text-3xl sm:text-4xl font-mono font-extrabold text-primary-dark tracking-wider" x-text="formattedCountdown()"></div>
                    <p class="text-xs text-amber-900 pt-1">
                        Once this 7-day countdown reaches zero, your account will <strong>not</strong> reactivate automatically. The <strong>Reactivation Appeal Form</strong> below will unlock so you can submit an appeal for Admin review.
                    </p>
                </div>

                <div x-show="countdownFinished" x-cloak class="space-y-4">
                    <div class="p-4 rounded-2xl bg-blue-50 border border-blue-200 text-xs text-blue-950 leading-relaxed">
                        <strong>7-Day Suspension Period Completed:</strong> To reactivate your account, please submit an appeal below. Storkia Admin will review your explanation before restoring marketplace access. Please note that if your account is reactivated and you abandon another COD order in the future, the <strong>2nd ban will be a Permanent Ban</strong>.
                    </div>

                    @if($latestAppeal && $latestAppeal->status === 'pending')
                        <div class="p-5 rounded-2xl bg-green-50 border border-green-200 text-xs text-green-900 space-y-1">
                            <span class="font-extrabold uppercase text-[10px] text-green-700 block">Appeal Status: Pending Admin Review</span>
                            <p class="font-semibold">You submitted your reactivation appeal on {{ $latestAppeal->created_at?->format('M d, Y h:i A') }}.</p>
                            <p class="italic text-text-muted mt-1">"{{ $latestAppeal->appeal_explanation }}"</p>
                        </div>
                    @else
                        @if($latestAppeal && $latestAppeal->status === 'rejected')
                            <div class="p-4 rounded-2xl bg-red-50 border border-red-200 text-xs text-red-900">
                                <strong>Previous Appeal Declined by Admin:</strong> "{{ $latestAppeal->admin_remarks }}" — You may submit a revised appeal below.
                            </div>
                        @endif

                        <form action="{{ route('buyer.suspended.appeal') }}" method="POST" enctype="multipart/form-data" class="space-y-4 text-xs pt-2">
                            @csrf
                            <div>
                                <label class="block font-bold text-text-muted uppercase tracking-wider mb-1.5">
                                    Reactivation Appeal Explanation (Required) <span class="text-danger">*</span>
                                </label>
                                <textarea name="appeal_explanation"
                                          rows="4"
                                          required
                                          minlength="15"
                                          maxlength="1500"
                                          placeholder="Explain why you missed or failed to receive your COD deliveries and confirm that you will honor future orders..."
                                          class="w-full p-3.5 rounded-2xl border border-border-subtle bg-surface outline-none focus:border-primary"></textarea>
                            </div>

                            <div>
                                <label class="block font-bold text-text-muted uppercase tracking-wider mb-1.5">
                                    Supporting Document / Photo (Optional, Max 5MB)
                                </label>
                                <input type="file"
                                       name="supporting_photo"
                                       accept="image/jpeg,image/png,image/jpg,image/webp"
                                       class="w-full p-2.5 rounded-xl border border-border-subtle bg-surface file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:bg-primary/10 file:text-primary file:font-bold cursor-pointer">
                            </div>

                            <div class="flex justify-end">
                                <button type="submit" class="px-6 py-3 rounded-2xl bg-primary hover:bg-primary-dark text-white font-bold shadow-sm transition-colors cursor-pointer">
                                    Submit Appeal for Admin Review
                                </button>
                            </div>
                        </form>
                    @endif
                </div>
            @endif

            <div class="pt-4 border-t border-border-subtle flex items-center justify-between text-xs">
                <a href="{{ route('home') }}" class="text-text-muted hover:text-primary font-bold">&larr; Return to Storefront</a>
                <a href="{{ route('login') }}" class="text-primary font-bold hover:underline">Back to Sign In</a>
            </div>
        </div>

    </div>
</div>
@endsection