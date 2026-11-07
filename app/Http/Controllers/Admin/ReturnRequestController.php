<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ReturnRequest;
use App\Services\AuditLoggerService;
use App\Services\ReturnRefundService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Admin side of Return & Refund.
 *
 *   GET  /admin/returns                          -> queue (needs decision / refund payouts / in progress / done)
 *   GET  /admin/returns/{returnRequest}          -> one request: evidence, money, parcel timeline, actions
 *   POST /admin/returns/{returnRequest}/resolve      -> decide an escalated request or a disputed returned parcel
 *   POST /admin/returns/{returnRequest}/refund-paid  -> record the GCash refund that was sent to the buyer
 *
 * The rules (who may decide what, how money moves) live in ReturnRefundService.
 */
class ReturnRequestController extends Controller
{
    /** Queue tabs and the request statuses each one lists. */
    protected const TAB_STATUSES = [
        'needs_decision' => [
            ReturnRequest::STATUS_ESCALATED,
            ReturnRequest::STATUS_RECEIPT_DISPUTED,
        ],
        'refund_queue' => [
            ReturnRequest::STATUS_REFUND_PENDING,
        ],
        'in_progress' => [
            ReturnRequest::STATUS_PENDING_SELLER_RESPONSE,
            ReturnRequest::STATUS_REJECTED_BY_SELLER,
            ReturnRequest::STATUS_RETURN_IN_TRANSIT,
            ReturnRequest::STATUS_AWAITING_SELLER_CONFIRM,
        ],
        'completed' => [
            ReturnRequest::STATUS_REFUNDED,
            ReturnRequest::STATUS_REJECTED,
            ReturnRequest::STATUS_CLOSED,
        ],
    ];

    public function __construct(protected ReturnRefundService $returnRefundService)
    {
    }

    // ------------------------------------------------------------------ pages

