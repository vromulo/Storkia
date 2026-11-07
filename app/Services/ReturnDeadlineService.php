<?php

namespace App\Services;

use App\Models\ReturnRequest;
use App\Models\ReturnShipment;
use App\Models\ReturnShipmentEvent;
use Illuminate\Support\Facades\DB;

/**
 * Things that happen to a return request when somebody takes too long. Run by the
 * scheduled command "returns:process-deadlines".
 *
 *   seller never answered the request   -> approved automatically (ReturnRequest::SELLER_RESPONSE_HOURS)
 *   seller never confirmed the parcel   -> confirmed automatically (ReturnRequest::SELLER_CONFIRM_HOURS)
 *   parcel never handed over            -> closed without a refund, but only if a rider already tried
 *                                          and failed; if nobody tried yet, the buyer is not at fault,
 *                                          so Logistics is reminded and the deadline restarts
 *
 * Extends ReturnRefundService so it reuses the exact same status, money and notification steps.
 * Every public method is one database transaction, and notifications go out after it commits.
 */
class ReturnDeadlineService extends ReturnRefundService
{
    /**
     * @return array{seller_auto_approved: int, receipt_auto_confirmed: int, handover_closed: int,
     *               handover_extended: int, refund_payouts_overdue: int, errors: array<int, string>}
     */
    public function processExpired(): array
    {
        $summary = [
            'seller_auto_approved'   => 0,
            'receipt_auto_confirmed' => 0,
            'handover_closed'        => 0,
            'handover_extended'      => 0,
            'refund_payouts_overdue' => 0,
            'errors'                 => [],
        ];

        $unanswered = ReturnRequest::where('status', ReturnRequest::STATUS_PENDING_SELLER_RESPONSE)
            ->whereNotNull('seller_response_deadline_at')
            ->where('seller_response_deadline_at', '<=', now())
            ->get();

        foreach ($unanswered as $request) {
            try {
                if ($this->autoApproveUnansweredRequest($request)) {
                    $summary['seller_auto_approved']++;
                }
            } catch (\Throwable $e) {
                $summary['errors'][] = "{$request->request_number}: {$e->getMessage()}";
            }
        }

        $unconfirmed = ReturnRequest::where('status', ReturnRequest::STATUS_AWAITING_SELLER_CONFIRM)
            ->whereNotNull('seller_receipt_deadline_at')
            ->where('seller_receipt_deadline_at', '<=', now())
            ->get();

        foreach ($unconfirmed as $request) {
            try {
                if ($this->autoConfirmUnansweredReceipt($request)) {
                    $summary['receipt_auto_confirmed']++;
                }
            } catch (\Throwable $e) {
                $summary['errors'][] = "{$request->request_number}: {$e->getMessage()}";
            }
        }

        $unclaimed = ReturnRequest::where('status', ReturnRequest::STATUS_RETURN_IN_TRANSIT)
            ->whereNotNull('handover_deadline_at')
            ->where('handover_deadline_at', '<=', now())
            ->get();

        foreach ($unclaimed as $request) {
            try {
                $result = $this->handleExpiredHandover($request);

                if ($result === 'closed') {
                    $summary['handover_closed']++;
                } elseif ($result === 'extended') {
                    $summary['handover_extended']++;
                }
            } catch (\Throwable $e) {
                $summary['errors'][] = "{$request->request_number}: {$e->getMessage()}";
            }
        }

        // Only counted here; the Admin refund queue highlights these.
        $summary['refund_payouts_overdue'] = ReturnRequest::awaitingRefundPayout()
            ->whereNotNull('refund_due_at')
            ->where('refund_due_at', '<', now())
            ->count();

        return $summary;
    }

    /**
     * The seller did not answer in time: the buyer's request is approved the way the buyer asked for it.
     *
     * @return bool true when something was approved
     */
    public function autoApproveUnansweredRequest(ReturnRequest $request): bool
    {
        $ctx = $this->newContext();
        $acted = false;

        DB::transaction(function () use ($request, &$ctx, &$acted) {
            $locked = $this->lockRequest($request->id);

            if (! $locked->isAwaitingSellerResponse()
                || ! $locked->seller_response_deadline_at
                || $locked->seller_response_deadline_at->isFuture()) {
                return;
            }

            $order = $this->lockOrder($locked->seller_order_id);
            $hours = ReturnRequest::SELLER_RESPONSE_HOURS;
            $note = "Approved automatically because the seller did not respond within {$hours} hours.";

            $refundOnly = $locked->isRefundOnly()
                || ReturnRequest::suggestedResolutionFor($locked->reason_category) === ReturnRequest::RESOLUTION_REFUND_ONLY;

            if ($refundOnly) {
                $locked->update(array_merge(
                    $this->decisionAttributes('System', null, ReturnRequest::DECISION_APPROVED_REFUND_ONLY, $note),
                    [
                        'approved_resolution' => ReturnRequest::RESOLUTION_REFUND_ONLY,
                        'auto_approved'       => true,
                    ]
                ));

                $this->syncLegacyExtra($order, [
                    'seller_return_decision'        => 'approved',
                    'seller_return_decision_reason' => $note,
                    'seller_return_responded_at'    => now(),
                ]);

                $this->makeRefundDue(
                    $locked,
                    $order,
                    false,
                    'System',
                    null,
                    "The seller did not respond within {$hours} hours, so the refund was approved automatically.",
                    $ctx
                );
            } else {
                $this->approveReturn($locked, $order, 'System', null, $note, $ctx);

                $locked->update(['auto_approved' => true]);

                // approveReturn words its first tracking event for a seller or Admin decision.
                $shipment = ReturnShipment::where('return_request_id', $locked->id)->first();

                if ($shipment) {
                    ReturnShipmentEvent::where('return_shipment_id', $shipment->id)
                        ->where('title', 'Return Approved')
                        ->update(['description' => 'The seller did not respond in time, so the return was approved automatically. Waiting for Logistics to assign a rider.']);
                }
            }

            $this->queue(
                $ctx,
                $order->seller,
                'Return Request Approved Automatically',
                "You did not respond to {$locked->request_number} for Order #{$order->seller_order_number} within {$hours} hours, so it was approved automatically.",
                'warning',
                $this->url('seller.returns.show', $locked->id),
                $order
            );

            $acted = true;
        });

        $this->runAfterCommit($ctx);

        return $acted;
    }

