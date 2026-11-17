@extends('buyer.account-settings', [
    'pageTitle' => 'Order #' . $sellerOrder->seller_order_number,
    'pageSubtitle' => 'Live driver GPS map tracking, in-app rider chat, official E-Invoice, and full order timeline.'
])

@section('option-content')
@php
    $deliverySnap = $sellerOrder->delivery_address_snapshot ?? [];
    $pickupSnap = $sellerOrder->pickup_address_snapshot ?? [];
    $shipment = $sellerOrder->shipment;
    $packages = $shipment?->packages ?? collect();
    $latestProof = $shipment?->latestDeliveryProof
        ?? $shipment?->deliveryProofs?->first()
        ?? \App\Models\DeliveryProof::with('rider')->where('seller_order_id', $sellerOrder->id)->latest('id')->first();
    $payMethod = $sellerOrder->effective_payment_method;
    $paymentSubmission = $sellerOrder->paymentSubmission;
    $extra = $sellerOrder->extra;

    $sellerOrder->loadMissing('returnRequest');
    $returnRequest = $sellerOrder->returnRequest;

    $canBuyerCancel = $sellerOrder->canBuyerCancelPreShipment();
    $isRated = $sellerOrder->isRatedByBuyer();

    $identityVerification = auth()->user()?->identityVerification;
    $identityStatus = $identityVerification?->status ?? 'unsubmitted';
    $isIdentityVerified = $isIdentityVerified ?? ($identityStatus === 'verified');

    $proofImageUrl = $latestProof ? route('user.orders.proof-image', $latestProof->id) : '';
    $invoiceViewUrl = route('user.orders.invoice', ['sellerOrder' => $sellerOrder->id, 'print' => 0]);
    $invoicePrintUrl = route('user.orders.invoice', ['sellerOrder' => $sellerOrder->id, 'print' => 1]);
    $invoiceDownloadUrl = route('user.orders.invoice', ['sellerOrder' => $sellerOrder->id, 'download' => 1]);

    $deliveryChatMessages = $deliveryChatMessages ?? collect();
    $isRiderChatOpenable = $shipment && $shipment->current_rider_id && in_array($shipment->status, ['rider_assigned', 'out_for_delivery', 'delivery_failed'], true);

    $stepRank = match($sellerOrder->status) {
        'pending_seller_acceptance', 'pending_buyer_response' => 1,
        'accepted', 'preparing', 'ready_for_pickup', 'pickup_assigned' => 2,
        'picked_up', 'at_logistics_hub' => 3,
        'out_for_delivery', 'delivery_failed' => 4,
        'delivered', 'completed' => 5,
        default => 0,
    };

    $trackingEvents = collect();

    if ($shipment && $shipment->trackingLogs && $shipment->trackingLogs->isNotEmpty()) {
        foreach ($shipment->trackingLogs as $tLog) {
            $trackingEvents->push((object) [
                'title'       => $tLog->title,
                'description' => $tLog->description,
                'location'    => $tLog->location,
                'actor'       => $tLog->actor_type,
                'occurred_at' => $tLog->occurred_at ?? $tLog->created_at,
                'category'    => 'Shipment',
            ]);
        }
    }

    if ($sellerOrder->statusHistories && $sellerOrder->statusHistories->isNotEmpty()) {
        foreach ($sellerOrder->statusHistories as $hist) {
            $statusTitle = match($hist->to_status) {
                'pending_seller_acceptance' => 'Order Placed — Waiting for Seller Acceptance',
                'pending_buyer_response'    => 'Payment Flagged — Awaiting Buyer Response',
                'accepted', 'preparing'     => 'Seller Accepted Order & Preparing Package',
                'ready_for_pickup'          => 'Package Packed & Ready for Logistics Handover',
                'pickup_assigned'           => 'First-Mile Courier Assigned for Pickup',
                'picked_up'                 => 'Package Picked Up by Courier',
                'at_logistics_hub'          => 'Arrived at Logistics Sorting Hub',
                'out_for_delivery'          => 'Out for Last-Mile Delivery',
                'delivered'                 => 'Package Delivered to Recipient',
                'completed'                 => 'Order Completed & Confirmed',
                'cancelled'                 => 'Order Cancelled',
                'rejected'                  => 'Order Auto-Rejected',
                'disputed'                  => 'Return / Refund Request Update',
                'returned_to_seller'        => $returnRequest ? 'Return / Refund Settled' : 'Returned to Seller (RTS)',
                default                     => 'Status Updated: ' . ucwords(str_replace('_', ' ', (string) $hist->to_status)),
            };

            $trackingEvents->push((object) [
                'title'       => $statusTitle,
                'description' => $hist->remarks,
                'location'    => null,
                'actor'       => $hist->actor_type,
                'occurred_at' => $hist->created_at,
                'category'    => 'Order',
            ]);
        }
    }

    if ($trackingEvents->isEmpty()) {
        $trackingEvents->push((object) [
            'title'       => 'Order Placed Successfully',
            'description' => 'Your order #' . $sellerOrder->seller_order_number . ' has been sent to ' . ($sellerOrder->sellerProfile->business_name ?? 'the seller') . '.',
            'location'    => ($deliverySnap['municipality'] ?? '') . ', ' . ($deliverySnap['province'] ?? ''),
            'actor'       => 'Buyer',
            'occurred_at' => $sellerOrder->created_at,
            'category'    => 'Order',
        ]);
    }

    $trackingEvents = $trackingEvents
        ->sortByDesc(fn ($ev) => $ev->occurred_at ? $ev->occurred_at->timestamp : 0)
        ->values();
