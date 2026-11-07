@extends('buyer.account-settings', [
    'pageTitle' => 'Return & Refund',
    'pageSubtitle' => 'Tell us what went wrong with Order #' . $sellerOrder->seller_order_number . '.'
])

@section('option-content')
@php
    $catalogForJs = collect($reasonCatalog)->map(fn ($reason) => ['resolution' => $reason['resolution']])->all();
    $sellerResponseHours = \App\Models\ReturnRequest::SELLER_RESPONSE_HOURS;
    $refundPayoutHours = \App\Models\ReturnRequest::REFUND_PAYOUT_HOURS;
@endphp

<script>
    window.storkiaReturnRequestForm = function (config) {
        return {
            catalog: config.catalog,
            category: config.category || '',
            resolution: config.resolution || 'return_refund',
            phone: config.phone || '',
            filesError: '',
            fileNames: [],
            submitting: false,

            get refundOnlyForced() {
                return !!this.category
                    && !!this.catalog[this.category]
                    && this.catalog[this.category].resolution === 'refund_only';
            },

            sanitizePhone() {
                let digits = (this.phone || '').replace(/\D/g, '');
                if (digits.startsWith('63') && digits.length === 12) {
                    digits = '0' + digits.slice(2);
                } else if (digits.startsWith('9') && digits.length === 10) {
                    digits = '0' + digits;
                }
                this.phone = digits.slice(0, 11);
            },

            validateFiles(event) {
                this.filesError = '';
                this.fileNames = [];
                const files = Array.from(event.target.files || []);
                if (files.length < 1 || files.length > 5) {
                    this.filesError = 'Please select between 1 and 5 photos.';
                    event.target.value = '';
                    return;
                }
                for (const f of files) {
                    if (f.size > 5 * 1024 * 1024) {
                        this.filesError = 'File "' + f.name + '" is larger than 5MB.';
                        event.target.value = '';
                        this.fileNames = [];
                        return;
                    }
                    this.fileNames.push(f.name);
                }
            },

            get canSubmit() {
                return !this.submitting && !this.filesError && !!this.category;
            }
        };
    };
</script>