    /**
     * The parcel was delivered back to the seller, but the seller never confirmed it:
     * it counts as received in good order and the refund becomes due.
     *
     * @return bool true when something was confirmed
     */
    public function autoConfirmUnansweredReceipt(ReturnRequest $request): bool
    {
        $ctx = $this->newContext();
        $acted = false;

        DB::transaction(function () use ($request, &$ctx, &$acted) {
            $locked = $this->lockRequest($request->id);

            if ($locked->status !== ReturnRequest::STATUS_AWAITING_SELLER_CONFIRM
                || ! $locked->seller_receipt_deadline_at
                || $locked->seller_receipt_deadline_at->isFuture()) {
                return;
            }

            $order = $this->lockOrder($locked->seller_order_id);
            $hours = ReturnRequest::SELLER_CONFIRM_HOURS;

            $locked->update([
                'seller_receipt_outcome' => ReturnRequest::RECEIPT_OK,
                'seller_receipt_notes'   => "Confirmed automatically after {$hours} hours without a response from the seller.",
                'seller_received_at'     => now(),
            ]);

            $this->makeRefundDue(
                $locked,
                $order,
                true,
                'System',
                null,
                "The seller did not confirm the returned parcel within {$hours} hours, so it was confirmed automatically.",
                $ctx
            );

            $this->queue(
                $ctx,
                $order->seller,
                'Returned Parcel Confirmed Automatically',
                "You did not confirm the parcel returned for Order #{$order->seller_order_number} within {$hours} hours, so it was confirmed as received in good order.",
                'warning',
                $this->url('seller.returns.show', $locked->id),
                $order
            );

            $acted = true;
        });

        $this->runAfterCommit($ctx);

        return $acted;
    }

    /**
     * The handover window ended and the parcel is still with the buyer.
     *
     *   a rider already tried and failed  -> the request is closed without a refund ("closed")
     *   nobody has tried yet              -> not the buyer's fault: remind Logistics, restart the window ("extended")
     *   parcel already collected / moved  -> nothing to do ("skipped")
     *
     * @return string closed|extended|skipped
     */
    public function handleExpiredHandover(ReturnRequest $request): string
    {
        $ctx = $this->newContext();
        $result = 'skipped';

        DB::transaction(function () use ($request, &$ctx, &$result) {
            $locked = $this->lockRequest($request->id);

            if ($locked->status !== ReturnRequest::STATUS_RETURN_IN_TRANSIT
                || ! $locked->handover_deadline_at
                || $locked->handover_deadline_at->isFuture()) {
                return;
            }

            $shipment = ReturnShipment::where('return_request_id', $locked->id)->lockForUpdate()->first();

            if (! $shipment || ! in_array($shipment->status, [
                ReturnShipment::STATUS_AWAITING_RIDER,
                ReturnShipment::STATUS_PICKUP_FAILED,
                ReturnShipment::STATUS_RIDER_ASSIGNED,
            ], true)) {
                return;
            }

            $order = $this->lockOrder($locked->seller_order_id);
            $hours = ReturnRequest::HANDOVER_HOURS;

            if ((int) $shipment->pickup_attempts_count >= 1) {
                $this->applyClose(
                    $locked,
                    $order,
                    ReturnRequest::STATUS_CLOSED,
                    'handover_timeout',
                    "The returned parcel was not handed over within {$hours} hours, so the return request was closed without a refund.",
                    'System',
                    null,
                    $ctx
                );

                $result = 'closed';

                return;
            }

            $locked->update(['handover_deadline_at' => now()->addHours($hours)]);

            $this->queue(
                $ctx,
                $order->logisticsProfile?->user,
                'Return Pickup Overdue',
                "{$shipment->tracking_number} (Order #{$order->seller_order_number}) has not been collected from the buyer yet. Please assign a rider and complete the pickup.",
                'danger',
                $this->url('logistics.returns.show', $shipment->id),
                $order
            );

            $this->queue(
                $ctx,
                $order->buyer,
                'Your Return Pickup Is Delayed',
                "The pickup of your return for Order #{$order->seller_order_number} is taking longer than expected. We reminded the logistics hub. Your return stays open.",
                'info',
                $this->url('user.orders.show', $order->id),
                $order
            );

            $result = 'extended';
        });

        $this->runAfterCommit($ctx);

        return $result;
    }
}