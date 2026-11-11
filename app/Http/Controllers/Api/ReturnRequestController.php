<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\FormatsReturnRequests;
use App\Http\Controllers\Controller;
use App\Models\ReturnRequest;
use App\Models\SellerOrder;
use App\Services\ReturnRefundService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\ImageManager;

class ReturnRequestController extends Controller
{
    use FormatsReturnRequests;

    public function __construct(
        protected ReturnRefundService $returnRefundService
    ) {}

    public function options(Request $request, $id): JsonResponse
    {
        $sellerOrder = $this->findOwnedOrder($request, $id);
        if (! $sellerOrder) {
            return $this->failure('Order not found.', 404, 'order_not_found');
        }

        $sellerOrder->load(['items', 'sellerProfile']);

        $existing = ReturnRequest::with($this->requestRelations())
            ->where('seller_order_id', $sellerOrder->id)
            ->first();

        $blocker = $existing
            ? 'A return or refund request already exists for this order.'
            : $this->eligibilityError($sellerOrder);

        $identityStatus = $request->user()->identityVerification?->status;
        $identityVerified = $identityStatus === 'verified';

        return response()->json([
            'success' => true,
            'data'    => [
                'seller_order'         => [
                    'id'                  => (int) $sellerOrder->id,
                    'seller_order_number' => $sellerOrder->seller_order_number,
                    'status'              => $sellerOrder->status,
                    'total_amount'        => (float) $sellerOrder->total_amount,
                    'seller_name'         => $sellerOrder->sellerProfile?->business_name,
                    'items'               => $sellerOrder->items->map(fn ($item) => [
                        'product_name' => $item->product_name,
                        'variant_name' => $item->variant_name,
                        'quantity'     => (int) $item->quantity,
                        'image_url'    => $this->returnStorageUrl($item->product_image),
                    ])->values()->all(),
                ],
                'existing_request'     => $existing ? $this->formatReturnRequest($existing) : null,
                'can_request'          => $blocker === null && $identityVerified,
                'blocker'              => $blocker,
                'window_ends_at'       => $sellerOrder->confirmation_deadline_at?->toIso8601String(),
                'identity_status'      => $identityStatus,
                'identity_verified'    => $identityVerified,
                'identity_message'     => $identityVerified ? null : $this->identityMessage(),
                'reasons'              => collect(ReturnRequest::reasonCatalog())
                    ->map(fn (array $reason, string $key) => [
                        'key'                => $key,
                        'resolution'         => $reason['resolution'],
                        'hint'               => $reason['hint'],
                        'refund_only_forced' => $reason['resolution'] === ReturnRequest::RESOLUTION_REFUND_ONLY,
                    ])
                    ->values()
                    ->all(),
                'default_account_name' => $request->user()->full_name,
                'seller_reply_hours'   => ReturnRequest::SELLER_RESPONSE_HOURS,
                'refund_payout_hours'  => ReturnRequest::REFUND_PAYOUT_HOURS,
                'evidence'             => [
                    'min'    => 1,
                    'max'    => 5,
                    'max_kb' => 5120,
                ],
            ],
        ]);
    }

    public function store(Request $request, $id): JsonResponse
    {
        $sellerOrder = $this->findOwnedOrder($request, $id);
        if (! $sellerOrder) {
            return $this->failure('Order not found.', 404, 'order_not_found');
        }

        if (! $this->isIdentityVerified($request)) {
            return $this->failure($this->identityMessage(), 422, 'identity_verification_required');
        }

        if (ReturnRequest::where('seller_order_id', $sellerOrder->id)->exists()) {
            return $this->failure('A return or refund request already exists for this order.', 422, 'request_exists');
        }

        $blocker = $this->eligibilityError($sellerOrder);
        if ($blocker) {
            return $this->failure($blocker, 422, 'not_eligible');
        }

        $request->merge([
            'refund_account_number' => $this->normalizeMobileNumber((string) $request->input('refund_account_number')),
        ]);

        $validated = $request->validate([
            'reason_category'       => ['required', 'string', Rule::in(array_keys(ReturnRequest::reasonCatalog()))],
            'requested_resolution'  => ['nullable', Rule::in([ReturnRequest::RESOLUTION_RETURN_REFUND, ReturnRequest::RESOLUTION_REFUND_ONLY])],
            'reason'                => ['required', 'string', 'min:10', 'max:1000'],
            'refund_account_name'   => ['required', 'string', 'max:120'],
            'refund_account_number' => ['required', 'regex:/^09\d{9}$/'],
            'evidence_photos'       => ['required', 'array', 'min:1', 'max:5'],
            'evidence_photos.*'     => ['required', 'image', 'mimes:jpeg,png,jpg,webp', 'max:5120'],
        ], [
            'reason_category.required'       => 'Please choose what went wrong with your order.',
            'reason_category.in'             => 'Please choose one of the listed reasons.',
            'reason.required'                => 'Please describe the problem.',
            'reason.min'                     => 'Please provide at least 10 characters explaining the problem.',
            'refund_account_name.required'   => 'Please enter the GCash account name for your refund.',
            'refund_account_number.required' => 'Please enter your 11-digit GCash number starting with 09.',
            'refund_account_number.regex'    => 'Your GCash number must be a valid 11-digit mobile number starting with 09.',
            'evidence_photos.required'       => 'Please upload 1 to 5 photos as evidence.',
            'evidence_photos.min'            => 'Please upload at least 1 evidence photo.',
            'evidence_photos.max'            => 'You may upload a maximum of 5 evidence photos.',
            'evidence_photos.*.image'        => 'Evidence files must be photos (JPG, PNG or WebP).',
            'evidence_photos.*.mimes'        => 'Evidence photos must be JPG, PNG or WebP.',
            'evidence_photos.*.max'          => 'Each evidence photo must not exceed 5MB.',
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
                'refund_account_name'   => $validated['refund_account_name'],
                'refund_account_number' => $validated['refund_account_number'],
            ]);
        } catch (ValidationException $e) {
            $this->deletePhotos($storedPhotos);

            return $this->validationFailure($e);
        } catch (\Throwable $e) {
            $this->deletePhotos($storedPhotos);

            throw $e;
        }

