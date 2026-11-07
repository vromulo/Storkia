<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\ReturnRequest;
use App\Models\ReturnShipment;
use App\Models\RoleWallet;
use App\Models\SellerOrder;
use App\Services\CommissionCalculatorService;
use App\Services\ReturnRefundLedgerService;
use App\Services\ReturnRefundService;
use App\Services\RoleWalletService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

/**
 * Seller side of Return & Refund.
 *
 *   GET  /seller/returns/{returnRequest}                  -> the request, deduction warning, return tracking
 *   POST /seller/returns/{returnRequest}/respond          -> approve return | approve refund only | reject
 *   POST /seller/returns/{returnRequest}/confirm-receipt  -> the returned parcel is OK | not as expected
 *
 * All business rules live in ReturnRefundService; this controller validates input,
 * stores photos, and prepares the "what will this cost me" figures the seller must
 * see before approving anything.
 */
class ReturnRequestController extends Controller
{
    public function __construct(
        protected ReturnRefundService $returnRefundService,
        protected ReturnRefundLedgerService $ledger,
        protected CommissionCalculatorService $commissionCalculator
    ) {
    }

    // ------------------------------------------------------------------ pages

    public function show(Request $request, ReturnRequest $returnRequest): View
    {
        $this->authorizeSeller($request, $returnRequest);

        $returnRequest->load([
            'sellerOrder.items',
            'sellerOrder.buyer',
            'sellerOrder.payment',
            'sellerOrder.shipment',
            'sellerOrder.commissionRecord',
            'returnShipment.events',
            'returnShipment.rider',
            'returnShipment.courier',
        ]);

        $sellerOrder = $returnRequest->sellerOrder;

        return view('seller.orders.return', [
            'returnRequest'   => $returnRequest,
            'sellerOrder'     => $sellerOrder,
            'returnShipment'  => $returnRequest->returnShipment,
            'deduction'       => $this->buildDeduction($returnRequest, $sellerOrder),
            // "Never arrived / empty box" cannot be sent back, so only a refund can be approved.
            'canApproveReturn' => ReturnRequest::suggestedResolutionFor($returnRequest->reason_category) === ReturnRequest::RESOLUTION_RETURN_REFUND,
        ]);
    }

    // ------------------------------------------------------------------ actions

    public function respond(Request $request, ReturnRequest $returnRequest): RedirectResponse
    {
        $this->authorizeSeller($request, $returnRequest);

        $isReject = $request->input('action') === ReturnRefundService::ACTION_REJECT;

        $validated = $request->validate([
            'action' => ['required', Rule::in([
                ReturnRefundService::ACTION_APPROVE_RETURN,
                ReturnRefundService::ACTION_APPROVE_REFUND_ONLY,
                ReturnRefundService::ACTION_REJECT,
            ])],
            // A rejection must be explained; on an approval the note is optional and can be short.
            'reason' => $isReject
                ? ['required', 'string', 'min:10', 'max:300']
                : ['nullable', 'string', 'max:300'],
            'acknowledge_deduction' => ['accepted_if:action,' . ReturnRefundService::ACTION_APPROVE_RETURN . ',' . ReturnRefundService::ACTION_APPROVE_REFUND_ONLY],
        ], [
            'action.required'                     => 'Please choose how you want to respond to this request.',
            'reason.required'                     => 'Please explain why you are rejecting this request (10 to 300 characters).',
            'reason.min'                          => 'Your explanation must be at least 10 characters.',
            'reason.max'                          => 'Your explanation must not exceed 300 characters.',
            'acknowledge_deduction.accepted_if'   => 'Please confirm that you understand the deduction that will be charged to your wallet.',
        ]);

        $updated = $this->returnRefundService->sellerRespond(
            $returnRequest,
            (int) $request->user()->id,
            $validated['action'],
            $validated['reason'] ?? null
        );

        $message = match ($validated['action']) {
            ReturnRefundService::ACTION_APPROVE_RETURN =>
                'Return approved. Storkia Logistics will collect the item from the buyer and send it to you. The refund deduction is applied to your wallet once you confirm you received it.',
            ReturnRefundService::ACTION_APPROVE_REFUND_ONLY =>
                'Refund approved. The buyer keeps the item. ₱' . number_format((float) $updated->seller_deduction_amount, 2) . ' was deducted from your wallet and the refund was queued for payout.',
            default =>
                'Request rejected. The buyer can escalate it to Storkia Admin, who will make the final decision.',
        };

        return redirect()->route('seller.returns.show', $returnRequest->id)->with('success', $message);
    }