<div class="space-y-5"
     x-data="storkiaReturnRequestForm(@js([
         'catalog'    => $catalogForJs,
         'category'   => old('reason_category', ''),
         'resolution' => old('requested_resolution', 'return_refund'),
         'phone'      => old('refund_account_number', ''),
     ]))">

    <a href="{{ route('user.orders.show', $sellerOrder->id) }}"
       class="inline-flex items-center gap-1.5 text-xs font-bold text-text-muted hover:text-primary transition-colors">
        &larr; Back to Order #{{ $sellerOrder->seller_order_number }}
    </a>

    @if($errors->any())
        <div class="p-4 rounded-2xl bg-red-50 border border-red-200 text-red-700 text-xs sm:text-sm space-y-1">
            @foreach($errors->all() as $err)
                <p class="font-semibold">{{ $err }}</p>
            @endforeach
        </div>
    @endif

    <!-- Order summary -->
    <div class="bg-surface rounded-2xl border border-border-subtle shadow-xs overflow-hidden">
        <div class="px-5 py-3.5 border-b border-border-subtle flex flex-wrap items-center justify-between gap-2">
            <div>
                <h3 class="text-sm font-bold text-primary-dark">{{ $sellerOrder->sellerProfile?->business_name ?? 'Seller' }}</h3>
                <p class="text-xs text-text-muted">Order #{{ $sellerOrder->seller_order_number }}</p>
            </div>
            <div class="text-right">
                <p class="text-[10px] font-bold uppercase text-text-muted">You will be refunded</p>
                <p class="text-base font-extrabold text-primary">₱{{ number_format((float) $sellerOrder->total_amount, 2) }}</p>
            </div>
        </div>
        <div class="divide-y divide-border-subtle">
            @foreach($sellerOrder->items as $item)
                <div class="p-4 flex items-center justify-between gap-4">
                    <div class="flex items-center gap-3 min-w-0">
                        <div class="w-12 h-12 rounded-xl bg-surface-subtle border border-border-subtle overflow-hidden shrink-0 flex items-center justify-center p-1">
                            @if($item->product_image)
                                <img src="{{ asset('storage/' . ltrim($item->product_image, '/')) }}" alt="{{ $item->product_name }}" class="w-full h-full object-contain rounded-lg">
                            @endif
                        </div>
                        <div class="min-w-0">
                            <p class="text-xs sm:text-sm font-bold text-text-main truncate">{{ $item->product_name }}</p>
                            @if($item->variant_name)
                                <p class="text-xs text-text-muted">{{ implode(' / ', array_filter([$item->variant_name, $item->sub_variant_name])) }}</p>
                            @endif
                            <p class="text-xs text-text-muted">Qty {{ $item->quantity }}</p>
                        </div>
                    </div>
                    <div class="text-xs sm:text-sm font-bold text-text-main shrink-0">₱{{ number_format((float) $item->line_total, 2) }}</div>
                </div>
            @endforeach
        </div>
    </div>

    <!-- How it works -->
    <div class="p-4 rounded-2xl bg-blue-50 border border-blue-200 text-xs text-blue-900 space-y-1.5">
        <p class="font-bold text-sm">How a return or refund works</p>
        <ol class="list-decimal pl-4 space-y-1 leading-relaxed">
            <li>Send your request with photos. The seller has <strong>{{ $sellerResponseHours }} hours</strong> to respond.</li>
            <li>If you are sending the item back, a Storkia rider collects it from your address and it travels back to the seller.</li>
            <li>Your refund goes to your GCash within <strong>{{ $refundPayoutHours }} hours</strong> of approval (or once the seller receives the returned item).</li>
            <li>If the seller declines, you can escalate to Storkia Admin for a final decision.</li>
        </ol>
    </div>

    <form action="{{ route('user.orders.return.store', $sellerOrder->id) }}"
          method="POST"
          enctype="multipart/form-data"
          @submit="if (!canSubmit) { $event.preventDefault(); } else { submitting = true; }"
          class="bg-surface rounded-2xl border border-border-subtle p-5 sm:p-6 shadow-xs space-y-5 text-xs">
        @csrf

        <div class="p-2.5 rounded-xl bg-green-50 border border-green-200 text-green-800 font-semibold">
            ✓ Valid Government ID Verified
        </div>

        <!-- 1. What went wrong -->
        <div>
            <label class="block font-bold text-text-main text-sm mb-2">1. What went wrong? <span class="text-danger">*</span></label>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                @foreach($reasonCatalog as $label => $reason)
                    <label class="p-3 rounded-xl border cursor-pointer flex items-start gap-2.5 transition-colors"
                           :class="category === @js($label) ? 'border-primary bg-primary/5' : 'border-border-subtle hover:border-primary/40'">
                        <input type="radio" name="reason_category" value="{{ $label }}" x-model="category" class="mt-0.5" required>
                        <span>
                            <span class="block font-bold text-text-main">{{ $label }}</span>
                            <span class="block text-text-muted mt-0.5">{{ $reason['hint'] }}</span>
                        </span>
                    </label>
                @endforeach
            </div>
        </div>

        <!-- 2. What do you want -->
        <div x-show="category" x-cloak>
            <label class="block font-bold text-text-main text-sm mb-2">2. What would you like? <span class="text-danger">*</span></label>

            <div x-show="refundOnlyForced" class="p-3 rounded-xl bg-amber-50 border border-amber-200 text-amber-900 leading-relaxed">
                There is nothing to send back for this reason, so you will be <strong>refunded directly</strong> once the seller (or Storkia Admin) approves.
                <input type="hidden" name="requested_resolution" value="refund_only" :disabled="!refundOnlyForced">
            </div>

            <div x-show="!refundOnlyForced" class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                <label class="p-3 rounded-xl border cursor-pointer flex items-start gap-2.5"
                       :class="resolution === 'return_refund' ? 'border-primary bg-primary/5' : 'border-border-subtle'">
                    <input type="radio" name="requested_resolution" value="return_refund" x-model="resolution" :disabled="refundOnlyForced" class="mt-0.5">
                    <span>
                        <span class="block font-bold text-text-main">Return the item &amp; get refunded</span>
                        <span class="block text-text-muted mt-0.5">A rider collects the item from you. Your refund is released once the seller receives it.</span>
                    </span>
                </label>
                <label class="p-3 rounded-xl border cursor-pointer flex items-start gap-2.5"
                       :class="resolution === 'refund_only' ? 'border-primary bg-primary/5' : 'border-border-subtle'">
                    <input type="radio" name="requested_resolution" value="refund_only" x-model="resolution" :disabled="refundOnlyForced" class="mt-0.5">
                    <span>
                        <span class="block font-bold text-text-main">Keep the item &amp; get refunded</span>
                        <span class="block text-text-muted mt-0.5">The seller may agree, or may ask you to send the item back instead.</span>
                    </span>
                </label>
            </div>
        </div>

        <!-- 3. Describe -->
        <div>
            <label class="block font-bold text-text-main text-sm mb-1.5">3. Describe the problem (10+ characters) <span class="text-danger">*</span></label>
            <textarea name="reason"
                      rows="4"
                      required
                      minlength="10"
                      maxlength="1000"
                      placeholder="Explain what happened. The more detail, the faster the seller can respond."
                      class="w-full p-3 rounded-xl border border-border-subtle bg-surface outline-none focus:border-primary">{{ old('reason') }}</textarea>
        </div>

        <!-- 4. Evidence -->
        <div>
            <label class="block font-bold text-text-main text-sm mb-1.5">4. Upload 1 to 5 photos as evidence (max 5MB each) <span class="text-danger">*</span></label>
            <input type="file"
                   name="evidence_photos[]"
                   multiple
                   required
                   accept="image/jpeg,image/png,image/jpg,image/webp"
                   @change="validateFiles($event)"
                   class="w-full p-2.5 rounded-xl border border-border-subtle bg-surface file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:bg-primary/10 file:text-primary file:font-bold cursor-pointer">
            <template x-if="filesError">
                <p class="text-danger font-bold mt-1" x-text="filesError"></p>
            </template>
            <template x-if="fileNames.length && !filesError">
                <p class="text-text-muted mt-1" x-text="fileNames.length + ' photo(s) selected'"></p>
            </template>
        </div>

        <!-- 5. GCash refund -->
        <div>
            <label class="block font-bold text-text-main text-sm mb-1.5">5. Where should we send your refund? (GCash) <span class="text-danger">*</span></label>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                <div>
                    <label class="block font-bold text-text-muted mb-1">GCash Account Name</label>
                    <input type="text"
                           name="refund_account_name"
                           value="{{ old('refund_account_name', $defaultName) }}"
                           required
                           maxlength="120"
                           placeholder="Juan Dela Cruz"
                           class="w-full p-2.5 rounded-xl border border-border-subtle bg-surface">
                </div>
                <div>
                    <label class="block font-bold text-text-muted mb-1">GCash Mobile Number</label>
                    <input type="text"
                           name="refund_account_number"
                           x-model="phone"
                           @input="sanitizePhone()"
                           maxlength="11"
                           inputmode="numeric"
                           required
                           placeholder="09171234567"
                           class="w-full p-2.5 rounded-xl border border-border-subtle bg-surface font-mono">
                </div>
            </div>
            <p class="text-text-muted mt-1.5">Refunds are sent by GCash only. Please double-check the number: we cannot recover a refund sent to the wrong account.</p>
        </div>

        <div class="flex flex-wrap justify-end gap-2 pt-1">
            <a href="{{ route('user.orders.show', $sellerOrder->id) }}"
               class="px-4 py-2.5 rounded-xl bg-surface-subtle text-text-muted font-bold">
                Cancel
            </a>
            <button type="submit"
                    :disabled="!canSubmit"
                    class="px-5 py-2.5 rounded-xl bg-danger text-white font-bold hover:opacity-90 cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed">
                <span x-show="!submitting">Submit Request</span>
                <span x-show="submitting" x-cloak>Submitting...</span>
            </button>
        </div>
    </form>
</div>
@endsection