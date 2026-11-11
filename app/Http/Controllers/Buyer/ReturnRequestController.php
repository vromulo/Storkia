<?php

namespace App\Http\Controllers\Buyer;

use App\Http\Controllers\Controller;
use App\Models\ReturnRequest;
use App\Models\SellerOrder;
use App\Services\ReturnRefundService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

class ReturnRequestController extends Controller
{
    public function __construct(
        protected ReturnRefundService $returnRefundService
    ) {
    }

    public function create(Request $request, SellerOrder $sellerOrder): View|RedirectResponse
    {
        $this->authorizeBuyer($request, $sellerOrder);

        $existing = ReturnRequest::where('seller_order_id', $sellerOrder->id)->first();
        if ($existing) {
            return redirect()->route('user.returns.show', $existing->id);
        }

        $blocker = $this->eligibilityError($sellerOrder);
        if ($blocker) {
            return redirect()->route('user.orders.show', $sellerOrder->id)->withErrors(['return' => $blocker]);
        }

        if (! $this->isIdentityVerified($request)) {
            return redirect()->route('user.orders.show', $sellerOrder->id)
                ->withErrors(['identity_verification' => $this->identityMessage()]);
        }

        $sellerOrder->load(['items', 'sellerProfile']);

        return view('buyer.option.orders.return-request', [
            'sellerOrder'   => $sellerOrder,
            'reasonCatalog' => ReturnRequest::reasonCatalog(),
            'defaultName'   => $request->user()->full_name,
        ]);
    }

    public function show(Request $request, ReturnRequest $returnRequest): View
    {
        abort_unless((int) $returnRequest->buyer_id === (int) $request->user()->id, 403);

        $returnRequest->load([
            'sellerOrder.sellerProfile',
            'returnShipment.events',
            'returnShipment.rider',
            'returnShipment.courier',
        ]);

        return view('buyer.option.orders.return-status', [
            'returnRequest'  => $returnRequest,
            'sellerOrder'    => $returnRequest->sellerOrder,
            'returnShipment' => $returnRequest->returnShipment,
        ]);
    }

    public function store(Request $request, SellerOrder $sellerOrder): RedirectResponse
    {
        $this->authorizeBuyer($request, $sellerOrder);

        if (! $this->isIdentityVerified($request)) {
            throw ValidationException::withMessages(['identity_verification' => $this->identityMessage()]);
        }

        $payoutMethod = strtoupper(trim((string) $request->input('refund_payout_method', 'WALLET')));
        if (! in_array($payoutMethod, ['WALLET', 'GCASH'], true)) {
            $payoutMethod = 'WALLET';
        }

        $request->merge([
            'refund_payout_method'  => $payoutMethod,
            'refund_account_number' => $this->normalizeMobileNumber((string) $request->input('refund_account_number')),
        ]);

        $validated = $request->validate([
            'reason_category'       => ['required', 'string', Rule::in(array_keys(ReturnRequest::reasonCatalog()))],
            'requested_resolution'  => ['nullable', Rule::in([ReturnRequest::RESOLUTION_RETURN_REFUND, ReturnRequest::RESOLUTION_REFUND_ONLY])],
            'reason'                => ['required', 'string', 'min:10', 'max:1000'],
            'refund_payout_method'  => ['required', Rule::in(['WALLET', 'GCASH'])],
            'refund_account_name'   => ['required_if:refund_payout_method,GCASH', 'nullable', 'string', 'max:120'],
            'refund_account_number' => ['required_if:refund_payout_method,GCASH', 'nullable', 'regex:/^09\d{9}$/'],
            'evidence_photos'       => ['required', 'array', 'min:1', 'max:5'],
            'evidence_photos.*'     => ['required', 'image', 'mimes:jpeg,png,jpg,webp', 'max:5120'],
        ], [
            'reason_category.required'          => 'Please choose what went wrong with your order.',
            'reason_category.in'                => 'Please choose one of the listed reasons.',
            'reason.required'                   => 'Please describe the problem.',
            'reason.min'                        => 'Please provide at least 10 characters explaining the problem.',
            'refund_account_name.required_if'   => 'Please enter the GCash account name for your refund.',
            'refund_account_number.required_if' => 'Please enter your 11-digit GCash number starting with 09.',
            'refund_account_number.regex'       => 'Your GCash number must be a valid 11-digit mobile number starting with 09.',
            'evidence_photos.required'          => 'Please upload 1 to 5 photos as evidence.',
            'evidence_photos.min'               => 'Please upload at least 1 evidence photo.',
            'evidence_photos.max'               => 'You may upload a maximum of 5 evidence photos.',
            'evidence_photos.*.image'           => 'Evidence files must be photos (JPG, PNG or WebP).',
            'evidence_photos.*.mimes'           => 'Evidence photos must be JPG, PNG or WebP.',
            'evidence_photos.*.max'             => 'Each evidence photo must not exceed 5MB.',
        ]);

        $storedPhotos = [];
        foreach ($request->file('evidence_photos', []) as $file) {
            $storedPhotos[] = $this->storeEvidencePhoto($file);
        }

        try {
            $returnRequest = $this->returnRefundService->submit($sellerOrder, (int) $request->user()->id, [
                'reason_category'       => $validated['reason_category'],
                'reason'                => $validated['reason'],
                'requested_resolution'  => $validated['requested_resolution'] ?? null,
                'evidence_photos'       => $storedPhotos,
                'refund_payout_method'  => $validated['refund_payout_method'],
                'refund_account_name'   => $validated['refund_payout_method'] === 'GCASH'
                    ? $validated['refund_account_name']
                    : $request->user()->full_name,
                'refund_account_number' => $validated['refund_payout_method'] === 'GCASH'
                    ? $validated['refund_account_number']
                    : null,
            ]);
        } catch (\Throwable $e) {
            foreach ($storedPhotos as $path) {
                Storage::disk('public')->delete($path);
            }

            throw $e;
        }

        return redirect()
            ->route('user.returns.show', $returnRequest->id)
            ->with('success', 'Your return/refund request was sent to the seller. Automatic order completion is paused while it is under review.');
    }