    public function index(Request $request): View
    {
        $tab = (string) $request->query('tab', 'needs_decision');

        if ($tab !== 'all' && ! isset(self::TAB_STATUSES[$tab])) {
            $tab = 'needs_decision';
        }

        $search = trim((string) $request->query('search', ''));

        $counts = ['all' => ReturnRequest::count()];
        foreach (self::TAB_STATUSES as $key => $statuses) {
            $counts[$key] = ReturnRequest::whereIn('status', $statuses)->count();
        }

        $query = ReturnRequest::with([
            'sellerOrder.order',
            'buyer',
            'sellerProfile',
            'returnShipment',
        ]);

        if ($tab !== 'all') {
            $query->whereIn('status', self::TAB_STATUSES[$tab]);
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('request_number', 'like', "%{$search}%")
                    ->orWhereHas('sellerOrder', fn ($sq) => $sq->where('seller_order_number', 'like', "%{$search}%"))
                    ->orWhereHas('sellerProfile', fn ($sp) => $sp->where('business_name', 'like', "%{$search}%"))
                    ->orWhereHas('buyer', fn ($bq) => $bq->where('email', 'like', "%{$search}%"));
            });
        }

        if ($tab === 'refund_queue') {
            $query->orderBy('refund_due_at', 'asc')->orderBy('id', 'asc');
        } elseif (in_array($tab, ['needs_decision', 'in_progress'], true)) {
            $query->orderBy('created_at', 'asc')->orderBy('id', 'asc');
        } else {
            $query->orderByDesc('created_at')->orderByDesc('id');
        }

        $returnRequests = $query->paginate(15)->withQueryString();

        $refundQueueTotal = (float) ReturnRequest::awaitingRefundPayout()->sum('refund_amount');
        $overduePayouts = ReturnRequest::awaitingRefundPayout()
            ->whereNotNull('refund_due_at')
            ->where('refund_due_at', '<', now())
            ->count();

        return view('admin.returns.index', compact(
            'tab',
            'search',
            'counts',
            'returnRequests',
            'refundQueueTotal',
            'overduePayouts'
        ));
    }

    public function show(ReturnRequest $returnRequest): View
    {
        $returnRequest->load([
            'sellerOrder.order',
            'sellerOrder.items',
            'sellerOrder.buyer',
            'sellerProfile',
            'logisticsProfile',
            'returnShipment.events',
            'returnShipment.rider',
            'returnShipment.courier',
        ]);

        $canResolve = in_array($returnRequest->status, [
            ReturnRequest::STATUS_ESCALATED,
            ReturnRequest::STATUS_RECEIPT_DISPUTED,
        ], true);

        $canPayRefund = $returnRequest->refund_status === ReturnRequest::REFUND_PENDING_ADMIN;

        $isReceiptDispute = $returnRequest->status === ReturnRequest::STATUS_RECEIPT_DISPUTED;

        // An item that was never received cannot be sent back, so "return and refund" is not offered for it.
        $canApproveReturn = $canResolve
            && ! $isReceiptDispute
            && ReturnRequest::suggestedResolutionFor($returnRequest->reason_category) !== ReturnRequest::RESOLUTION_REFUND_ONLY;

        return view('admin.returns.show', [
            'returnRequest'    => $returnRequest,
            'sellerOrder'      => $returnRequest->sellerOrder,
            'returnShipment'   => $returnRequest->returnShipment,
            'canResolve'       => $canResolve,
            'canPayRefund'     => $canPayRefund,
            'isReceiptDispute' => $isReceiptDispute,
            'canApproveReturn' => $canApproveReturn,
            'payoutOverdue'    => $canPayRefund && $returnRequest->refund_due_at && $returnRequest->refund_due_at->isPast(),
        ]);
    }

    // ------------------------------------------------------------------ actions

    public function resolve(Request $request, ReturnRequest $returnRequest): RedirectResponse
    {
        $validated = $request->validate([
            'decision' => ['required', Rule::in([
                ReturnRequest::DECISION_APPROVED_RETURN,
                ReturnRequest::DECISION_APPROVED_REFUND_ONLY,
                ReturnRequest::DECISION_REJECTED,
            ])],
            'remarks'  => ['required', 'string', 'min:5', 'max:500'],
        ], [
            'decision.required' => 'Please choose a decision.',
            'decision.in'       => 'Please choose one of the listed decisions.',
            'remarks.required'  => 'Please add remarks explaining the decision.',
            'remarks.min'       => 'Remarks must be at least 5 characters.',
        ]);

        $adminId = (int) Auth::guard('admin')->id();

        $updated = $this->returnRefundService->adminResolve(
            $returnRequest,
            $adminId,
            $validated['decision'],
            $validated['remarks']
        );

        AuditLoggerService::log(
            action: 'return_request_resolved_by_admin',
            targetType: 'ReturnRequest',
            targetId: (int) $returnRequest->id,
            targetReference: $returnRequest->request_number,
            newValues: ['decision' => $validated['decision'], 'status' => $updated->status],
            reason: trim($validated['remarks'])
        );

        $message = match ($validated['decision']) {
            ReturnRequest::DECISION_REJECTED             => "{$returnRequest->request_number} was rejected. The order continues to completion.",
            ReturnRequest::DECISION_APPROVED_REFUND_ONLY => "{$returnRequest->request_number} was approved as a refund. It is now in the refund payout queue.",
            default                                      => "{$returnRequest->request_number} was approved. A return parcel was created for Logistics.",
        };

        return redirect()
            ->route('admin.returns.show', $returnRequest->id)
            ->with('success', $message);
    }

    public function markRefundPaid(Request $request, ReturnRequest $returnRequest): RedirectResponse
    {
        $validated = $request->validate([
            'refund_reference' => ['required', 'string', 'min:4', 'max:60'],
            'refund_remarks'   => ['nullable', 'string', 'max:300'],
        ], [
            'refund_reference.required' => 'Enter the GCash reference number of the refund you sent.',
            'refund_reference.min'      => 'The GCash reference number must be at least 4 characters.',
        ]);

        $updated = $this->returnRefundService->markRefundPaid(
            $returnRequest,
            (int) Auth::guard('admin')->id(),
            $validated['refund_reference'],
            $validated['refund_remarks'] ?? null
        );

        AuditLoggerService::log(
            action: 'return_refund_paid_by_admin',
            targetType: 'ReturnRequest',
            targetId: (int) $returnRequest->id,
            targetReference: $returnRequest->request_number,
            newValues: [
                'refund_amount'    => (float) $updated->refund_amount,
                'refund_reference' => $updated->refund_reference,
            ],
            reason: $validated['refund_remarks'] ?? 'GCash refund sent to the buyer.'
        );

        return redirect()
            ->route('admin.returns.show', $returnRequest->id)
            ->with('success', "Refund for {$returnRequest->request_number} was recorded as paid (Ref #{$updated->refund_reference}). The buyer was notified.");
    }
}