    public function confirmReceipt(Request $request, ReturnRequest $returnRequest): RedirectResponse
    {
        $this->authorizeSeller($request, $returnRequest);

        $isProblem = $request->input('outcome') === ReturnRequest::RECEIPT_NOT_AS_EXPECTED;

        $validated = $request->validate([
            'outcome' => ['required', Rule::in([ReturnRequest::RECEIPT_OK, ReturnRequest::RECEIPT_NOT_AS_EXPECTED])],
            // A reported problem needs a description and photos for Admin; a good-order receipt needs neither.
            'notes'   => $isProblem
                ? ['required', 'string', 'min:10', 'max:500']
                : ['nullable', 'string', 'max:500'],
            'receipt_photos'   => $isProblem
                ? ['required', 'array', 'min:1', 'max:5']
                : ['nullable', 'array', 'max:5'],
            'receipt_photos.*' => ['image', 'mimes:jpeg,png,jpg,webp', 'max:5120'],
            'acknowledge_deduction' => ['accepted_if:outcome,' . ReturnRequest::RECEIPT_OK],
        ], [
            'outcome.required'                  => 'Please tell us whether the returned parcel arrived in good order.',
            'notes.required'                    => 'Please describe what is wrong with the returned parcel (at least 10 characters).',
            'notes.min'                         => 'Your description must be at least 10 characters.',
            'receipt_photos.required'           => 'Please upload 1 to 5 photos of the returned parcel so Admin can review it.',
            'receipt_photos.min'                => 'Please upload at least 1 photo of the returned parcel.',
            'receipt_photos.max'                => 'You may upload a maximum of 5 photos.',
            'receipt_photos.*.image'            => 'Photos must be JPG, PNG or WebP files.',
            'receipt_photos.*.mimes'            => 'Photos must be JPG, PNG or WebP files.',
            'receipt_photos.*.max'              => 'Each photo must not exceed 5MB.',
            'acknowledge_deduction.accepted_if' => 'Please confirm that you understand the deduction that will be charged to your wallet.',
        ]);

        $storedPhotos = [];
        foreach ($request->file('receipt_photos', []) as $file) {
            $storedPhotos[] = $this->storeReceiptPhoto($file);
        }

        try {
            $updated = $this->returnRefundService->sellerConfirmReceipt(
                $returnRequest,
                (int) $request->user()->id,
                $validated['outcome'],
                $validated['notes'] ?? null,
                $storedPhotos
            );
        } catch (\Throwable $e) {
            // Nothing was saved, so do not leave the uploaded photos behind.
            foreach ($storedPhotos as $path) {
                Storage::disk('public')->delete($path);
            }

            throw $e;
        }

        $message = $validated['outcome'] === ReturnRequest::RECEIPT_OK
            ? 'Receipt confirmed. Product stock was restored, ₱' . number_format((float) $updated->seller_deduction_amount, 2)
                . ' was deducted from your wallet, and the buyer\'s refund was queued for payout.'
            : 'Your report was sent to Storkia Admin. They will review the returned parcel and make the final decision.';

        return redirect()->route('seller.returns.show', $returnRequest->id)->with('success', $message);
    }

    /**
     * The old order-page form (decision: approved|rejected) posts here until the page itself
     * is updated. Send the seller to the new response page, where the deduction is shown first.
     */
    public function legacyRespond(Request $request, SellerOrder $sellerOrder): RedirectResponse
    {
        abort_unless((int) $sellerOrder->seller_id === (int) $request->user()->id, 403);

        $existing = ReturnRequest::where('seller_order_id', $sellerOrder->id)->first();

        if (! $existing) {
            return redirect()
                ->route('seller.orders.show', $sellerOrder->id)
                ->withErrors(['return' => 'There is no return or refund request to respond to on this order.']);
        }

        return redirect()->route('seller.returns.show', $existing->id);
    }

    // ------------------------------------------------------------------ helpers

    protected function authorizeSeller(Request $request, ReturnRequest $returnRequest): void
    {
        $returnRequest->loadMissing('sellerOrder');

        abort_unless(
            $returnRequest->sellerOrder && (int) $returnRequest->sellerOrder->seller_id === (int) $request->user()->id,
            403
        );
    }

