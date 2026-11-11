<?php

namespace App\Http\Controllers\Api\Concerns;

use App\Models\ReturnRequest;
use App\Models\ReturnShipment;

trait FormatsReturnRequests
{
    protected function returnStorageUrl(?string $path): ?string
    {
        if ($path === null || trim($path) === '') {
            return null;
        }

        if (preg_match('#^https?://#i', $path)) {
            return $path;
        }

        return asset('storage/' . ltrim($path, '/'));
    }

    protected function formatReturnRequest(ReturnRequest $rr): array
    {
        $order = $rr->sellerOrder;
        $photos = is_array($rr->evidence_photos) ? $rr->evidence_photos : [];

        return [
            'id'                          => (int) $rr->id,
            'request_number'              => $rr->request_number,
            'status'                      => $rr->status,
            'status_label'                => $rr->status_label,
            'resolution'                  => $rr->effectiveResolution(),
            'resolution_label'            => $rr->resolution_label,
            'requested_resolution'        => $rr->requested_resolution,
            'approved_resolution'         => $rr->approved_resolution,
            'reason_category'             => $rr->reason_category,
            'reason'                      => $rr->reason,
            'evidence_photos'             => array_values(array_filter(
                array_map(fn ($path) => $this->returnStorageUrl((string) $path), $photos)
            )),
            'refund_amount'               => (float) $rr->refund_amount,
            'refund_status'               => $rr->refund_status,
            'refund_account_name'         => $rr->refund_account_name,
            'refund_account_number'       => $rr->refund_account_number,
            'refund_reference'            => $rr->refund_reference,
            'refund_due_at'               => $rr->refund_due_at?->toIso8601String(),
            'refunded_at'                 => $rr->refunded_at?->toIso8601String(),
            'seller_decision'             => $rr->seller_decision,
            'seller_decision_reason'      => $rr->seller_decision_reason,
            'seller_response_deadline_at' => $rr->seller_response_deadline_at?->toIso8601String(),
            'handover_deadline_at'        => $rr->handover_deadline_at?->toIso8601String(),
            'seller_receipt_deadline_at'  => $rr->seller_receipt_deadline_at?->toIso8601String(),
            'seller_receipt_outcome'      => $rr->seller_receipt_outcome,
            'seller_receipt_notes'        => $rr->seller_receipt_notes,
            'admin_decision'              => $rr->admin_decision,
            'admin_remarks'               => $rr->admin_remarks,
            'is_escalated'                => (bool) $rr->is_escalated,
            'escalation_note'             => $rr->escalation_note,
            'can_escalate'                => $rr->canBuyerEscalate(),
            'is_terminal'                 => $rr->isTerminal(),
            'created_at'                  => $rr->created_at?->toIso8601String(),
            'next_step'                   => $this->returnNextStep($rr),
            'seller_order'                => $order ? [
                'id'                  => (int) $order->id,
                'seller_order_number' => $order->seller_order_number,
                'status'              => $order->status,
                'seller_name'         => $order->sellerProfile?->business_name,
            ] : null,
            'shipment'                    => $this->formatReturnShipment($rr->returnShipment),
        ];
    }

    protected function formatReturnShipment(?ReturnShipment $shipment): ?array
    {
        if (! $shipment) {
            return null;
        }

        $steps = [
            'Return approved',
            'Rider assigned',
            'Picked up from you',
            'At sorting hub',
            'On the way to seller',
            'Delivered to seller',
        ];

        $stepIndex = match ($shipment->status) {
            ReturnShipment::STATUS_RIDER_ASSIGNED,
            ReturnShipment::STATUS_PICKUP_FAILED => 1,
            ReturnShipment::STATUS_PICKED_UP_FROM_BUYER => 2,
            ReturnShipment::STATUS_RECEIVED_AT_HUB,
            ReturnShipment::STATUS_COURIER_ASSIGNED => 3,
            ReturnShipment::STATUS_OUT_FOR_RETURN_DELIVERY => 4,
            ReturnShipment::STATUS_DELIVERED_TO_SELLER => 5,
            default => 0,
        };

        return [
            'tracking_number'       => $shipment->tracking_number,
            'status'                => $shipment->status,
            'status_label'          => $shipment->status_label,
            'steps'                 => $steps,
            'step_index'            => $stepIndex,
            'is_cancelled'          => $shipment->status === ReturnShipment::STATUS_CANCELLED,
            'is_pickup_failed'      => $shipment->status === ReturnShipment::STATUS_PICKUP_FAILED,
            'rider_name'            => $shipment->rider?->full_name,
            'pickup_attempts_count' => (int) $shipment->pickup_attempts_count,
            'max_pickup_attempts'   => ReturnShipment::MAX_PICKUP_ATTEMPTS,
            'pickup_failure_reason' => $shipment->pickup_failure_reason,
            'events'                => $shipment->events->map(fn ($event) => [
                'id'             => (int) $event->id,
                'title'          => $event->title,
                'description'    => $event->description,
                'location'       => $event->location,
                'occurred_at'    => $event->occurred_at?->toIso8601String(),
                'occurred_label' => $event->occurred_at?->format('M d, Y h:i A'),
            ])->values()->all(),
        ];
    }