        $returnRequest->load($this->requestRelations());

        return response()->json([
            'success' => true,
            'message' => 'Your return/refund request was sent to the seller. Automatic order completion is paused while it is under review.',
            'data'    => $this->formatReturnRequest($returnRequest),
        ], 201);
    }

    public function show(Request $request, $id): JsonResponse
    {
        $returnRequest = $this->findOwnedRequest($request, $id);
        if (! $returnRequest) {
            return $this->failure('Return request not found.', 404, 'request_not_found');
        }

        return response()->json([
            'success' => true,
            'data'    => $this->formatReturnRequest($returnRequest),
        ]);
    }

    public function escalate(Request $request, $id): JsonResponse
    {
        $returnRequest = $this->findOwnedRequest($request, $id);
        if (! $returnRequest) {
            return $this->failure('Return request not found.', 404, 'request_not_found');
        }

        $validated = $request->validate([
            'escalation_note' => ['nullable', 'string', 'max:500'],
        ], [
            'escalation_note.max' => 'Your note must not exceed 500 characters.',
        ]);

        try {
            $updated = $this->returnRefundService->buyerEscalate(
                $returnRequest,
                (int) $request->user()->id,
                $validated['escalation_note'] ?? null
            );
        } catch (ValidationException $e) {
            return $this->validationFailure($e);
        }

        $updated->load($this->requestRelations());

        return response()->json([
            'success' => true,
            'message' => 'Your request was escalated to Storkia Admin for a final decision.',
            'data'    => $this->formatReturnRequest($updated),
        ]);
    }

    public function legacyReport(Request $request, $id): JsonResponse
    {
        $sellerOrder = $this->findOwnedOrder($request, $id);
        if (! $sellerOrder) {
            return $this->failure('Order not found.', 404, 'order_not_found');
        }

        return response()->json([
            'success'         => false,
            'code'            => 'use_return_request',
            'message'         => 'Return & Refund requests are now sent through the new request form. Please update the Storkia app and open the order to request it.',
            'seller_order_id' => (int) $sellerOrder->id,
        ], 410);
    }

    public function legacyEscalate(Request $request, $id): JsonResponse
    {
        $sellerOrder = $this->findOwnedOrder($request, $id);
        if (! $sellerOrder) {
            return $this->failure('Order not found.', 404, 'order_not_found');
        }

        $existing = ReturnRequest::where('seller_order_id', $sellerOrder->id)->first();

        if (! $existing) {
            return $this->failure('There is no return or refund request to escalate for this order.', 404, 'request_not_found');
        }

        return response()->json([
            'success'           => false,
            'code'              => 'use_return_request',
            'message'           => 'Please escalate from the return request status page in the updated Storkia app.',
            'return_request_id' => (int) $existing->id,
        ], 410);
    }

    protected function requestRelations(): array
    {
        return [
            'sellerOrder.sellerProfile',
            'returnShipment.events',
            'returnShipment.rider',
        ];
    }

    protected function findOwnedOrder(Request $request, $id): ?SellerOrder
    {
        $sellerOrder = SellerOrder::find($id);

        if (! $sellerOrder || (int) $sellerOrder->buyer_id !== (int) $request->user()->id) {
            return null;
        }

        return $sellerOrder;
    }

    protected function findOwnedRequest(Request $request, $id): ?ReturnRequest
    {
        $returnRequest = ReturnRequest::with($this->requestRelations())->find($id);

        if (! $returnRequest || (int) $returnRequest->buyer_id !== (int) $request->user()->id) {
            return null;
        }

        return $returnRequest;
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

    protected function storeEvidencePhoto(UploadedFile $file): string
    {
        $path = 'return_evidence/return_' . uniqid('', true) . '.webp';

        foreach (['encodeWebpModern', 'encodeWebpClassic'] as $encoder) {
            try {
                Storage::disk('public')->put($path, $this->{$encoder}($file->getRealPath()));

                return $path;
            } catch (\Throwable) {
                continue;
            }
        }

        return $file->store('return_evidence', 'public');
    }

    protected function encodeWebpModern(string $realPath): string
    {
        return ImageManager::usingDriver(Driver::class)
            ->decode($realPath)
            ->scaleDown(width: 1200, height: 1200)
            ->encode(new WebpEncoder(quality: 84))
            ->toString();
    }

    protected function encodeWebpClassic(string $realPath): string
    {
        return (string) (new ImageManager(new Driver()))
            ->read($realPath)
            ->scaleDown(1200, 1200)
            ->toWebp(84);
    }

    protected function deletePhotos(array $paths): void
    {
        foreach ($paths as $path) {
            Storage::disk('public')->delete($path);
        }
    }

    protected function validationFailure(ValidationException $e): JsonResponse
    {
        $message = collect($e->errors())->flatten()->first() ?? 'This request could not be completed.';

        return $this->failure($message, 422, 'request_rejected', $e->errors());
    }

    protected function failure(string $message, int $status, string $code, array $errors = []): JsonResponse
    {
        $payload = [
            'success' => false,
            'code'    => $code,
            'message' => $message,
        ];

        if ($errors !== []) {
            $payload['errors'] = $errors;
        }

        return response()->json($payload, $status);
    }
}