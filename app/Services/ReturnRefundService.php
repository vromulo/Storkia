<?php

namespace App\Services;

use App\Models\OrderStatusHistory;
use App\Models\ReturnRequest;
use App\Models\ReturnShipment;
use App\Models\SellerOrder;
use App\Models\SellerOrderExtra;
use App\Notifications\OrderLifecycleNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class ReturnRefundService
{
    public const ACTION_APPROVE_RETURN      = 'approve_return';
    public const ACTION_APPROVE_REFUND_ONLY = 'approve_refund_only';
    public const ACTION_REJECT              = 'reject';

    public function __construct(
        protected ReturnRefundLedgerService $ledger,
        protected InventoryStockService $inventoryStockService,
        protected ?RoleWalletService $roleWalletService = null,
        protected ?BuyerRewardService $buyerRewardService = null
    ) {
        $this->roleWalletService = $roleWalletService ?? app(RoleWalletService::class);
        $this->buyerRewardService = $buyerRewardService ?? app(BuyerRewardService::class);
    }

    public function submit(SellerOrder $sellerOrder, int $buyerId, array $data): ReturnRequest
    {
        $ctx = $this->newContext();

        $request = DB::transaction(function () use ($sellerOrder, $buyerId, $data, &$ctx) {
            $locked = $this->lockOrder($sellerOrder->id);

            if ((int) $locked->buyer_id !== $buyerId) {
                $this->fail('This order does not belong to your account.');
            }
            if ($locked->status === 'completed') {
                $this->fail('Completed orders can no longer be returned or refunded.');
            }
            if ($locked->status !== 'delivered') {
                $this->fail('A return or refund can only be requested after the parcel is marked delivered.');
            }
            if ($locked->confirmation_deadline_at && $locked->confirmation_deadline_at->isPast()) {
                $this->fail('The ' . SellerOrder::BUYER_CONFIRMATION_HOURS . '-hour window to request a return or refund has already expired.');
            }
            if (ReturnRequest::where('seller_order_id', $locked->id)->exists()) {
                $this->fail('A return or refund request already exists for this order.');
            }

            $category = (string) ($data['reason_category'] ?? '');
            if (! isset(ReturnRequest::reasonCatalog()[$category])) {
                $this->fail('Please choose a valid reason for your request.');
            }

            $reason = trim((string) ($data['reason'] ?? ''));
            if ($reason === '') {
                $this->fail('Please describe the problem with your order.');
            }

            $resolution = $this->chooseRequestedResolution($category, $data['requested_resolution'] ?? null);
            $photos = array_values(array_filter((array) ($data['evidence_photos'] ?? [])));
            $payoutMethod = strtoupper(trim((string) ($data['refund_payout_method'] ?? 'WALLET')));
            if (! in_array($payoutMethod, ['WALLET', 'GCASH'], true)) {
                $payoutMethod = 'WALLET';
            }

            $accountName = trim((string) ($data['refund_account_name'] ?? ''));
            $accountNumber = trim((string) ($data['refund_account_number'] ?? ''));

            $createPayload = [
                'request_number'              => ReturnRequest::generateRequestNumber(),
                'seller_order_id'             => $locked->id,
                'order_id'                    => $locked->order_id,
                'buyer_id'                    => $buyerId,
                'seller_profile_id'           => $locked->seller_profile_id,
                'logistics_profile_id'        => $locked->logistics_profile_id,
                'requested_resolution'        => $resolution,
                'status'                      => ReturnRequest::STATUS_PENDING_SELLER_RESPONSE,
                'reason_category'             => $category,
                'reason'                      => $reason,
                'evidence_photos'             => $photos,
                'refund_amount'               => round((float) $locked->total_amount, 2),
                'refund_account_name'         => $accountName !== '' ? $accountName : ($locked->buyer?->full_name ?? null),
                'refund_account_number'       => $accountNumber !== '' ? $accountNumber : null,
                'refund_status'               => ReturnRequest::REFUND_NOT_DUE,
                'seller_response_deadline_at' => now()->addHours(ReturnRequest::SELLER_RESPONSE_HOURS),
            ];

            if (Schema::hasColumn('return_requests', 'refund_payout_method')) {
                $createPayload['refund_payout_method'] = $payoutMethod;
            }

            $request = ReturnRequest::create($createPayload);

            $locked->update([
                'status'                 => 'disputed',
                'has_unresolved_dispute' => true,
                'dispute_reason'         => $reason,
                'dispute_reported_at'    => now(),
            ]);

            $this->history(
                $locked,
                'delivered',
                'disputed',
                'Buyer',
                $buyerId,
                "Buyer requested {$request->resolution_label} ({$request->request_number}): {$category}. "
                    . 'Seller has ' . ReturnRequest::SELLER_RESPONSE_HOURS . ' hours to respond.'
            );

            $this->syncLegacyExtra($locked, [
                'dispute_reason_category' => $category,
                'dispute_evidence_photos' => $photos,
                'refund_method'           => $payoutMethod,
                'refund_account_name'     => $request->refund_account_name,
                'refund_account_number'   => $request->refund_account_number,
                'refund_amount'           => $request->refund_amount,
            ]);

            $this->syncParent($locked);

            $this->queue(
                $ctx,
                $locked->seller,
                'Return/Refund Request (' . ReturnRequest::SELLER_RESPONSE_HOURS . '-Hour Response Deadline)',
                "Buyer requested a {$request->resolution_label} for Order #{$locked->seller_order_number} ({$category}). "
                    . 'Please respond within ' . ReturnRequest::SELLER_RESPONSE_HOURS . ' hours or it will be auto-approved.',
                'danger',
                $this->url('seller.orders.show', $locked->id),
                $locked
            );

            $this->queue(
                $ctx,
                $locked->buyer,
                'Return/Refund Request Submitted',
                "Your request {$request->request_number} for Order #{$locked->seller_order_number} was sent to the seller. "
                    . 'They have ' . ReturnRequest::SELLER_RESPONSE_HOURS . ' hours to respond.',
                'info',
                $this->url('user.orders.show', $locked->id),
                $locked
            );

            return $request;
        });

        $this->runAfterCommit($ctx);

        return $request->fresh();
    }

    public function buyerEscalate(ReturnRequest $request, int $buyerId, ?string $note = null): ReturnRequest
    {
        $ctx = $this->newContext();

        DB::transaction(function () use ($request, $buyerId, $note, &$ctx) {
            $locked = $this->lockRequest($request->id);
            $order = $this->lockOrder($locked->seller_order_id);

            if ((int) $locked->buyer_id !== $buyerId) {
                $this->fail('This request does not belong to your account.');
            }
            if (! $locked->canBuyerEscalate()) {
                $this->fail('Only a request rejected by the seller can be escalated, and only once.');
            }

            $note = trim((string) $note);

            $locked->update([
                'status'          => ReturnRequest::STATUS_ESCALATED,
                'is_escalated'    => true,
                'escalated_at'    => now(),
                'escalation_note' => $note !== '' ? $note : null,
            ]);

            $this->syncLegacyExtra($order, [
                'is_escalated_to_admin' => true,
                'escalated_at'          => now(),
            ]);

            $this->history($order, 'disputed', 'disputed', 'Buyer', $buyerId, "Buyer escalated {$locked->request_number} to Storkia Admin after the seller rejected it.");

            $this->queue(
                $ctx,
                $order->seller,
                'Return/Refund Escalated to Admin',
                "The buyer escalated {$locked->request_number} for Order #{$order->seller_order_number}. Storkia Admin will review the case and make the final decision.",
                'info',
                $this->url('seller.orders.show', $order->id),
                $order
            );

            $this->queue(
                $ctx,
                $order->buyer,
                'Escalation Received',
                "Storkia Admin is now reviewing {$locked->request_number} for Order #{$order->seller_order_number}.",
                'info',
                $this->url('user.orders.show', $order->id),
                $order
            );
        });

        $this->runAfterCommit($ctx);

        return $request->fresh();
    }

    public function sellerRespond(ReturnRequest $request, int $sellerUserId, string $action, ?string $reason = null): ReturnRequest
    {
        $ctx = $this->newContext();

        DB::transaction(function () use ($request, $sellerUserId, $action, $reason, &$ctx) {
            $locked = $this->lockRequest($request->id);
            $order = $this->lockOrder($locked->seller_order_id);

            if ((int) $order->seller_id !== $sellerUserId) {
                $this->fail('This request belongs to another seller.');
            }
            if (! $locked->isAwaitingSellerResponse()) {
                $this->fail('This request is no longer waiting for your response.');
            }

            $note = trim((string) $reason);
            $cannotBeSentBack = ReturnRequest::suggestedResolutionFor($locked->reason_category) === ReturnRequest::RESOLUTION_REFUND_ONLY;

            if ($action === self::ACTION_APPROVE_RETURN) {
                if ($cannotBeSentBack) {
                    $this->fail('An item that was never received (or an empty package) cannot be sent back. Approve a refund only instead.');
                }
                $this->approveReturn($locked, $order, 'Seller', $sellerUserId, $note, $ctx);
                return;
            }

            if ($action === self::ACTION_APPROVE_REFUND_ONLY) {
                $this->approveRefundOnly($locked, $order, 'Seller', $sellerUserId, $note, $ctx);
                return;
            }

            if ($action === self::ACTION_REJECT) {
                if (mb_strlen($note) < 10) {
                    $this->fail('Please explain why you are rejecting this request (at least 10 characters).');
                }

                $locked->update(array_merge(
                    $this->decisionAttributes('Seller', $sellerUserId, ReturnRequest::DECISION_REJECTED, $note),
                    ['status' => ReturnRequest::STATUS_REJECTED_BY_SELLER]
                ));

                $this->syncLegacyExtra($order, [
                    'seller_return_decision'        => 'rejected',
                    'seller_return_decision_reason' => $note,
                    'seller_return_responded_at'    => now(),
                ]);

                $this->history($order, 'disputed', 'disputed', 'Seller', $sellerUserId, "Seller rejected {$locked->request_number}: {$note}");

                $this->queue(
                    $ctx,
                    $order->buyer,
                    'Return/Refund Request Rejected by Seller',
                    "The seller rejected {$locked->request_number} for Order #{$order->seller_order_number}: {$note}. You can escalate this to Storkia Admin from your order page.",
                    'danger',
                    $this->url('user.orders.show', $order->id),
                    $order
                );
                return;
            }

            $this->fail('Unknown response. Choose approve return, approve refund only, or reject.');
        });

        $this->runAfterCommit($ctx);

        return $request->fresh();
    }

    public function sellerConfirmReceipt(ReturnRequest $request, int $sellerUserId, string $outcome, ?string $notes = null, array $photos = []): ReturnRequest
    {
        $ctx = $this->newContext();

        DB::transaction(function () use ($request, $sellerUserId, $outcome, $notes, $photos, &$ctx) {
            $locked = $this->lockRequest($request->id);
            $order = $this->lockOrder($locked->seller_order_id);

            if ((int) $order->seller_id !== $sellerUserId) {
                $this->fail('This request belongs to another seller.');
            }
            if ($locked->status !== ReturnRequest::STATUS_AWAITING_SELLER_CONFIRM) {
                $this->fail('There is no returned parcel waiting for your confirmation.');
            }

            $notes = trim((string) $notes);

            if ($outcome === ReturnRequest::RECEIPT_OK) {
                $locked->update([
                    'seller_receipt_outcome' => ReturnRequest::RECEIPT_OK,
                    'seller_receipt_notes'   => $notes !== '' ? $notes : null,
                    'seller_receipt_photos'  => array_values(array_filter($photos)),
                    'seller_received_at'     => now(),
                ]);

                $this->makeRefundDue($locked, $order, true, 'Seller', $sellerUserId, 'Seller confirmed the returned parcel was received in good order.', $ctx);
                return;
            }

            if ($outcome === ReturnRequest::RECEIPT_NOT_AS_EXPECTED) {
                if (mb_strlen($notes) < 10) {
                    $this->fail('Please describe what is wrong with the returned parcel (at least 10 characters).');
                }

                $locked->update([
                    'status'                 => ReturnRequest::STATUS_RECEIPT_DISPUTED,
                    'seller_receipt_outcome' => ReturnRequest::RECEIPT_NOT_AS_EXPECTED,
                    'seller_receipt_notes'   => $notes,
                    'seller_receipt_photos'  => array_values(array_filter($photos)),
                    'seller_received_at'     => now(),
                ]);

                $this->history($order, 'disputed', 'disputed', 'Seller', $sellerUserId, "Seller reported the returned parcel for {$locked->request_number} is not as expected: {$notes}");

                $this->queue(
                    $ctx,
                    $order->buyer,
                    'Returned Item Under Admin Review',
                    "The seller reported a problem with the parcel you returned for Order #{$order->seller_order_number}. Storkia Admin will review it and decide.",
                    'info',
                    $this->url('user.orders.show', $order->id),
                    $order
                );
                return;
            }

            $this->fail('Choose whether the returned parcel was received in good order or not as expected.');
        });

        $this->runAfterCommit($ctx);

        return $request->fresh();
    }

    public function adminResolve(ReturnRequest $request, int $adminId, string $decision, ?string $remarks = null): ReturnRequest
    {
        $ctx = $this->newContext();

        DB::transaction(function () use ($request, $adminId, $decision, $remarks, &$ctx) {
            $locked = $this->lockRequest($request->id);
            $order = $this->lockOrder($locked->seller_order_id);

            $isReceiptDispute = $locked->status === ReturnRequest::STATUS_RECEIPT_DISPUTED;
            if (! $isReceiptDispute && $locked->status !== ReturnRequest::STATUS_ESCALATED) {
                $this->fail('Only escalated requests or disputed returned parcels can be resolved by Admin.');
            }

            $remarks = trim((string) $remarks);
            if (mb_strlen($remarks) < 5) {
                $this->fail('Please add remarks explaining the decision.');
            }

            if (! in_array($decision, [
                ReturnRequest::DECISION_APPROVED_RETURN,
                ReturnRequest::DECISION_APPROVED_REFUND_ONLY,
                ReturnRequest::DECISION_REJECTED,
            ], true)) {
                $this->fail('Unknown decision.');
            }

            if ($decision === ReturnRequest::DECISION_REJECTED) {
                $locked->update($this->decisionAttributes('Admin', $adminId, $decision, $remarks));
                $this->applyClose(
                    $locked,
                    $order,
                    ReturnRequest::STATUS_REJECTED,
                    'admin_rejected',
                    "Storkia Admin rejected {$locked->request_number}: {$remarks}",
                    'Admin',
                    $adminId,
                    $ctx
                );
                return;
            }

            if ($isReceiptDispute || $decision === ReturnRequest::DECISION_APPROVED_REFUND_ONLY) {
                $this->approveRefundOnly($locked, $order, 'Admin', $adminId, $remarks, $ctx, $isReceiptDispute);
                return;
            }

            if (ReturnRequest::suggestedResolutionFor($locked->reason_category) === ReturnRequest::RESOLUTION_REFUND_ONLY) {
                $this->fail('An item that was never received cannot be sent back. Approve a refund only instead.');
            }

            $this->approveReturn($locked, $order, 'Admin', $adminId, $remarks, $ctx);
        });

        $this->runAfterCommit($ctx);

        return $request->fresh();
    }

    public function markRefundPaid(ReturnRequest $request, int $adminId, string $reference, ?string $remarks = null): ReturnRequest
    {
        $ctx = $this->newContext();

        DB::transaction(function () use ($request, $adminId, $reference, $remarks, &$ctx) {
            $locked = $this->lockRequest($request->id);
            $order = $this->lockOrder($locked->seller_order_id);

            if ($locked->refund_status !== ReturnRequest::REFUND_PENDING_ADMIN) {
                $this->fail('This refund is not waiting for payout.');
            }

            $reference = strtoupper(trim($reference));
            if (mb_strlen($reference) < 4) {
                $this->fail('Enter the GCash reference number of the refund (at least 4 characters).');
            }

            $locked->update([
                'status'               => ReturnRequest::STATUS_REFUNDED,
                'refund_status'        => ReturnRequest::REFUND_REFUNDED,
                'refund_reference'     => $reference,
                'refund_remarks'       => $remarks ? trim($remarks) : null,
                'refunded_at'          => now(),
                'refunded_by_admin_id' => $adminId,
                'closed_at'            => now(),
                'closed_reason'        => 'refunded',
            ]);

            $this->syncLegacyExtra($order, [
                'refund_status'      => 'refund_released',
                'refund_released_at' => now(),
            ]);

            $order->paymentSubmission?->update([
                'refund_status'    => 'refunded',
                'refund_reference' => $reference,
            ]);

            $this->history($order, $order->status, $order->status, 'Admin', $adminId, "Refund of ₱" . number_format((float) $locked->refund_amount, 2) . " sent to the buyer via GCash (Ref #{$reference}).");

            $this->queue(
                $ctx,
                $order->buyer,
                'Refund Sent',
                "Your refund of ₱" . number_format((float) $locked->refund_amount, 2) . " for Order #{$order->seller_order_number} was sent to your GCash (Ref #{$reference}).",
                'success',
                $this->url('user.orders.show', $order->id),
                $order
            );
        });

        $this->runAfterCommit($ctx);

        return $request->fresh();
    }

    public function markReturnDelivered(ReturnRequest $request): ReturnRequest
    {
        $ctx = $this->newContext();

        DB::transaction(function () use ($request, &$ctx) {
            $locked = $this->lockRequest($request->id);
            $order = $this->lockOrder($locked->seller_order_id);

            if ($locked->status !== ReturnRequest::STATUS_RETURN_IN_TRANSIT) {
                return;
            }

            $locked->update([
                'status'                     => ReturnRequest::STATUS_AWAITING_SELLER_CONFIRM,
                'seller_receipt_deadline_at' => now()->addHours(ReturnRequest::SELLER_CONFIRM_HOURS),
            ]);

            $this->history($order, 'disputed', 'disputed', 'System', null, "Returned parcel for {$locked->request_number} was delivered to the seller. Waiting for the seller to confirm receipt.");

            $this->queue(
                $ctx,
                $order->seller,
                'Returned Parcel Delivered: Please Confirm',
                "The parcel returned for Order #{$order->seller_order_number} was delivered to you. Please check it and confirm within " . ReturnRequest::SELLER_CONFIRM_HOURS . ' hours, or it will be confirmed automatically.',
                'danger',
                $this->url('seller.orders.show', $order->id),
                $order
            );

            $this->queue(
                $ctx,
                $order->buyer,
                'Your Returned Item Reached the Seller',
                "Your returned parcel for Order #{$order->seller_order_number} was delivered to the seller. Your refund will be processed once they confirm.",
                'info',
                $this->url('user.orders.show', $order->id),
                $order
            );
        });

        $this->runAfterCommit($ctx);

        return $request->fresh();
    }

    public function closeWithoutRefund(ReturnRequest $request, string $finalStatus, string $reasonCode, string $message, string $actorType = 'System', ?int $actorId = null): ReturnRequest
    {
        $ctx = $this->newContext();

        DB::transaction(function () use ($request, $finalStatus, $reasonCode, $message, $actorType, $actorId, &$ctx) {
            $locked = $this->lockRequest($request->id);
            if ($locked->isTerminal()) {
                return;
            }
            $order = $this->lockOrder($locked->seller_order_id);
            $this->applyClose($locked, $order, $finalStatus, $reasonCode, $message, $actorType, $actorId, $ctx);
        });

        $this->runAfterCommit($ctx);

        return $request->fresh();
    }

    protected function approveReturn(ReturnRequest $locked, SellerOrder $order, string $actorType, ?int $actorId, string $note, array &$ctx): void
    {
        $locked->update(array_merge(
            $this->decisionAttributes($actorType, $actorId, ReturnRequest::DECISION_APPROVED_RETURN, $note !== '' ? $note : null),
            [
                'approved_resolution'  => ReturnRequest::RESOLUTION_RETURN_REFUND,
                'status'               => ReturnRequest::STATUS_RETURN_IN_TRANSIT,
                'handover_deadline_at' => now()->addHours(ReturnRequest::HANDOVER_HOURS),
            ]
        ));

        $forward = $order->shipment;
        $returnShipment = ReturnShipment::firstOrCreate(
            ['return_request_id' => $locked->id],
            [
                'tracking_number'         => ReturnShipment::generateTrackingNumber(),
                'seller_order_id'         => $order->id,
                'shipment_id'             => $forward?->id,
                'logistics_profile_id'    => $order->logistics_profile_id,
                'status'                  => ReturnShipment::STATUS_AWAITING_RIDER,
                'total_weight'            => (float) $order->total_weight,
                'pickup_address_snapshot' => (array) ($order->delivery_address_snapshot ?? []),
                'hub_address_snapshot'    => (array) ($forward?->hub_address_snapshot ?? []),
                'return_address_snapshot' => (array) ($order->pickup_address_snapshot ?? []),
            ]
        );

        $returnShipment->recordEvent(
            'Return Approved',
            $actorType === 'Admin'
                ? 'Storkia Admin approved the return. Waiting for Logistics to assign a rider.'
                : 'The seller approved the return. Waiting for Logistics to assign a rider.',
            null,
            $actorType,
            $actorId,
            ReturnShipment::STATUS_AWAITING_RIDER
        );

        $this->syncLegacyExtra($order, [
            'seller_return_decision'        => 'approved',
            'seller_return_decision_reason' => $note !== '' ? $note : 'Return approved.',
            'seller_return_responded_at'    => now(),
        ]);

        $this->history($order, 'disputed', 'disputed', $actorType, $actorId, "Return approved for {$locked->request_number}. Parcel must be sent back (tracking {$returnShipment->tracking_number}).");

        $this->queue(
            $ctx,
            $order->buyer,
            'Return Approved: Prepare Your Parcel',
            "Your return for Order #{$order->seller_order_number} was approved. Pack the item with everything it came with. A rider will collect it from you. Tracking: {$returnShipment->tracking_number}.",
            'success',
            $this->url('user.orders.show', $order->id),
            $order
        );

        $this->queue(
            $ctx,
            $order->logisticsProfile?->user,
            'New Return Pickup: Assign a Rider',
            "A return was approved for Order #{$order->seller_order_number} ({$returnShipment->tracking_number}). Please assign a rider to collect the parcel from the buyer.",
            'info',
            $this->url('logistics.returns.index'),
            $order
        );
    }

    protected function approveRefundOnly(ReturnRequest $locked, SellerOrder $order, string $actorType, ?int $actorId, string $note, array &$ctx, bool $afterReceiptDispute = false): void
    {
        $locked->update(array_merge(
            $this->decisionAttributes($actorType, $actorId, ReturnRequest::DECISION_APPROVED_REFUND_ONLY, $note !== '' ? $note : null),
            ['approved_resolution' => $afterReceiptDispute ? $locked->effectiveResolution() : ReturnRequest::RESOLUTION_REFUND_ONLY]
        ));

        $this->syncLegacyExtra($order, [
            'seller_return_decision'        => 'approved',
            'seller_return_decision_reason' => $note !== '' ? $note : 'Refund approved.',
            'seller_return_responded_at'    => now(),
        ]);

        $remarks = $afterReceiptDispute
            ? 'Storkia Admin approved the refund after reviewing the returned parcel.'
            : ($actorType === 'Admin' ? 'Storkia Admin approved a refund only (buyer keeps the item).' : 'Seller approved a refund only (buyer keeps the item).');

        $this->makeRefundDue($locked, $order, false, $actorType, $actorId, $remarks, $ctx);
    }

    protected function makeRefundDue(ReturnRequest $locked, SellerOrder $order, bool $restoreStock, string $actorType, ?int $actorId, string $remarks, array &$ctx): void
    {
        $isWalletRefund = $locked->isWalletRefund() || empty($locked->refund_account_number);

        $this->ledger->settleRefund($locked->fresh());
        $locked->refresh();

        if ($restoreStock) {
            $this->inventoryStockService->restoreStockForSellerOrder($order);
        }

        $this->buyerRewardService?->restoreCoinsForSellerOrder($order);

        if ($isWalletRefund) {
            $walletTx = $this->roleWalletService?->refundBuyerWalletForOrder(
                $order,
                (float) $locked->refund_amount,
                "Instant E-Wallet Refund ({$locked->request_number})"
            );

            $walletRef = $walletTx?->transaction_reference ?? ('WAL-' . now()->format('YmdHis'));

            $locked->update([
                'status'           => ReturnRequest::STATUS_REFUNDED,
                'refund_status'    => ReturnRequest::REFUND_REFUNDED,
                'refund_reference' => $walletRef,
                'refund_remarks'   => 'Instant automated refund credited to Buyer Storkia E-Wallet.',
                'refund_due_at'    => now(),
                'refunded_at'      => now(),
                'closed_at'        => now(),
                'closed_reason'    => 'refunded',
            ]);

            $order->paymentSubmission?->update([
                'refund_status'    => 'refunded',
                'refund_reference' => $walletRef,
            ]);

            $this->syncLegacyExtra($order, [
                'refund_method'      => 'WALLET',
                'refund_amount'      => $locked->refund_amount,
                'refund_status'      => 'refund_released',
                'refund_released_at' => now(),
            ]);

            $this->history($order, 'disputed', 'returned_to_seller', $actorType, $actorId, $remarks . " Instant refund of ₱" . number_format((float) $locked->refund_amount, 2) . " credited to Buyer Storkia E-Wallet (Ref #{$walletRef}).");
        } else {
            $locked->update([
                'status'        => ReturnRequest::STATUS_REFUND_PENDING,
                'refund_status' => ReturnRequest::REFUND_PENDING_ADMIN,
                'refund_due_at' => now()->addHours(ReturnRequest::REFUND_PAYOUT_HOURS),
            ]);

            $order->paymentSubmission?->update(['refund_status' => 'pending_admin_refund']);

            $this->syncLegacyExtra($order, [
                'refund_method' => 'GCASH',
                'refund_amount' => $locked->refund_amount,
                'refund_status' => 'pending_admin_refund',
            ]);

            $this->history($order, 'disputed', 'returned_to_seller', $actorType, $actorId, $remarks . " Refund of ₱" . number_format((float) $locked->refund_amount, 2) . " queued for payout ({$locked->request_number}).");
        }

        $order->update([
            'status'                 => 'returned_to_seller',
            'payment_status'         => 'cancelled',
            'has_unresolved_dispute' => false,
            'dispute_resolved_at'    => now(),
            'cancelled_at'           => now(),
        ]);
        $order->payment?->update(['status' => 'cancelled']);

        $this->syncParent($order);

        $this->queue(
            $ctx,
            $order->buyer,
            $isWalletRefund ? 'Instant Refund Credited to Your E-Wallet!' : 'Refund Approved',
            $isWalletRefund
                ? "Your refund of ₱" . number_format((float) $locked->refund_amount, 2) . " for Order #{$order->seller_order_number} was instantly credited to your Storkia E-Wallet!"
                : "Your refund of ₱" . number_format((float) $locked->refund_amount, 2) . " for Order #{$order->seller_order_number} was approved. Storkia will send it to your GCash within " . ReturnRequest::REFUND_PAYOUT_HOURS . ' hours.',
            'success',
            $this->url('user.orders.show', $order->id),
            $order
        );

        $this->queue(
            $ctx,
            $order->seller,
            'Refund Settled on Your Wallet',
            "Order #{$order->seller_order_number} was refunded. Your earnings for it were cancelled and ₱" . number_format((float) $locked->seller_deduction_amount, 2) . ' was deducted from your wallet for the shipping and return costs already paid out.',
            'info',
            $this->url('seller.orders.show', $order->id),
            $order
        );
    }

    protected function applyClose(ReturnRequest $locked, SellerOrder $order, string $finalStatus, string $reasonCode, string $message, string $actorType, ?int $actorId, array &$ctx): void
    {
        $locked->update([
            'status'        => $finalStatus,
            'refund_status' => ReturnRequest::REFUND_NOT_DUE,
            'closed_at'     => now(),
            'closed_reason' => $reasonCode,
        ]);

        $returnShipment = $locked->returnShipment;
        if ($returnShipment && $returnShipment->isOpen()) {
            $returnShipment->update([
                'status'       => ReturnShipment::STATUS_CANCELLED,
                'cancelled_at' => now(),
            ]);
            $returnShipment->recordEvent('Return Cancelled', $message, null, $actorType, $actorId, ReturnShipment::STATUS_CANCELLED);
        }

        $order->update([
            'status'                 => 'delivered',
            'has_unresolved_dispute' => false,
            'dispute_resolved_at'    => now(),
        ]);

        $this->history($order, 'disputed', 'delivered', $actorType, $actorId, $message . ' The order will now complete normally.');
        $this->syncParent($order);

        $this->queue(
            $ctx,
            $order->buyer,
            'Return/Refund Request Closed',
            "{$message} The order is now completed.",
            'danger',
            $this->url('user.orders.show', $order->id),
            $order
        );

        $this->queue(
            $ctx,
            $order->seller,
            'Return/Refund Request Closed',
            "{$locked->request_number} for Order #{$order->seller_order_number} was closed without a refund. The order will complete and your earnings will be released.",
            'success',
            $this->url('seller.orders.show', $order->id),
            $order
        );

        $orderId = (int) $order->id;
        $ctx['after'][] = function () use ($orderId) {
            $fresh = SellerOrder::find($orderId);
            if ($fresh) {
                app(OrderCompletionService::class)->confirmSellerOrder($fresh, 'System', null, true);
            }
        };
    }

    protected function chooseRequestedResolution(string $category, ?string $requested): string
    {
        if (ReturnRequest::suggestedResolutionFor($category) === ReturnRequest::RESOLUTION_REFUND_ONLY) {
            return ReturnRequest::RESOLUTION_REFUND_ONLY;
        }

        return in_array($requested, [ReturnRequest::RESOLUTION_RETURN_REFUND, ReturnRequest::RESOLUTION_REFUND_ONLY], true)
            ? $requested
            : ReturnRequest::RESOLUTION_RETURN_REFUND;
    }

    protected function decisionAttributes(string $actorType, ?int $actorId, string $decision, ?string $note): array
    {
        if ($actorType === 'Admin') {
            return [
                'admin_decision'    => $decision,
                'admin_remarks'     => $note,
                'admin_resolved_by' => $actorId,
                'admin_resolved_at' => now(),
            ];
        }

        return [
            'seller_decision'        => $decision,
            'seller_decision_reason' => $note,
            'seller_responded_at'    => now(),
        ];
    }

    protected function lockRequest(int $id): ReturnRequest
    {
        return ReturnRequest::where('id', $id)->lockForUpdate()->firstOrFail();
    }

    protected function lockOrder(int $sellerOrderId): SellerOrder
    {
        return SellerOrder::with([
            'order',
            'buyer',
            'seller',
            'logisticsProfile.user',
            'payment',
            'paymentSubmission',
            'extra',
            'shipment',
        ])->where('id', $sellerOrderId)->lockForUpdate()->firstOrFail();
    }

    protected function history(SellerOrder $order, string $from, string $to, string $actorType, ?int $actorId, string $remarks): void
    {
        OrderStatusHistory::create([
            'order_id'        => $order->order_id,
            'seller_order_id' => $order->id,
            'from_status'     => $from,
            'to_status'       => $to,
            'actor_type'      => $actorType,
            'actor_id'        => $actorId,
            'remarks'         => $remarks,
        ]);
    }

    protected function syncLegacyExtra(SellerOrder $order, array $attributes): void
    {
        $extra = $order->extra ?: SellerOrderExtra::create([
            'order_id'        => $order->order_id,
            'seller_order_id' => $order->id,
        ]);

        $extra->update($attributes);
    }

    protected function syncParent(SellerOrder $order): void
    {
        if ($order->order) {
            app(OrderCompletionService::class)->syncParentOrderStatus($order->order);
        }
    }

    protected function fail(string $message): never
    {
        throw ValidationException::withMessages(['return' => $message]);
    }

    protected function newContext(): array
    {
        return ['notifications' => [], 'after' => []];
    }

    protected function queue(array &$ctx, ?object $recipient, string $title, string $message, string $type, ?string $url, SellerOrder $order): void
    {
        if (! $recipient) {
            return;
        }

        $ctx['notifications'][] = [
            $recipient,
            new OrderLifecycleNotification($title, $message, $type, $url, (int) $order->order_id, (int) $order->id),
        ];
    }

    protected function runAfterCommit(array $ctx): void
    {
        foreach ($ctx['notifications'] as [$recipient, $notification]) {
            try {
                $recipient->notify($notification);
            } catch (\Throwable $e) {
            }
        }

        foreach ($ctx['after'] as $callback) {
            try {
                $callback();
            } catch (\Throwable $e) {
            }
        }
    }

    protected function url(string $routeName, mixed $parameter = null): ?string
    {
        try {
            return $parameter === null ? route($routeName) : route($routeName, $parameter);
        } catch (\Throwable $e) {
            return null;
        }
    }
}