    protected function returnNextStep(ReturnRequest $rr): array
    {
        $fmt = fn ($date) => $date?->format('M d, Y h:i A');
        $amount = number_format((float) $rr->refund_amount, 2);

        $tone = match ($rr->status) {
            ReturnRequest::STATUS_REFUNDED => 'success',
            ReturnRequest::STATUS_REJECTED,
            ReturnRequest::STATUS_CLOSED,
            ReturnRequest::STATUS_REJECTED_BY_SELLER => 'danger',
            ReturnRequest::STATUS_REFUND_PENDING => 'info',
            default => 'warning',
        };

        [$title, $text] = match ($rr->status) {
            ReturnRequest::STATUS_PENDING_SELLER_RESPONSE => [
                'Waiting for the seller',
                'The seller has until ' . $fmt($rr->seller_response_deadline_at) . ' to respond. If they do not respond in time, your request is approved automatically.',
            ],
            ReturnRequest::STATUS_REJECTED_BY_SELLER => [
                'The seller rejected your request',
                'You can escalate this to Storkia Admin, who will review both sides and make the final decision.',
            ],
            ReturnRequest::STATUS_ESCALATED => [
                'Storkia Admin is reviewing your case',
                'We are reviewing the evidence from you and the seller. You will be notified as soon as a decision is made.',
            ],
            ReturnRequest::STATUS_RETURN_IN_TRANSIT => [
                'Send the item back',
                'Pack the item with everything it came with and keep it ready for the rider. '
                    . ($rr->handover_deadline_at ? 'Please hand it over by ' . $fmt($rr->handover_deadline_at) . '. ' : '')
                    . 'If the parcel cannot be handed over in time, the request is closed and the order completes without a refund.',
            ],
            ReturnRequest::STATUS_AWAITING_SELLER_CONFIRM => [
                'Your item reached the seller',
                'The seller is checking the returned item. They must confirm by ' . $fmt($rr->seller_receipt_deadline_at) . ', or it is confirmed automatically. Your refund follows right after.',
            ],
            ReturnRequest::STATUS_RECEIPT_DISPUTED => [
                'Returned item under admin review',
                'The seller reported a problem with the returned item. Storkia Admin will review it and decide.',
            ],
            ReturnRequest::STATUS_REFUND_PENDING => [
                'Your refund is on its way',
                '₱' . $amount . ' will be sent to your GCash'
                    . ($rr->refund_account_number ? ' (' . $rr->refund_account_name . ' • ' . $rr->refund_account_number . ')' : '')
                    . ($rr->refund_due_at ? ' by ' . $fmt($rr->refund_due_at) : '') . '.',
            ],
            ReturnRequest::STATUS_REFUNDED => [
                'Refund sent',
                '₱' . $amount . ' was sent to your GCash on ' . $fmt($rr->refunded_at)
                    . ($rr->refund_reference ? ' (Ref #' . $rr->refund_reference . ')' : '') . '.',
            ],
            ReturnRequest::STATUS_REJECTED => [
                'Request rejected',
                'This request was rejected and the order was completed without a refund.',
            ],
            ReturnRequest::STATUS_CLOSED => [
                'Request closed',
                'This request was closed and the order was completed without a refund.',
            ],
            default => ['Status: ' . $rr->status_label, ''],
        };

        return [
            'title' => $title,
            'text'  => $text,
            'tone'  => $tone,
        ];
    }
}