@endphp

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<script>
    window.storkiaBuyerOrderShowState = function () {
        return {
            cancelModalOpen: false,
            cancelFlaggedModalOpen: false,
            proofModalOpen: false,
            gcashScreenshotModalOpen: false,
            riderChatModalOpen: false,
            idRequiredModalOpen: false,
            isSubmittingCancel: false,
            isPrintingInvoice: false,
            cancelReason: '',
            cancelReasonDropdownOpen: false,
            cancelReasonOptions: [
                'Changed my mind',
                'Need to change delivery address',
                'Forgot to apply voucher code',
                'Ordered wrong item or variation'
            ],
            cancelNotes: '',
            cancelError: '',
            showAllTracking: false,
            showAllOrderItems: false,
            liveMap: null,
            driverMarker: null,
            destMarker: null,
            hubMarker: null,
            routeLine: null,
            liveTrackingData: null,
            isFetchingMap: false,
            _wsListener: null,
            chatMessages: @js($deliveryChatMessages->map(fn ($m) => [
                'id'          => (int) $m->id,
                'sender_type' => $m->sender_type,
                'message'     => $m->message,
                'created_at'  => $m->created_at?->timezone('Asia/Manila')->format('M d, h:i A'),
            ])->values()),
            chatInput: '',
            chatError: '',
            isSendingChat: false,
            canSendChat: {{ $isRiderChatOpenable ? 'true' : 'false' }},

            notify(message, type = 'error') {
                if (!message) return;
                if (window.StorkiaLive && typeof window.StorkiaLive.showToast === 'function') {
                    window.StorkiaLive.showToast(message, type === 'danger' ? 'error' : type);
                    return;
                }
                window.dispatchEvent(new CustomEvent('toast', {
                    detail: {
                        title: type === 'danger' || type === 'error' ? 'Alert' : (type === 'warning' ? 'Notice' : 'Success'),
                        message: message,
                        type: type
                    }
                }));
            },

            initOrderShowPage() {
                if (window.StorkiaRealtime && typeof window.StorkiaRealtime.subscribePrivate === 'function') {
                    window.StorkiaRealtime.subscribePrivate('order.{{ (int) $sellerOrder->id }}');
                    @if($shipment)
                        window.StorkiaRealtime.subscribePrivate('shipment.{{ (int) $shipment->id }}');
                    @endif
                }

                @if($shipment)
                    this.fetchLiveTracking();
                @endif

                this._wsListener = (event) => {
                    const detail = (event && event.detail) || {};
                    const orderId = Number(detail.seller_order_id || detail.order_id || 0);
                    if (orderId > 0 && orderId !== {{ (int) $sellerOrder->id }}) {
                        return;
                    }

                    if (detail.scope === 'location' || detail.scope === 'driver_location') {
                        const lat = Number(detail.latitude);
                        const lng = Number(detail.longitude);
                        if (Number.isFinite(lat) && Number.isFinite(lng) && this.liveTrackingData && this.liveTrackingData.driver) {
                            this.liveTrackingData.driver.latitude = lat;
                            this.liveTrackingData.driver.longitude = lng;
                            this.renderOrUpdateMap(this.liveTrackingData);
                        }
                        this.fetchLiveTracking();
                        return;
                    }

                    if (detail.scope === 'delivery_chat' && detail.message) {
                        const msg = detail.message;
                        const exists = this.chatMessages.some(m => Number(m.id) === Number(msg.id));
                        if (!exists) {
                            this.chatMessages.push({
                                id: Number(msg.id),
                                sender_type: msg.sender_type || 'rider',
                                message: msg.message || '',
                                created_at: msg.created_at_formatted || 'Just now'
                            });
                        }
                        return;
                    }

                    if (detail.scope === 'shipment' || detail.scope === 'order' || detail.scope === 'delivery' || detail.scope === 'pickup') {
                        this.fetchLiveTracking();
                        if (this.riderChatModalOpen) {
                            this.refreshRiderChat();
                        }
                    }
                };
                window.addEventListener('storkia:ws-event', this._wsListener);
            },

            destroy() {
                if (this._wsListener) {
                    window.removeEventListener('storkia:ws-event', this._wsListener);
                }
            },

            printOfficialInvoice() {
                if (this.isPrintingInvoice) return;
                this.isPrintingInvoice = true;
                this.notify('Preparing Official E-Invoice for printing...', 'info');
                try {
                    let frame = document.getElementById('storkia-hidden-invoice-print-frame');
                    if (frame) {
                        frame.remove();
                    }
                    frame = document.createElement('iframe');
                    frame.id = 'storkia-hidden-invoice-print-frame';
                    frame.style.position = 'fixed';
                    frame.style.right = '0';
                    frame.style.bottom = '0';
                    frame.style.width = '0';
                    frame.style.height = '0';
                    frame.style.border = '0';
                    frame.src = @js($invoiceViewUrl);
                    frame.onload = () => {
                        this.isPrintingInvoice = false;
                        try {
                            frame.contentWindow.focus();
                            frame.contentWindow.print();
                        } catch (err) {
                            window.open(@js($invoicePrintUrl), '_blank', 'noopener');
                        }
                    };
                    frame.onerror = () => {
                        this.isPrintingInvoice = false;
                        window.open(@js($invoicePrintUrl), '_blank', 'noopener');
                    };
                    document.body.appendChild(frame);
                } catch (e) {
                    this.isPrintingInvoice = false;
                    window.open(@js($invoicePrintUrl), '_blank', 'noopener');
                }
            },

            handleCancelOrderSubmit(event) {
                this.cancelError = '';
                if (!this.cancelReason || this.cancelReason.trim() === '') {
                    event.preventDefault();
                    this.cancelError = 'Please select a reason for cancelling this order.';
                    this.notify(this.cancelError, 'danger');
                    return;
                }
                this.isSubmittingCancel = true;
            },

            async fetchLiveTracking() {
                if (this.isFetchingMap) return;
                this.isFetchingMap = true;
                try {
                    const res = await fetch('{{ route('user.orders.live-tracking', $sellerOrder->id) }}', {
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-Skip-Session-Guard': '1'
                        }
                    });
                    const json = await res.json();
                    if (res.ok && json.success && json.data && json.data.has_shipment) {
                        this.liveTrackingData = json.data;
                        this.$nextTick(() => {
                            this.renderOrUpdateMap(json.data);
                        });
                    }
                } catch (e) {
                } finally {
                    this.isFetchingMap = false;
                }
            },

            renderOrUpdateMap(data) {
                const container = document.getElementById('storkia-buyer-live-map');
                if (!container || typeof L === 'undefined') return;

                const destLat = Number(data.destination?.latitude || 14.2786);
                const destLng = Number(data.destination?.longitude || 121.4162);
                const hubLat = Number(data.sorting_hub?.latitude || destLat);
                const hubLng = Number(data.sorting_hub?.longitude || destLng);
                const drvLat = data.driver ? Number(data.driver.latitude) : hubLat;
                const drvLng = data.driver ? Number(data.driver.longitude) : hubLng;

                if (!this.liveMap) {
                    this.liveMap = L.map(container).setView([drvLat, drvLng], 13);
                    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                        maxZoom: 19,
                        attribution: '&copy; OpenStreetMap'
                    }).addTo(this.liveMap);
                }

                if (this.destMarker) {
                    this.destMarker.setLatLng([destLat, destLng]);
                } else {
                    this.destMarker = L.circleMarker([destLat, destLng], {
                        radius: 8,
                        fillColor: '#16A34A',
                        color: '#FFFFFF',
                        weight: 2,
                        fillOpacity: 1
                    }).addTo(this.liveMap).bindPopup('Delivery Destination: ' + (data.destination?.recipient || 'Buyer'));
                }

                if (this.hubMarker) {
                    this.hubMarker.setLatLng([hubLat, hubLng]);
                } else {
                    this.hubMarker = L.circleMarker([hubLat, hubLng], {
                        radius: 7,
                        fillColor: '#5D3140',
                        color: '#FFFFFF',
                        weight: 2,
                        fillOpacity: 0.9
                    }).addTo(this.liveMap).bindPopup('Sorting Hub: ' + (data.sorting_hub?.name || 'Logistics Hub'));
                }

                if (data.driver) {
                    if (this.driverMarker) {
                        this.driverMarker.setLatLng([drvLat, drvLng]);
                    } else {
                        this.driverMarker = L.circleMarker([drvLat, drvLng], {
                            radius: 9,
                            fillColor: '#CF4173',
                            color: '#FFFFFF',
                            weight: 3,
                            fillOpacity: 1
                        }).addTo(this.liveMap).bindPopup(data.driver.role + ': ' + data.driver.name + ' (' + data.driver.plate_number + ')');
                    }

                    const targetLat = data.driver.role === 'Courier' ? hubLat : destLat;
                    const targetLng = data.driver.role === 'Courier' ? hubLng : destLng;
                    if (this.routeLine) {
                        this.routeLine.setLatLngs([[drvLat, drvLng], [targetLat, targetLng]]);
                    } else {
                        this.routeLine = L.polyline([[drvLat, drvLng], [targetLat, targetLng]], {
                            color: '#CF4173',
                            weight: 3,
                            dashArray: '6, 6'
                        }).addTo(this.liveMap);
                    }
                }
            },

            async openRiderChat() {
                this.riderChatModalOpen = true;
                this.chatError = '';
                await this.refreshRiderChat();
            },

            async refreshRiderChat() {
                try {
                    const res = await fetch('{{ route('user.orders.delivery-chat.fetch', $sellerOrder->id) }}', {
                        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                    });
                    const data = await res.json();
                    if (res.ok && data.success) {
                        this.chatMessages = data.messages || [];
                        this.canSendChat = Boolean(data.can_send);
                    }
                } catch (e) {}
            },

            async sendRiderChatMessage() {
                this.chatError = '';
                const text = (this.chatInput || '').trim();
                if (!this.canSendChat) {
                    this.chatError = 'Delivery chat is only active while a Rider is assigned and delivering your parcel.';
                    this.notify(this.chatError, 'danger');
                    return;
                }
                if (!text) {
                    this.chatError = 'Please enter a delivery note or landmark message before sending.';
                    this.notify(this.chatError, 'danger');
                    return;
                }
                if (this.isSendingChat) return;

                this.isSendingChat = true;
                try {
                    const socketId = window.Echo && typeof window.Echo.socketId === 'function' ? window.Echo.socketId() : null;
                    const headers = {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    };
                    if (socketId) {
                        headers['X-Socket-ID'] = socketId;
                    }

                    const res = await fetch('{{ route('user.orders.delivery-chat.send', $sellerOrder->id) }}', {
                        method: 'POST',
                        headers,
                        body: JSON.stringify({ message: text })
                    });
                    const data = await res.json();
                    if (res.ok && data.success && data.data) {
                        const exists = this.chatMessages.some(m => Number(m.id) === Number(data.data.id));
                        if (!exists) {
                            this.chatMessages.push(data.data);
                        }
                        this.chatInput = '';
                    } else {
                        const msg = data.message || 'Unable to send message to Rider.';
                        this.chatError = msg;
                        this.notify(msg, 'danger');
                    }
                } catch (e) {
                    this.chatError = 'Network error while sending message to Rider.';
                    this.notify(this.chatError, 'danger');
                } finally {
                    this.isSendingChat = false;
                }
            }
        };
    };
