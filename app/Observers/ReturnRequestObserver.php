<?php

namespace App\Observers;

use App\Models\Admin;
use App\Models\ReturnRequest;
use App\Notifications\OrderLifecycleNotification;
use Illuminate\Support\Facades\DB;

/**
 * Notifies Storkia Admin when a return request reaches a step only Admin can finish:
 *
 *   escalated         -> the buyer escalated a seller rejection; Admin must decide
 *   receipt_disputed  -> the seller says the returned parcel is not as expected; Admin must decide
 *   refund_pending    -> a refund is owed; Admin must pay it to the buyer's GCash
 *
 * It listens for the status change itself, so it fires no matter which code path caused it
 * (buyer, seller, Admin, or the scheduled deadline job). Registered in AppServiceProvider.
 */
class ReturnRequestObserver
{
    public function updated(ReturnRequest $request): void
    {
        if (! $request->wasChanged('status')) {
            return;
        }

        $status = $request->status;

        if (! in_array($status, [
            ReturnRequest::STATUS_ESCALATED,
            ReturnRequest::STATUS_RECEIPT_DISPUTED,
            ReturnRequest::STATUS_REFUND_PENDING,
        ], true)) {
            return;
        }

        // Wait for the surrounding transaction: nothing is sent if the step is rolled back.
        DB::afterCommit(function () use ($request, $status) {
            try {
                $request->loadMissing('sellerOrder');

                $orderNumber = $request->sellerOrder?->seller_order_number ?? ('#' . $request->seller_order_id);

                if ($status === ReturnRequest::STATUS_ESCALATED) {
                    $title = 'Return/Refund Escalated: Decision Needed';
                    $message = "{$request->request_number} (Order #{$orderNumber}) was escalated by the buyer after the seller rejected it. Please review the evidence and decide.";
                    $type = 'warning';
                } elseif ($status === ReturnRequest::STATUS_RECEIPT_DISPUTED) {
                    $title = 'Returned Parcel Disputed: Decision Needed';
                    $message = "The seller says the parcel returned for {$request->request_number} (Order #{$orderNumber}) is not as expected. Please review the photos and decide.";
                    $type = 'warning';
                } else {
                    $title = 'Refund Ready for GCash Payout';
                    $message = "{$request->request_number} (Order #{$orderNumber}): send ₱" . number_format((float) $request->refund_amount, 2)
                        . ' to the buyer\'s GCash, then record the reference number.';
                    $type = 'info';
                }

                $url = route('admin.returns.show', $request->id);

                foreach (Admin::all() as $admin) {
                    $admin->notify(new OrderLifecycleNotification(
                        title: $title,
                        message: $message,
                        type: $type,
                        url: $url,
                        orderId: $request->order_id ? (int) $request->order_id : null,
                        sellerOrderId: (int) $request->seller_order_id
                    ));
                }
            } catch (\Throwable $e) {
                // A failed notification must never undo a real status change.
            }
        });
    }
}