    /**
     * Everything the seller must see before money moves: what is cancelled, what is charged,
     * and what that does to the wallet. Once the refund is settled the stored snapshot is
     * shown instead, so the page always matches what actually happened.
     *
     * @return array<string, mixed>
     */
    protected function buildDeduction(ReturnRequest $returnRequest, SellerOrder $sellerOrder): array
    {
        if ($returnRequest->ledger_settled_at !== null) {
            $snap = (array) ($returnRequest->settlement_snapshot ?? []);
            $returnPaid = round((float) ($snap['return_rider_pay'] ?? 0) + (float) ($snap['return_courier_pay'] ?? 0), 2);
            $total = round((float) ($snap['seller_deduction_total'] ?? $returnRequest->seller_deduction_amount), 2);

            return [
                'settled'            => true,
                'payment_method'     => $snap['payment_method'] ?? $sellerOrder->effective_payment_method,
                'earnings_cancelled' => round((float) ($snap['seller_escrow_reversed'] ?? $snap['seller_net_cancelled'] ?? 0), 2),
                'commission_waived'  => round((float) ($snap['admin_commission_waived'] ?? 0), 2),
                'logistics'          => round((float) ($snap['logistics_earning'] ?? 0), 2),
                'rider'              => round((float) ($snap['rider_earning'] ?? 0), 2),
                'gcash_recovery'     => round((float) ($snap['gcash_refund_recovery'] ?? 0), 2),
                'return_paid'        => $returnPaid,
                'return_estimate'    => 0.00,
                'total_refund_only'  => $total,
                'total_return_refund' => $total,
                'total_now'          => $total,
                'wallet_available'   => null,
                'after_refund_only'  => null,
                'after_return_refund' => null,
                'after_now'          => null,
                'arrears_cap'        => RoleWalletService::GCASH_ARREARS_CAP,
            ];
        }

        $base = $this->ledger->estimateSellerDeduction($sellerOrder, $returnRequest);

        $fixed = round($base['logistics_earning'] + $base['rider_earning'] + $base['gcash_refund_recovery'], 2);
        $returnPaid = round($base['return_rider_pay'] + $base['return_courier_pay'], 2);
        $returnEstimate = $this->estimateUnpaidReturnLegs($sellerOrder, $returnRequest->returnShipment);

        $totalRefundOnly = round($fixed + $returnPaid, 2);
        $totalReturnRefund = round($fixed + $returnPaid + $returnEstimate, 2);

        $totalNow = match ($returnRequest->approved_resolution) {
            ReturnRequest::RESOLUTION_RETURN_REFUND => $totalReturnRefund,
            ReturnRequest::RESOLUTION_REFUND_ONLY   => $totalRefundOnly,
            default                                 => null,
        };

        $wallet = RoleWallet::where('owner_type', 'Seller')
            ->where('owner_id', $returnRequest->seller_profile_id)
            ->first();
        $available = round((float) ($wallet?->available_balance ?? 0), 2);

        return [
            'settled'             => false,
            'payment_method'      => $base['payment_method'],
            'earnings_cancelled'  => $base['seller_net_cancelled'],
            'commission_waived'   => $base['admin_commission_waived'],
            'logistics'           => $base['logistics_earning'],
            'rider'               => $base['rider_earning'],
            'gcash_recovery'      => $base['gcash_refund_recovery'],
            'return_paid'         => $returnPaid,
            'return_estimate'     => $returnEstimate,
            'total_refund_only'   => $totalRefundOnly,
            'total_return_refund' => $totalReturnRefund,
            'total_now'           => $totalNow,
            'wallet_available'    => $available,
            'after_refund_only'   => round($available - $totalRefundOnly, 2),
            'after_return_refund' => round($available - $totalReturnRefund, 2),
            'after_now'           => $totalNow === null ? null : round($available - $totalNow, 2),
            'arrears_cap'         => RoleWalletService::GCASH_ARREARS_CAP,
        ];
    }

    /**
     * The return trip is paid leg by leg (Rider: buyer -> hub, Courier: hub -> seller) with the
     * same distance-scaled formula the forward trip uses. Legs already paid are in the ledger
     * estimate; this adds the legs that are still to come. A failed estimate counts as zero:
     * the warning is then understated, but never blocks the seller from responding.
     */
    protected function estimateUnpaidReturnLegs(SellerOrder $sellerOrder, ?ReturnShipment $returnShipment): float
    {
        try {
            $forward = $sellerOrder->shipment;

            $weight = (float) ($returnShipment?->total_weight ?: $sellerOrder->total_weight);
            $buyerSnap = (array) ($returnShipment?->pickup_address_snapshot ?: ($sellerOrder->delivery_address_snapshot ?? []));
            $hubSnap = (array) ($returnShipment?->hub_address_snapshot ?: ($forward?->hub_address_snapshot ?? []));
            $sellerSnap = (array) ($returnShipment?->return_address_snapshot ?: ($sellerOrder->pickup_address_snapshot ?? []));

            $total = 0.0;

            if (! $returnShipment?->rider_paid_at) {
                $total += (float) ($this->commissionCalculator
                    ->calculateDistanceScaledDriverPay('delivery', $buyerSnap, $hubSnap, $weight)['total_pay'] ?? 0);
            }

            if (! $returnShipment?->courier_paid_at) {
                $total += (float) ($this->commissionCalculator
                    ->calculateDistanceScaledDriverPay('pickup', $hubSnap, $sellerSnap, $weight)['total_pay'] ?? 0);
            }

            return round($total, 2);
        } catch (\Throwable $e) {
            return 0.00;
        }
    }

    protected function storeReceiptPhoto($file): string
    {
        $path = 'return_receipts/receipt_' . uniqid('', true) . '.webp';

        try {
            $encoded = (new ImageManager(new Driver()))
                ->read($file->getRealPath())
                ->scaleDown(1200, 1200)
                ->toWebp(84);

            Storage::disk('public')->put($path, (string) $encoded);

            return $path;
        } catch (\Throwable $e) {
            return $file->store('return_receipts', 'public');
        }
    }
}