</script>

<div x-data="storkiaBuyerOrderShowState()"
     x-init="initOrderShowPage()"
     class="space-y-6 select-none w-full min-w-0 max-w-full overflow-x-hidden">

    <div class="flex flex-wrap items-center justify-between gap-3">
        <a href="{{ route('user.orders') }}" class="inline-flex items-center gap-1.5 text-xs font-bold text-text-muted hover:text-primary transition-colors cursor-pointer">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
            </svg>
            <span>Back to My Orders</span>
        </a>

        <div class="flex flex-wrap items-center gap-2">
            <button type="button"
                    @click.stop="printOfficialInvoice()"
                    :disabled="isPrintingInvoice"
                    class="px-3.5 py-1 rounded-full text-xs font-bold bg-surface border border-border-subtle hover:border-primary text-primary-dark shadow-2xs transition-colors inline-flex items-center gap-1.5 cursor-pointer disabled:opacity-60">
                <svg class="w-3.5 h-3.5 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                </svg>
                <span x-text="isPrintingInvoice ? 'Preparing Print...' : 'Print Official E-Invoice'"></span>
            </button>

            <a href="{{ $invoiceDownloadUrl }}"
               data-no-live
               download="INV-{{ str_replace('SO-', '', $sellerOrder->seller_order_number) }}.pdf"
               class="px-3.5 py-1 rounded-full text-xs font-bold bg-primary/10 border border-primary/25 hover:bg-primary hover:text-white text-primary-dark shadow-2xs transition-colors inline-flex items-center gap-1.5 cursor-pointer">
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                <span>Download PDF Invoice</span>
            </a>

            @if($shipment && $shipment->currentRider)
                <button type="button"
                        @click.stop="openRiderChat()"
                        class="px-3.5 py-1 rounded-full text-xs font-bold bg-primary-dark hover:bg-primary text-white shadow-xs transition-colors cursor-pointer inline-flex items-center gap-1.5">
                    <span>Chat with Rider ({{ $shipment->currentRider->first_name }})</span>
                </button>
            @endif

            @if($latestProof)
                <button type="button"
                        @click.stop="proofModalOpen = true"
                        class="px-3 py-1 rounded-full text-xs font-bold bg-primary text-white hover:bg-primary-dark shadow-xs transition-colors cursor-pointer inline-flex items-center gap-1.5">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                    </svg>
                    <span>Inspect Delivery Photo</span>
                </button>
            @endif

            <span class="px-2.5 py-1 rounded-full text-[11px] font-bold uppercase {{ $payMethod === 'WALLET' || $payMethod === 'GCASH' ? 'bg-primary/15 text-primary-dark border border-primary/25' : 'bg-surface-subtle text-text-main border border-border-subtle' }}">
                {{ $payMethod }} ({{ strtoupper($sellerOrder->payment_status) }})
            </span>

            <span class="px-3 py-1 rounded-full text-xs font-bold bg-brand-light/50 text-primary-dark border border-border-subtle">
                {{ $sellerOrder->status_label }}
            </span>
        </div>
    </div>

    @if($sellerOrder->status === 'pending_seller_acceptance' && $sellerOrder->acceptance_deadline_at)
        <div class="p-4 sm:p-5 rounded-2xl bg-brand-light/25 border border-primary/25 flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-xs">
            <div class="space-y-0.5">
                <div class="flex items-center gap-2">
                    <span class="px-2 py-0.5 rounded-full bg-primary text-white text-[10px] font-extrabold uppercase">24h Seller Response Protection</span>
                    <strong class="text-primary-dark">Awaiting Seller Acceptance</strong>
                </div>
                <p class="text-text-muted">
                    The seller has until <strong>{{ $sellerOrder->acceptance_deadline_at->format('M d, Y h:i A') }}</strong> ({{ $sellerOrder->acceptance_deadline_at->diffForHumans() }}) to accept your order. If they fail to respond in time, the order auto-cancels and you automatically earn <strong>+50 Storkia Coins</strong> in <a href="{{ route('user.points') }}" class="text-primary font-bold underline cursor-pointer">My Coins</a>!
                </p>
            </div>
            <a href="{{ route('user.points') }}" class="px-4 py-2 rounded-xl bg-primary/10 hover:bg-primary text-primary hover:text-white font-bold transition-colors shrink-0 self-start sm:self-auto cursor-pointer">
                View My Coins &rarr;
            </a>
        </div>
    @endif

    @if($shipment && in_array($shipment->status, ['courier_assigned', 'picked_up_from_seller', 'in_transit_to_hub', 'received_at_hub', 'rider_assigned', 'out_for_delivery', 'delivery_failed'], true))
        <div class="bg-surface rounded-3xl border border-border-subtle p-5 sm:p-6 shadow-xs space-y-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <div class="flex items-center gap-2">
                        <span class="w-2.5 h-2.5 rounded-full bg-success animate-ping"></span>
                        <span class="text-[10px] font-extrabold uppercase tracking-wider text-primary">Live GPS Parcel &amp; Driver Map</span>
                    </div>
                    <h3 class="text-base font-bold text-primary-dark mt-0.5">
                        Real-Time Route Tracking (<span class="font-mono">{{ $shipment->tracking_number }}</span>)
                    </h3>
                </div>
                <div class="flex flex-wrap items-center gap-2 text-xs">
                    <template x-if="liveTrackingData && liveTrackingData.driver">
                        <div class="px-3 py-1.5 rounded-xl bg-surface-subtle border border-border-subtle flex items-center gap-2">
                            <span class="font-bold text-primary-dark" x-text="liveTrackingData.driver.role + ': ' + liveTrackingData.driver.name"></span>
                            <span class="font-mono text-[11px] text-text-muted" x-text="'(' + liveTrackingData.driver.plate_number + ')'"></span>
                        </div>
                    </template>
                    <template x-if="liveTrackingData && liveTrackingData.eta_minutes">
                        <span class="px-3 py-1.5 rounded-xl bg-primary text-white font-bold text-xs"
                              x-text="'Est. ' + liveTrackingData.eta_minutes + ' min away'"></span>
                    </template>
                    @if($shipment->currentRider)
                        <button type="button"
                                @click="openRiderChat()"
                                class="px-3.5 py-1.5 rounded-xl bg-primary-dark hover:bg-primary text-white font-bold text-xs transition-colors cursor-pointer">
                            Message Rider
                        </button>
                    @endif
                </div>
            </div>
            <div id="storkia-buyer-live-map" class="w-full h-72 rounded-2xl border border-border-subtle overflow-hidden z-10"></div>
        </div>
    @endif

    @if($sellerOrder->status === 'completed')
        <div class="p-5 rounded-3xl {{ $isRated ? 'bg-success/10 border-2 border-success/25' : 'bg-brand-light/35 border-2 border-primary/30' }} flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                @if(!$isRated)
                    <span class="px-2.5 py-0.5 rounded-full bg-primary text-white text-[10px] font-extrabold uppercase">Next Step: Rate Your Order (+Up to 10 Coins/Item)</span>
                    <h3 class="text-base font-bold text-primary-dark mt-1">Order Received! Rate Product, Condition, Seller, Logistics &amp; Rider</h3>
                    <p class="text-xs text-text-main mt-0.5">
                        Leave your 5-point rating to earn <strong>+5 to +10 Storkia Coins per item</strong> and unlock the <strong>Buy Again</strong> button.
                    </p>
                @else
                    <span class="px-2.5 py-0.5 rounded-full bg-success/20 text-success text-[10px] font-extrabold uppercase">Order Completed &amp; Rated ({{ number_format((float) $sellerOrder->serviceRating->overall_average_rating, 1) }} &#9733;)</span>
                    <h3 class="text-base font-bold text-primary-dark mt-1">Thank You for Rating This Order!</h3>
                    <p class="text-xs text-text-main mt-0.5">
                        Your ratings for the product, packaging condition, seller, logistics hub, and rider have been recorded.
                    </p>
                @endif
            </div>

            <div class="flex flex-wrap items-center gap-2 shrink-0">
                <a href="{{ $invoiceDownloadUrl }}"
                   data-no-live
                   download="INV-{{ str_replace('SO-', '', $sellerOrder->seller_order_number) }}.pdf"
                   class="px-4 py-3 rounded-2xl bg-surface border border-border-subtle hover:border-primary text-primary-dark text-xs font-bold shadow-2xs transition-colors cursor-pointer">
                    Download E-Invoice (PDF)
                </a>
                @if($latestProof)
                    <button type="button"
                            @click.stop="proofModalOpen = true"
                            class="px-4 py-3 rounded-2xl bg-surface border border-border-subtle hover:border-primary text-primary-dark text-xs font-bold shadow-2xs transition-colors cursor-pointer">
                        Inspect Delivery Photo
                    </button>
                @endif
                @if(!$isRated)
                    <a href="{{ route('user.orders.rate.show', $sellerOrder->id) }}"
                       class="inline-flex items-center gap-2 px-5 py-3 rounded-2xl bg-primary hover:bg-primary-dark text-white text-xs font-extrabold shadow-sm transition-colors cursor-pointer">
                        &#9733; Rate &amp; Earn Coins
                    </a>
                @else
                    <form action="{{ route('user.orders.buy-again', $sellerOrder->id) }}" method="POST" class="m-0">
                        @csrf
                        <button type="submit" class="px-6 py-3 rounded-2xl bg-primary hover:bg-primary-dark text-white text-xs font-bold shadow-sm transition-colors cursor-pointer">
                            Buy Again
                        </button>
                    </form>
                @endif
            </div>
        </div>
    @endif

    @if($sellerOrder->status === 'pending_buyer_response')
        <div class="p-6 rounded-3xl bg-brand-light/35 border-2 border-primary/30 space-y-4">
            <div class="flex flex-wrap items-center justify-between gap-2 pb-3 border-b border-border-subtle">
                <div>
                    <span class="px-2.5 py-0.5 rounded-full bg-primary-dark text-white text-[10px] font-extrabold uppercase">
                        Action Required — Seller Flagged Online Payment
                    </span>
                    <h3 class="text-base font-bold text-primary-dark mt-1">
                        Do You Want to Continue This Transaction via Cash on Delivery (COD)?
                    </h3>
                </div>
                @if($paymentSubmission)
                    <button type="button"
                            @click.stop="gcashScreenshotModalOpen = true"
                            class="px-3.5 py-2 rounded-xl bg-surface border border-border-subtle hover:border-primary text-primary-dark text-xs font-bold cursor-pointer">
                        View Previous Payment Screenshot
                    </button>
                @endif
            </div>

            <div class="p-4 rounded-2xl bg-surface border border-border-subtle space-y-2 text-xs">
                <p class="text-text-main font-semibold">
                    Seller Report Reason: <em class="text-danger">"{{ $paymentSubmission?->suspicious_reason ?? 'Submitted payment details could not be verified.' }}"</em>
                </p>
            </div>

            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pt-1">
                <p class="text-xs text-text-main font-semibold leading-relaxed">
                    Continuing this transaction will switch the payment method to <strong>Cash on Delivery (COD &#8369;{{ number_format((float) $sellerOrder->total_amount, 2) }})</strong> so the Seller can accept and ship your order. Or you may cancel the order immediately.
                </p>
                <div class="flex flex-wrap items-center gap-2.5 shrink-0">
                    <form action="{{ route('user.orders.switch-to-cod', $sellerOrder->id) }}" method="POST" class="m-0">
                        @csrf
                        <button type="submit" class="px-5 py-2.5 rounded-xl bg-primary hover:bg-primary-dark text-white text-xs font-bold shadow-sm transition-colors cursor-pointer">
                            Yes, Switch to COD &amp; Continue
                        </button>
                    </form>

                    <button type="button"
                            @click="cancelFlaggedModalOpen = true"
                            class="px-4 py-2.5 rounded-xl bg-surface border border-danger/30 hover:bg-danger/10 text-danger text-xs font-bold transition-colors cursor-pointer">
                        Cancel Order Immediately
                    </button>
                </div>
            </div>
        </div>
    @endif

    @if($canBuyerCancel && $sellerOrder->status !== 'pending_buyer_response')
        <div class="p-4 sm:px-5 rounded-2xl bg-surface border border-border-subtle flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-xs">
            <div>
                <span class="font-bold text-text-main">Need to cancel this order before shipment?</span>
                <p class="text-text-muted mt-0.5">
                    Orders can be cancelled before the Seller marks the package Ready for Pickup. Any E-Wallet payment or Storkia Coins used will be refunded immediately.
                    (Remaining 7-day buyer cancellation quota: <strong>{{ $cancellationQuota['remaining_buyer'] }} of {{ $cancellationQuota['limit_by_buyer'] }}</strong>)
                </p>
            </div>
            <button type="button"
                    @click.stop="cancelReason = ''; cancelNotes = ''; cancelError = ''; cancelModalOpen = true"
                    class="px-4 py-2.5 rounded-xl border border-danger/30 bg-surface hover:bg-danger/10 text-danger font-bold transition-colors cursor-pointer shrink-0 self-start sm:self-auto">
                Cancel Order
            </button>
        </div>
    @endif

    @if($sellerOrder->status === 'delivered' && !$sellerOrder->has_unresolved_dispute && !$returnRequest)
        <div class="p-5 rounded-2xl bg-brand-light/30 border border-primary/25 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <div class="flex flex-wrap items-center gap-2">
                    <h3 class="text-sm font-bold text-primary-dark">Package Delivered — Confirm Receipt or Request Return &amp; Refund</h3>
                    @if($isIdentityVerified)
                        <span class="px-2 py-0.5 rounded-full bg-success/15 text-success text-[10px] font-bold">Valid ID Verified &#10003;</span>
                    @elseif($identityStatus === 'pending')
                        <span class="px-2 py-0.5 rounded-full bg-brand-light text-primary-dark text-[10px] font-bold">ID Under Admin Review (Required for Refund)</span>
                    @elseif($identityStatus === 'failed')
                        <span class="px-2 py-0.5 rounded-full bg-danger/10 text-danger text-[10px] font-bold">ID Verification Rejected (Resubmit for Refund)</span>
                    @else
                        <span class="px-2 py-0.5 rounded-full bg-brand-light text-primary-dark text-[10px] font-bold">Valid ID Required for Refund</span>
                    @endif
                </div>
                <p class="text-xs text-text-main mt-1 leading-relaxed">
                    Please inspect your items. If you don't confirm or report a problem by
                    <strong>{{ $sellerOrder->confirmation_deadline_at?->format('M d, Y h:i A') }}</strong>
                    ({{ $sellerOrder->confirmation_deadline_at?->diffForHumans() }}),
                    this order will automatically complete and release commission earnings.
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-2.5 shrink-0">
                @if($latestProof)
                    <button type="button"
                            @click.stop="proofModalOpen = true"
                            class="px-4 py-2.5 rounded-xl border border-primary/30 bg-surface hover:bg-brand-light/40 text-primary-dark text-xs font-bold transition-colors cursor-pointer">
                        Inspect Delivery Photo
                    </button>
                @endif
                @if($isIdentityVerified)
                    <a href="{{ route('user.orders.return.create', $sellerOrder->id) }}"
                       class="px-4 py-2.5 rounded-xl border border-danger/30 bg-surface hover:bg-danger/10 text-danger text-xs font-bold transition-colors cursor-pointer">
                        Request Return / Refund
                    </a>
                @else
                    <button type="button"
                            @click.stop="idRequiredModalOpen = true; notify('Valid Government ID verification is required before requesting a Return & Refund.', 'warning')"
                            class="px-4 py-2.5 rounded-xl border border-primary/40 bg-surface hover:bg-brand-light/40 text-primary-dark text-xs font-bold transition-colors cursor-pointer">
                        Request Return / Refund (Verify ID)
                    </button>
                @endif
                <form action="{{ route('user.orders.confirm-receipt', $sellerOrder->id) }}" method="POST" class="m-0">
                    @csrf
                    <button type="submit" class="px-5 py-2.5 rounded-xl bg-primary hover:bg-primary-dark text-white text-xs font-bold shadow-xs transition-colors cursor-pointer">
                        Confirm Order Received
                    </button>
                </form>
            </div>
        </div>
    @endif

    @if($returnRequest)
        @php
            $rrStatus = $returnRequest->status;
            $rrTone = match ($rrStatus) {
                'refunded'                                 => 'bg-success/10 border-success/25 text-text-main',
                'rejected', 'closed', 'rejected_by_seller' => 'bg-danger/10 border-danger/25 text-danger',
                default                                    => 'bg-brand-light/35 border-primary/25 text-primary-dark',
            };
            $rrBadge = match ($rrStatus) {
                'refunded'                                 => 'bg-success text-white',
                'rejected', 'closed', 'rejected_by_seller' => 'bg-danger text-white',
                default                                    => 'bg-primary text-white',
            };
            $rrHint = match ($rrStatus) {
                'pending_seller_response'      => 'Waiting for the seller to respond (until ' . $returnRequest->seller_response_deadline_at?->format('M d, Y h:i A') . ').',
                'rejected_by_seller'           => 'The seller declined your request. You can escalate it to Storkia Admin for a final decision.',
                'escalated'                    => 'Storkia Admin is reviewing your case.',
                'return_in_transit'            => 'Return approved. Keep the item packed and ready for the rider.',
                'awaiting_seller_confirmation' => 'Your item reached the seller. Your refund follows once they confirm.',
                'receipt_disputed'             => 'The returned item is under Storkia Admin review.',
                'refund_pending'               => 'Your refund is queued for payout.',
                'refunded'                     => $returnRequest->isWalletRefund() ? 'Your refund was credited instantly to your Storkia E-Wallet.' : 'Your refund was sent to your GCash.',
                default                        => 'This request has ended and the order completed without a refund.',
            };
            $rrLinkLabel = $rrStatus === 'rejected_by_seller' ? 'Review & Escalate' : 'View Status & Tracking';
        @endphp
        <div class="p-5 rounded-2xl border {{ $rrTone }} flex flex-col sm:flex-row sm:items-center justify-between gap-4 text-xs">
            <div class="space-y-1.5">
                <div class="flex flex-wrap items-center gap-2">
                    <h3 class="text-sm font-bold">Return / Refund Request</h3>
                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-extrabold uppercase {{ $rrBadge }}">{{ $returnRequest->status_label }}</span>
                </div>
                <p class="font-semibold">
                    {{ $returnRequest->request_number }} &bull; {{ $returnRequest->resolution_label }} &bull; &#8369;{{ number_format((float) $returnRequest->refund_amount, 2) }}
                </p>
                <p class="leading-relaxed">{{ $rrHint }}</p>
            </div>
            <a href="{{ route('user.returns.show', $returnRequest->id) }}"
               class="px-4 py-2.5 rounded-xl bg-surface border border-border-subtle hover:border-primary text-primary-dark font-bold text-center shrink-0 transition-colors cursor-pointer">
                {{ $rrLinkLabel }} &rarr;
            </a>
        </div>
    @elseif($sellerOrder->has_unresolved_dispute || $sellerOrder->status === 'disputed' || $extra?->seller_return_decision)
        <div class="p-5 rounded-2xl bg-danger/10 border border-danger/25 space-y-2 text-xs">
            <h3 class="text-sm font-bold text-danger">Return / Refund Dispute</h3>
            <p class="text-text-main">
                Reported on {{ $sellerOrder->dispute_reported_at?->format('M d, Y h:i A') }}:
                <em>"{{ $sellerOrder->dispute_reason }}"</em>
            </p>
            @if($extra?->seller_return_decision)
                <p class="text-primary-dark font-semibold">
                    Seller decision: {{ ucfirst($extra->seller_return_decision) }}
                    @if($extra->seller_return_decision_reason)
                        &mdash; "{{ $extra->seller_return_decision_reason }}"
                    @endif
                </p>
            @endif
        </div>
    @endif

    <div class="bg-surface rounded-2xl border border-border-subtle p-5 sm:p-6 shadow-xs space-y-6">
        <div class="flex flex-wrap items-center justify-between gap-2 pb-4 border-b border-border-subtle">
            <div>
                <h3 class="text-sm sm:text-base font-bold text-primary-dark">Order &amp; Shipment Tracking</h3>
                <p class="text-xs text-text-muted mt-0.5">
                    @if($shipment)
                        Tracking Number: <strong class="font-mono text-primary-dark">{{ $shipment->tracking_number }}</strong>
                        &bull; Status: <strong class="text-primary">{{ $shipment->status_label }}</strong>
                    @else
                        Current Stage: <strong class="text-primary">{{ $sellerOrder->status_label }}</strong>
                    @endif
                </p>
            </div>

            @if($shipment && $shipment->currentRider)
                <div class="px-3 py-1.5 rounded-xl bg-surface-subtle border border-border-subtle text-xs flex items-center gap-2">
                    <span class="text-text-muted">Assigned Rider:</span>
                    <strong class="text-text-main">{{ $shipment->currentRider->full_name }}</strong>
                    @if($shipment->currentRider->plate_number)
                        <span class="font-mono text-[11px] text-primary-dark">({{ $shipment->currentRider->plate_number }})</span>
                    @endif
                </div>
            @endif
        </div>

        @if($stepRank > 0)
            <div class="grid grid-cols-5 gap-2 text-center text-[11px]">
                @foreach([
                    1 => 'Order Placed',
                    2 => 'Seller Preparing',
                    3 => 'At Sorting Hub',
                    4 => 'Out for Delivery',
                    5 => 'Delivered',
                ] as $sNum => $sLabel)
                    <div class="flex flex-col items-center gap-1.5">
                        <div class="w-7 h-7 rounded-full flex items-center justify-center text-xs font-extrabold border-2 transition-all {{ $stepRank >= $sNum ? 'bg-primary border-primary text-white shadow-xs' : 'bg-surface-subtle border-border-subtle text-text-muted' }}">
                            @if($stepRank > $sNum)
                                &check;
                            @else
                                {{ $sNum }}
                            @endif
                        </div>
                        <span class="font-semibold leading-tight {{ $stepRank >= $sNum ? 'text-primary-dark font-bold' : 'text-text-muted' }}">
                            {{ $sLabel }}
                        </span>
                    </div>
                @endforeach
            </div>
        @endif

        <div class="relative pl-6 border-l-2 border-primary/30 space-y-5 pt-2">
            @foreach($trackingEvents as $idx => $ev)
                <div x-show="{{ $idx }} < 5 || showAllTracking"
                     x-transition
                     class="relative">
                    <span class="absolute -left-[31px] top-1 w-3.5 h-3.5 rounded-full border-2 border-surface {{ $idx === 0 ? 'bg-primary ring-4 ring-primary/20' : 'bg-border-subtle' }}"></span>
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-1">
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="px-2 py-0.5 rounded text-[9px] font-extrabold uppercase {{ $ev->category === 'Shipment' ? 'bg-primary/10 text-primary' : 'bg-surface-subtle text-text-muted border border-border-subtle' }}">
                                {{ $ev->category }}
                            </span>
                            <span class="text-xs sm:text-sm font-bold {{ $idx === 0 ? 'text-primary-dark' : 'text-text-main' }}">
                                {{ $ev->title }}
                            </span>
                        </div>
                        <span class="text-[11px] font-mono text-text-muted">
                            {{ $ev->occurred_at?->format('M d, Y h:i A') }}
                        </span>
                    </div>
                    @if($ev->description)
                        <p class="text-xs text-text-muted mt-1 leading-relaxed">{{ $ev->description }}</p>
                    @endif
                    @if($ev->location || $ev->actor)
                        <p class="text-[11px] text-text-muted/80 mt-0.5">
                            @if($ev->actor) Updated by: {{ $ev->actor }} @endif
                            @if($ev->location) &bull; {{ $ev->location }} @endif
                        </p>
                    @endif
                </div>
            @endforeach
        </div>

        @if($trackingEvents->count() > 5)
            <div class="pt-2 flex justify-center">
                <button type="button"
                        @click="showAllTracking = !showAllTracking"
                        class="inline-flex items-center gap-1.5 px-4 py-1.5 rounded-full bg-primary/10 hover:bg-primary text-primary hover:text-white border border-primary/30 text-xs font-extrabold transition-colors cursor-pointer">
                    <span x-text="showAllTracking ? 'See Less Tracking Events' : 'See All {{ $trackingEvents->count() }} Tracking Events (+' + ({{ $trackingEvents->count() }} - 5) + ' older)'"></span>
                </button>
            </div>
        @endif
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div class="p-5 rounded-2xl bg-surface border border-border-subtle space-y-2">
            <h3 class="text-xs font-bold uppercase tracking-wider text-text-muted">Delivery Address Snapshot</h3>
            <p class="text-sm font-bold text-text-main select-text">{{ $deliverySnap['recipient_name'] ?? 'Recipient' }}</p>
            <p class="text-xs text-text-muted font-mono select-text">{{ $deliverySnap['phone_number'] ?? '' }}</p>
            <p class="text-xs text-text-main leading-relaxed select-text">{{ $deliverySnap['full_address'] ?? '' }}</p>
        </div>

        <div class="p-5 rounded-2xl bg-surface border border-border-subtle space-y-2">
            <h3 class="text-xs font-bold uppercase tracking-wider text-text-muted">Shipment &amp; Payment Info</h3>
            <div class="text-xs space-y-1">
                <div class="flex justify-between gap-2">
                    <span class="text-text-muted">Seller Store:</span>
                    <span class="font-bold text-text-main text-right">{{ $sellerOrder->sellerProfile->business_name ?? 'N/A' }}</span>
                </div>
                @if($sellerOrder->sellerProfile)
                    <div class="flex justify-end -mt-0.5">
                        <button type="button"
                                @click.stop="$dispatch('open-report-seller', { sellerId: {{ $sellerOrder->sellerProfile->id }}, sellerName: @js($sellerOrder->sellerProfile->business_name), orderId: {{ $sellerOrder->id }} })"
                                class="inline-flex items-center gap-1 text-[11px] font-bold text-text-muted hover:text-danger transition-colors cursor-pointer">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z"></path>
                                <line x1="4" y1="22" x2="4" y2="15"></line>
                            </svg>
                            <span>Report this seller</span>
                        </button>
                    </div>
                @endif
                <div class="flex justify-between gap-2">
                    <span class="text-text-muted">Logistics Provider:</span>
                    <span class="font-bold text-text-main text-right">{{ $sellerOrder->logisticsProfile->business_name ?? 'Awaiting Seller Selection' }}</span>
                </div>
                <div class="flex justify-between gap-2">
                    <span class="text-text-muted">Tracking Number:</span>
                    <span class="font-mono font-bold text-primary-dark text-right select-text">{{ $shipment->tracking_number ?? 'Not generated yet' }}</span>
                </div>
                @if($packages->isNotEmpty())
                    <div class="flex justify-between gap-2">
                        <span class="text-text-muted">Package Reference(s):</span>
                        <span class="font-mono font-semibold text-text-main text-right select-text">{{ $packages->pluck('package_reference')->implode(', ') }}</span>
                    </div>
                @endif
                <div class="flex justify-between gap-2">
                    <span class="text-text-muted">Payment Method / Status:</span>
                    <span class="font-bold text-text-main uppercase text-right">{{ $payMethod }} ({{ $sellerOrder->payment_status }})</span>
                </div>
                @if($latestProof)
                    <div class="flex justify-between gap-2 pt-1 border-t border-border-subtle/50">
                        <span class="text-text-muted">Received By:</span>
                        <span class="font-bold text-success text-right">
                            {{ $latestProof->recipient_name ?: 'Recipient' }}
                            @if($latestProof->recipient_relation)
                                ({{ $latestProof->recipient_relation }})
                            @endif
                        </span>
                    </div>
                @endif
            </div>

            <div class="pt-2 border-t border-border-subtle flex flex-wrap items-center justify-between gap-2">
                <div class="flex flex-wrap items-center gap-2">
                    <button type="button"
                            @click.stop="printOfficialInvoice()"
                            :disabled="isPrintingInvoice"
                            class="px-3 py-1.5 rounded-xl bg-surface-subtle hover:bg-brand-light/50 border border-border-subtle text-primary-dark text-xs font-bold transition-colors cursor-pointer disabled:opacity-60">
                        <span x-text="isPrintingInvoice ? 'Preparing Print...' : 'Print Official E-Invoice'"></span>
                    </button>
                    <a href="{{ $invoiceDownloadUrl }}"
                       data-no-live
                       download="INV-{{ str_replace('SO-', '', $sellerOrder->seller_order_number) }}.pdf"
                       class="px-3 py-1.5 rounded-xl bg-primary/10 hover:bg-primary hover:text-white border border-primary/25 text-primary-dark text-xs font-bold transition-colors cursor-pointer">
                        Download PDF
                    </a>
                </div>
                @if($latestProof)
                    <button type="button"
                            @click.stop="proofModalOpen = true"
                            class="px-3.5 py-1.5 rounded-xl bg-primary/15 text-primary-dark hover:bg-primary hover:text-white text-xs font-bold transition-colors cursor-pointer inline-flex items-center gap-1.5">
                        <span>Inspect Delivery Photo</span>
                    </button>
                @endif
            </div>
        </div>
    </div>

    <div class="bg-surface rounded-2xl border border-border-subtle overflow-hidden">
        <div class="p-4 sm:px-5 bg-surface-subtle/60 border-b border-border-subtle">
            <h3 class="text-sm font-bold text-primary-dark">Purchased Items</h3>
        </div>
        <div class="divide-y divide-border-subtle/60">
            @foreach($sellerOrder->items as $itemIdx => $item)
                <div x-show="{{ $itemIdx }} < 4 || showAllOrderItems"
                     x-transition
                     class="p-4 sm:px-5 flex items-center justify-between gap-4">
                    <div class="flex items-center gap-3.5 min-w-0">
                        <div class="w-14 h-14 rounded-xl bg-surface-subtle border border-border-subtle overflow-hidden shrink-0 flex items-center justify-center p-1">
                            @if($item->product_image)
                                <img src="{{ asset('storage/' . ltrim($item->product_image, '/')) }}" alt="{{ $item->product_name }}" class="w-full h-full object-contain rounded-lg">
                            @endif
                        </div>
                        <div class="min-w-0">
                            <p class="text-xs sm:text-sm font-bold text-text-main truncate">{{ $item->product_name }}</p>
                            @if($item->variant_name)
                                <p class="text-xs text-text-muted">{{ implode(' / ', array_filter([$item->variant_name, $item->sub_variant_name])) }}</p>
                            @endif
                            <p class="text-xs text-text-muted">&#8369;{{ number_format((float) $item->discounted_unit_price, 2) }} &times; {{ $item->quantity }}</p>
                        </div>
                    </div>
                    <div class="text-sm font-bold text-price shrink-0">
                        &#8369;{{ number_format((float) $item->line_total, 2) }}
                    </div>
                </div>
            @endforeach
        </div>

        @if($sellerOrder->items->count() > 4)
            <div class="p-3 bg-surface-subtle/30 border-t border-border-subtle text-center">
                <button type="button"
                        @click="showAllOrderItems = !showAllOrderItems"
                        class="text-xs font-extrabold text-primary hover:underline cursor-pointer"
                        x-text="showAllOrderItems ? 'See Less Purchased Items' : 'See All {{ $sellerOrder->items->count() }} Purchased Items'"></button>
            </div>
        @endif

        <div class="p-4 sm:px-5 bg-surface-subtle/40 border-t border-border-subtle space-y-1.5 text-xs sm:text-sm">
            @if($extra && (float) $extra->store_voucher_discount > 0)
                <div class="flex justify-between text-primary font-bold">
                    <span>Store Voucher / Bundle Discount:</span>
                    <span>-&#8369;{{ number_format((float) $extra->store_voucher_discount, 2) }}</span>
                </div>
            @endif
            @if($extra && (float) ($extra->coins_discount_amount ?? 0) > 0)
                <div class="flex justify-between text-primary font-bold">
                    <span>Storkia Coins Redeemed ({{ (int) $extra->coins_used }} Coins):</span>
                    <span>-&#8369;{{ number_format((float) $extra->coins_discount_amount, 2) }}</span>
                </div>
            @endif
            @php
                $platVoucherOnly = 0.0;
                if ($extra && !empty($extra->platform_voucher_code)) {
                    $rawPlat = (float) ($extra->platform_voucher_discount ?? 0);
                    $coinsDisc = (float) ($extra->coins_discount_amount ?? 0);
                    $origSub = (float) ($extra->original_subtotal_amount ?? 0);
                    $storeDisc = (float) ($extra->store_voucher_discount ?? 0);
                    $netSub = (float) $sellerOrder->subtotal_amount;
                    if ($origSub > 0 && abs(round($origSub - $storeDisc - $rawPlat - $coinsDisc, 2) - round($netSub, 2)) < 0.02) {
                        $platVoucherOnly = round($rawPlat, 2);
                    } else {
                        $platVoucherOnly = max(0.0, round($rawPlat - $coinsDisc, 2));
                    }
                }
            @endphp
            @if($platVoucherOnly > 0)
                <div class="flex justify-between text-primary-dark font-bold">
                    <span>Platform Voucher ({{ $extra->platform_voucher_code }}):</span>
                    <span>-&#8369;{{ number_format($platVoucherOnly, 2) }}</span>
                </div>
            @endif
            <div class="flex justify-between text-text-muted">
                <span>Net Product Subtotal:</span>
                <span class="font-semibold text-text-main">&#8369;{{ number_format((float) $sellerOrder->subtotal_amount, 2) }}</span>
            </div>
            <div class="flex justify-between text-text-muted">
                <span>Shipping Fee ({{ number_format((float) $sellerOrder->total_weight, 2) }} kg):</span>
                <span class="font-semibold text-text-main">
                    &#8369;{{ number_format((float) $sellerOrder->shipping_fee, 2) }}
                    @if($extra && (float) $extra->shipping_voucher_discount > 0)
                        <span class="text-primary font-bold">(-&#8369;{{ number_format((float) $extra->shipping_voucher_discount, 2) }} {{ $extra->shipping_voucher_code }})</span>
                    @endif
                </span>
            </div>
            <div class="flex justify-between pt-2 border-t border-border-subtle text-base font-extrabold text-primary-dark">
                <span>Total Order Amount ({{ $payMethod }}):</span>
                <span class="text-price">&#8369;{{ number_format((float) $sellerOrder->total_amount, 2) }}</span>
            </div>
        </div>
    </div>

    <template x-teleport="body">
        <div x-show="cancelFlaggedModalOpen"
             x-cloak
             class="fixed inset-0 z-[205] flex items-center justify-center p-4">
            <div @click="cancelFlaggedModalOpen = false" class="absolute inset-0 bg-black/60 backdrop-blur-xs cursor-pointer"></div>
            <div @click.stop class="relative z-10 bg-surface rounded-3xl max-w-sm w-full p-6 shadow-2xl border border-border-subtle text-center space-y-4">
                <h3 class="text-base font-bold text-primary-dark">Cancel Order #{{ $sellerOrder->seller_order_number }}?</h3>
                <p class="text-xs text-text-muted leading-relaxed">
                    Cancel this order immediately? Reserved stock and any applied vouchers or Storkia Coins will be restored.
                </p>
                <form action="{{ route('user.orders.cancel-flagged-payment', $sellerOrder->id) }}" method="POST" class="flex gap-2.5 pt-2 m-0">
                    @csrf
                    <button type="button" @click="cancelFlaggedModalOpen = false" class="flex-1 py-2.5 rounded-xl bg-surface-subtle hover:bg-brand-light/30 text-text-main text-xs font-bold transition-colors cursor-pointer">
                        Keep Order
                    </button>
                    <button type="submit" class="flex-1 py-2.5 rounded-xl bg-danger hover:opacity-90 text-white text-xs font-bold transition-opacity cursor-pointer">
                        Cancel Order
                    </button>
                </form>
            </div>
        </div>
    </template>

    <template x-teleport="body">
        <div x-show="idRequiredModalOpen"
             x-cloak
             class="fixed inset-0 z-[205] flex items-center justify-center p-4">
            <div @click="idRequiredModalOpen = false" class="absolute inset-0 bg-black/60 backdrop-blur-xs cursor-pointer"></div>
            <div @click.stop class="relative z-10 bg-surface rounded-3xl max-w-md w-full p-6 shadow-2xl border border-border-subtle space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-border-subtle">
                    <div class="flex items-center gap-2">
                        <span class="px-2.5 py-0.5 rounded-full bg-brand-light text-primary-dark text-[10px] font-extrabold uppercase">Refund Protection Policy</span>
                        <h3 class="text-base font-bold text-primary-dark">Valid ID Required for Return &amp; Refund</h3>
                    </div>
                    <button type="button" @click="idRequiredModalOpen = false" class="p-1 text-text-muted hover:text-text-main cursor-pointer text-lg font-bold">&times;</button>
                </div>

                <div class="p-4 rounded-2xl bg-brand-light/30 border border-primary/25 text-xs text-text-main space-y-2 leading-relaxed">
                    <p class="font-bold text-primary-dark">
                        While you can place orders on Storkia without ID verification, submitting a Return &amp; Refund claim requires an approved Valid Government ID.
                    </p>
                    @if($identityStatus === 'pending')
                        <p>
                            Current Status: <strong>Pending Admin Review</strong>. Your submitted Government ID is currently being reviewed by Storkia Admin.
                        </p>
                    @elseif($identityStatus === 'failed')
                        <p class="text-danger font-semibold">
                            Current Status: <strong>Verification Declined</strong> ({{ $identityVerification?->rejection_reason ?: 'Please upload a clearer photo of your valid ID.' }}).
                        </p>
                    @else
                        <p>
                            Current Status: <strong>Not Verified Yet</strong>. Please upload or capture your Government ID in Account Management.
                        </p>
                    @endif
                </div>

                <div class="flex items-center justify-end gap-2 pt-2">
                    <button type="button" @click="idRequiredModalOpen = false" class="px-4 py-2.5 rounded-xl bg-surface-subtle text-text-muted font-bold text-xs cursor-pointer">
                        Close
                    </button>
                    <a href="{{ route('user.identity-verification') }}"
                       class="px-5 py-2.5 rounded-xl bg-primary hover:bg-primary-dark text-white font-bold text-xs shadow-xs transition-colors cursor-pointer">
                        {{ $identityStatus === 'pending' ? 'View ID Verification Status' : 'Verify Valid ID Now' }} &rarr;
                    </a>
                </div>
            </div>
        </div>
    </template>

    <template x-teleport="body">
        <div x-show="riderChatModalOpen"
             x-cloak
             class="fixed inset-0 z-[200] flex items-center justify-center p-4">
            <div @click="riderChatModalOpen = false" class="absolute inset-0 bg-black/60 backdrop-blur-xs cursor-pointer"></div>
            <div @click.stop class="relative z-10 bg-surface rounded-3xl max-w-md w-full p-5 shadow-2xl border border-border-subtle flex flex-col h-[520px]">
                <div class="flex items-center justify-between pb-3 border-b border-border-subtle">
                    <div>
                        <span class="px-2 py-0.5 rounded-full bg-brand-light/60 text-primary-dark text-[10px] font-extrabold uppercase">Last-Mile Delivery Chat</span>
                        <h3 class="text-sm font-bold text-primary-dark mt-0.5">
                            Rider: {{ $shipment?->currentRider?->full_name ?? 'Assigned Rider' }}
                        </h3>
                    </div>
                    <button type="button" @click="riderChatModalOpen = false" class="p-1 text-text-muted hover:text-text-main cursor-pointer text-lg font-bold">&times;</button>
                </div>

                <div class="flex-1 overflow-y-auto p-3 space-y-2.5 bg-surface-subtle/50 rounded-2xl my-3 text-xs custom-scrollbar">
                    <template x-if="chatMessages.length === 0">
                        <p class="text-center text-text-muted py-12">Send delivery instructions or landmarks to your assigned Rider.</p>
                    </template>
                    <template x-for="msg in chatMessages" :key="msg.id">
                        <div :class="msg.sender_type === 'buyer' ? 'flex justify-end' : 'flex justify-start'">
                            <div :class="msg.sender_type === 'buyer' ? 'bg-primary text-white' : 'bg-surface border border-border-subtle text-text-main'"
                                 class="max-w-[80%] px-3.5 py-2 rounded-2xl shadow-2xs space-y-0.5">
                                <p class="leading-relaxed" x-text="msg.message"></p>
                                <span class="block text-[9px] opacity-75 text-right" x-text="msg.created_at"></span>
                            </div>
                        </div>
                    </template>
                </div>

                <template x-if="canSendChat">
                    <form @submit.prevent="sendRiderChatMessage()" class="flex items-center gap-2">
                        <input type="text"
                               x-model="chatInput"
                               @input="chatError = ''"
                               maxlength="1000"
                               placeholder="Type delivery landmark or note..."
                               class="flex-1 p-2.5 rounded-xl border border-border-subtle bg-surface text-xs outline-none focus:border-primary">
                        <button type="submit"
                                :aria-disabled="isSendingChat || !chatInput.trim()"
                                :class="(isSendingChat || !chatInput.trim()) ? 'bg-primary/50 cursor-not-allowed' : 'bg-primary hover:bg-primary-dark cursor-pointer'"
                                class="px-4 py-2.5 rounded-xl text-white text-xs font-bold transition-colors">
                            <span x-text="isSendingChat ? 'Sending...' : 'Send'"></span>
                        </button>
                    </form>
                </template>
                <template x-if="!canSendChat">
                    <div class="text-center text-[11px] text-text-muted py-2">
                        Delivery chat closes automatically once the parcel is delivered.
                    </div>
                </template>
            </div>
        </div>
    </template>

    <template x-teleport="body">
        <div x-show="cancelModalOpen"
             x-cloak
             class="fixed inset-0 z-[185] flex items-center justify-center p-4">
            <div @click="cancelModalOpen = false" class="absolute inset-0 bg-black/60 backdrop-blur-xs cursor-pointer"></div>
            <div @click.stop class="relative z-10 bg-surface rounded-3xl max-w-md w-full p-6 shadow-2xl border border-border-subtle space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-border-subtle">
                    <h3 class="text-base font-bold text-primary-dark">Cancel Order #{{ $sellerOrder->seller_order_number }}</h3>
                    <button type="button" @click="cancelModalOpen = false" class="p-1 text-text-muted hover:text-text-main text-lg font-bold cursor-pointer">&times;</button>
                </div>

                <form action="{{ route('user.orders.cancel', $sellerOrder->id) }}"
                      method="POST"
                      data-no-live
                      @submit="handleCancelOrderSubmit($event)"
                      class="space-y-4 text-xs">
                    @csrf
                    <div class="relative" @click.away="cancelReasonDropdownOpen = false">
                        <label class="block font-bold text-text-muted mb-1">Select Cancellation Reason <span class="text-danger">*</span></label>
                        <input type="hidden" name="cancel_reason" :value="cancelReason" required>
                        <button type="button"
                                @click="cancelReasonDropdownOpen = !cancelReasonDropdownOpen"
                                class="w-full p-3 rounded-xl border border-border-subtle hover:border-primary bg-surface font-semibold flex items-center justify-between text-left cursor-pointer">
                            <span :class="cancelReason ? 'text-text-main font-bold' : 'text-text-muted'" x-text="cancelReason || '-- Choose a reason --'"></span>
                            <svg class="w-4 h-4 text-primary transition-transform" :class="cancelReasonDropdownOpen ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                        </button>
                        <div x-show="cancelReasonDropdownOpen" x-cloak x-transition class="absolute left-0 right-0 top-full mt-1 bg-surface border border-border-subtle rounded-2xl shadow-xl z-30 py-1 overflow-hidden">
                            <template x-for="rOpt in cancelReasonOptions" :key="rOpt">
                                <button type="button"
                                        @click="cancelReason = rOpt; cancelError = ''; cancelReasonDropdownOpen = false"
                                        class="w-full px-4 py-2.5 text-left hover:bg-brand-light/30 font-semibold flex items-center justify-between cursor-pointer"
                                        :class="cancelReason === rOpt ? 'text-primary font-bold bg-primary/5' : 'text-text-main'">
                                    <span x-text="rOpt"></span>
                                    <span x-show="cancelReason === rOpt" class="text-primary font-extrabold">&#10003;</span>
                                </button>
                            </template>
                        </div>
                    </div>

                    <div>
                        <label class="block font-bold text-text-muted mb-1">Additional Cancellation Notes <span class="font-normal italic">(Optional)</span></label>
                        <textarea name="cancel_notes"
                                  x-model="cancelNotes"
                                  rows="2"
                                  maxlength="500"
                                  placeholder="Optional details..."
                                  class="w-full p-3 rounded-xl border border-border-subtle bg-surface outline-none focus:border-primary"></textarea>
                    </div>

                    <div class="flex justify-end gap-2 pt-1">
                        <button type="button" @click="cancelModalOpen = false" class="px-4 py-2.5 rounded-xl bg-surface-subtle text-text-muted font-bold cursor-pointer">
                            Keep Order
                        </button>
                        <button type="submit"
                                :aria-disabled="isSubmittingCancel || !cancelReason"
                                :class="(isSubmittingCancel || !cancelReason) ? 'bg-danger/50 cursor-not-allowed' : 'bg-danger hover:opacity-90 cursor-pointer'"
                                class="px-5 py-2.5 rounded-xl text-white font-bold transition-opacity">
                            <span x-text="isSubmittingCancel ? 'Cancelling...' : 'Confirm Cancel'"></span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </template>

    @if($latestProof)
        <template x-teleport="body">
            <div x-show="proofModalOpen"
                 x-cloak
                 @keydown.escape.window="proofModalOpen = false"
                 class="fixed inset-0 z-[195] flex items-center justify-center p-4">
                <div @click="proofModalOpen = false" class="absolute inset-0 bg-black/75 backdrop-blur-xs cursor-pointer"></div>
                <div @click.stop class="relative z-10 bg-surface rounded-3xl max-w-xl w-full p-6 shadow-2xl border border-border-subtle space-y-4 max-h-[90vh] overflow-y-auto">
                    <div class="flex items-center justify-between pb-3 border-b border-border-subtle">
                        <div>
                            <h3 class="text-base font-bold text-primary-dark">Proof of Delivery</h3>
                            <p class="text-xs text-text-muted">Order #{{ $sellerOrder->seller_order_number }} &bull; Tracking #{{ $shipment->tracking_number ?? 'N/A' }}</p>
                        </div>
                        <button type="button" @click="proofModalOpen = false" class="p-1.5 text-text-muted hover:text-text-main cursor-pointer text-lg leading-none">&times;</button>
                    </div>

                    <div class="rounded-2xl overflow-hidden bg-surface-subtle border border-border-subtle flex items-center justify-center max-h-[55vh] p-2">
                        <img src="{{ $proofImageUrl }}"
                             alt="Proof of Delivery"
                             class="max-h-[52vh] w-auto object-contain rounded-xl shadow-xs">
                    </div>

                    <div class="p-4 rounded-2xl bg-surface-subtle border border-border-subtle grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
                        <div>
                            <span class="text-text-muted block">Received By:</span>
                            <strong class="text-text-main">
                                {{ $latestProof->recipient_name ?: ($deliverySnap['recipient_name'] ?? 'Recipient') }}
                                @if($latestProof->recipient_relation)
                                    ({{ $latestProof->recipient_relation }})
                                @endif
                            </strong>
                        </div>
                        <div>
                            <span class="text-text-muted block">Delivered At:</span>
                            <strong class="text-text-main">{{ ($latestProof->captured_at ?? $sellerOrder->delivered_at)?->format('M d, Y h:i A') ?? 'Recorded' }}</strong>
                        </div>
                        <div>
                            <span class="text-text-muted block">Delivered By Rider:</span>
                            <strong class="text-text-main">{{ $latestProof->rider?->full_name ?? ($shipment?->currentRider?->full_name ?? 'Assigned Logistics Rider') }}</strong>
                        </div>
                        <div>
                            <span class="text-text-muted block">Payment / COD Collected:</span>
                            <strong class="text-primary-dark">
                                @if((float) ($latestProof->cod_collected_amount ?? 0) > 0)
                                    &#8369;{{ number_format((float) $latestProof->cod_collected_amount, 2) }} (COD)
                                @else
                                    {{ $payMethod }} (&#8369;{{ number_format((float) $sellerOrder->total_amount, 2) }})
                                @endif
                            </strong>
                        </div>
                    </div>

                    <div class="flex items-center justify-between pt-2 border-t border-border-subtle text-xs">
                        <a href="{{ $proofImageUrl }}"
                           target="_blank"
                           data-no-live
                           class="font-bold text-primary hover:underline cursor-pointer">
                            Open Full Image in New Tab &nearr;
                        </a>
                        <button type="button"
                                @click="proofModalOpen = false"
                                class="px-5 py-2 rounded-xl bg-primary-dark hover:bg-primary text-white font-bold transition-colors cursor-pointer">
                            Done
                        </button>
                    </div>
                </div>
            </div>
        </template>
    @endif

    <x-buyer.report-seller-modal />
</div>
@endsection