    public function escalate(Request $request, ReturnRequest $returnRequest): RedirectResponse
    {
        abort_unless((int) $returnRequest->buyer_id === (int) $request->user()->id, 403);

        $validated = $request->validate([
            'escalation_note' => ['nullable', 'string', 'max:500'],
        ], [
            'escalation_note.max' => 'Your note must not exceed 500 characters.',
        ]);

        $this->returnRefundService->buyerEscalate(
            $returnRequest,
            (int) $request->user()->id,
            $validated['escalation_note'] ?? null
        );

        return redirect()
            ->route('user.returns.show', $returnRequest->id)
            ->with('success', 'Your request was escalated to Storkia Admin for a final decision.');
    }

    public function legacyReport(Request $request, SellerOrder $sellerOrder): RedirectResponse
    {
        $this->authorizeBuyer($request, $sellerOrder);

        return redirect()->route('user.orders.return.create', $sellerOrder->id);
    }

    public function legacyEscalate(Request $request, SellerOrder $sellerOrder): RedirectResponse
    {
        $this->authorizeBuyer($request, $sellerOrder);

        $existing = ReturnRequest::where('seller_order_id', $sellerOrder->id)->first();

        if (! $existing) {
            return redirect()
                ->route('user.orders.show', $sellerOrder->id)
                ->withErrors(['return' => 'There is no return or refund request to escalate for this order.']);
        }

        return redirect()->route('user.returns.show', $existing->id);
    }

    protected function authorizeBuyer(Request $request, SellerOrder $sellerOrder): void
    {
        abort_unless((int) $sellerOrder->buyer_id === (int) $request->user()->id, 403);
    }

    protected function eligibilityError(SellerOrder $sellerOrder): ?string
    {
        if ($sellerOrder->status === 'completed') {
            return 'Completed orders can no longer be returned or refunded.';
        }

        if ($sellerOrder->status !== 'delivered') {
            return 'A return or refund can only be requested after the parcel is marked delivered.';
        }

        if ($sellerOrder->confirmation_deadline_at && $sellerOrder->confirmation_deadline_at->isPast()) {
            return 'The ' . SellerOrder::BUYER_CONFIRMATION_HOURS . '-hour window to request a return or refund has already expired.';
        }

        return null;
    }

    protected function isIdentityVerified(Request $request): bool
    {
        return $request->user()->identityVerification?->status === 'verified';
    }

    protected function identityMessage(): string
    {
        return 'Valid ID Verification Required: You must have an approved Valid Government ID on your account to request a Return & Refund. Please complete Identity Verification in your Account Management settings first.';
    }

    protected function normalizeMobileNumber(string $value): string
    {
        $digits = preg_replace('/\D/', '', $value);

        if (str_starts_with($digits, '63') && strlen($digits) === 12) {
            return '0' . substr($digits, 2);
        }

        if (strlen($digits) === 10 && str_starts_with($digits, '9')) {
            return '0' . $digits;
        }

        return $digits;
    }

    protected function storeEvidencePhoto($file): string
    {
        $path = 'return_evidence/return_' . uniqid('', true) . '.webp';

        try {
            $encoded = (new ImageManager(new Driver()))
                ->read($file->getRealPath())
                ->scaleDown(1200, 1200)
                ->toWebp(84);

            Storage::disk('public')->put($path, (string) $encoded);

            return $path;
        } catch (\Throwable $e) {
            return $file->store('return_evidence', 'public');
        }
    }
}