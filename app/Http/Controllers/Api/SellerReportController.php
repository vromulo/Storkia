<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SellerOrder;
use App\Models\SellerProfile;
use App\Models\SellerReport;
use App\Services\AuditLoggerService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

class SellerReportController extends Controller
{
    private const DUPLICATE_MESSAGE = 'You already have an open report against this store. Please wait for Storkia to review it before sending another one.';

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $status = $request->query('status', 'all');
        if (! is_string($status) || ! in_array($status, ['all', 'open', 'closed'], true)) {
            $status = 'all';
        }

        $baseQuery = SellerReport::with(['sellerProfile', 'sellerOrder'])
            ->where('reporter_id', $user->id);

        $counts = [
            'all'    => (clone $baseQuery)->count(),
            'open'   => (clone $baseQuery)->open()->count(),
            'closed' => (clone $baseQuery)->closed()->count(),
        ];

        $reportsQuery = clone $baseQuery;
        if ($status === 'open') {
            $reportsQuery->open();
        } elseif ($status === 'closed') {
            $reportsQuery->closed();
        }

        $perPage = min(max((int) $request->query('per_page', 10), 1), 30);

        $paginator = $reportsQuery->latest()->latest('id')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data'    => [
                'reports'    => $paginator->getCollection()
                    ->map(fn (SellerReport $report) => $this->formatReport($report))
                    ->values()
                    ->all(),
                'counts'     => $counts,
                'status'     => $status,
                'pagination' => [
                    'current_page' => $paginator->currentPage(),
                    'last_page'    => $paginator->lastPage(),
                    'per_page'     => $paginator->perPage(),
                    'total'        => $paginator->total(),
                ],
            ],
        ]);
    }

    public function context(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        $seller = SellerProfile::find($id);
        if (! $seller) {
            return $this->failure('This store could not be found.', 404, 'seller_not_found');
        }

        if ((int) $user->id === (int) $seller->user_id) {
            return response()->json([
                'success' => true,
                'data'    => [
                    'can_report' => false,
                    'message'    => 'You cannot report your own store.',
                ],
            ]);
        }

        $openReport = SellerReport::open()
            ->where('reporter_id', $user->id)
            ->where('seller_profile_id', $seller->id)
            ->latest()
            ->first();

        $orders = SellerOrder::where('buyer_id', $user->id)
            ->where('seller_profile_id', $seller->id)
            ->latest()
            ->limit(50)
            ->get(['id', 'seller_order_number', 'status', 'created_at']);

        $reasons = [];
        foreach (SellerReport::categoryCatalog() as $key => $meta) {
            $reasons[] = [
                'key'   => $key,
                'label' => $meta['label'],
                'hint'  => $meta['hint'],
            ];
        }

        return response()->json([
            'success' => true,
            'data'    => [
                'can_report'      => true,
                'seller'          => [
                    'id'            => (int) $seller->id,
                    'business_name' => $seller->business_name,
                    'logo_url'      => $this->logoUrl($seller),
                ],
                'has_open_report' => $openReport !== null,
                'open_report'     => $openReport ? [
                    'id'           => (int) $openReport->id,
                    'number'       => $openReport->report_number,
                    'status_label' => $openReport->status_label,
                ] : null,
                'orders'          => $orders->map(fn (SellerOrder $order) => [
                    'id'        => (int) $order->id,
                    'number'    => $order->seller_order_number,
                    'status'    => Str::headline((string) $order->status),
                    'placed_at' => $order->created_at?->format('M d, Y'),
                ])->values()->all(),
                'reasons'         => $reasons,
                'limits'          => [
                    'min_description' => SellerReport::MIN_DESCRIPTION_LENGTH,
                    'max_description' => SellerReport::MAX_DESCRIPTION_LENGTH,
                    'max_photos'      => SellerReport::MAX_EVIDENCE_PHOTOS,
                    'max_photo_kb'    => SellerReport::MAX_EVIDENCE_KB,
                ],
            ],
        ]);
    }

    public function store(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        $seller = SellerProfile::find($id);
        if (! $seller) {
            return $this->failure('This store could not be found.', 404, 'seller_not_found');
        }

        $request->merge([
            'description'     => trim((string) $request->input('description', '')),
            'seller_order_id' => $request->filled('seller_order_id') ? $request->input('seller_order_id') : null,
        ]);

        $request->validate($this->rules((int) $user->id, (int) $seller->id), $this->messages());

        if ((int) $user->id === (int) $seller->user_id) {
            return $this->failure('You cannot report your own store.', 403, 'own_store');
        }

        if (SellerReport::hasOpenReport((int) $user->id, (int) $seller->id)) {
            return $this->failure(self::DUPLICATE_MESSAGE, 409, 'open_report_exists');
        }

        $storedPhotos = [];
        foreach ($request->file('evidence_photos', []) as $file) {
            $storedPhotos[] = $this->storeEvidencePhoto($file);
        }

        try {
            $report = DB::transaction(function () use ($request, $user, $seller, $storedPhotos) {
                return SellerReport::create([
                    'report_number'     => SellerReport::generateReportNumber(),
                    'reporter_id'       => $user->id,
                    'seller_profile_id' => $seller->id,
                    'seller_order_id'   => $request->input('seller_order_id'),
                    'reason_category'   => $request->input('reason_category'),
                    'description'       => $request->input('description'),
                    'evidence_photos'   => $storedPhotos ?: null,
                    'status'            => SellerReport::STATUS_PENDING,
                ]);
            });
        } catch (UniqueConstraintViolationException $e) {
            $this->deleteEvidencePhotos($storedPhotos);

            return $this->failure(self::DUPLICATE_MESSAGE, 409, 'open_report_exists');
        } catch (\Throwable $e) {
            $this->deleteEvidencePhotos($storedPhotos);
            report($e);

            return $this->failure('We could not submit your report right now. Please try again in a moment.', 500, 'report_failed');
        }

        AuditLoggerService::log(
            action: 'buyer_reported_seller',
            targetType: 'SellerProfile',
            targetId: (int) $seller->id,
            targetReference: $report->report_number,
            newValues: [
                'reason_category' => $report->reason_category,
                'seller_order_id' => $report->seller_order_id,
                'evidence_count'  => count($storedPhotos),
            ],
            reason: Str::limit($report->description, 500, '')
        );

        $report->load(['sellerProfile', 'sellerOrder']);

        return response()->json([
            'success' => true,
            'message' => "Your report ({$report->report_number}) has been submitted. Storkia will review it.",
            'data'    => [
                'report_number' => $report->report_number,
                'report'        => $this->formatReport($report),
            ],
        ], 201);
    }

    protected function rules(int $buyerId, int $sellerProfileId): array
    {
        return [
            'reason_category'   => ['required', 'string', Rule::in(SellerReport::categoryKeys())],
            'description'       => [
                'required',
                'string',
                'min:' . SellerReport::MIN_DESCRIPTION_LENGTH,
                'max:' . SellerReport::MAX_DESCRIPTION_LENGTH,
            ],
            'seller_order_id'   => [
                'nullable',
                'integer',
                Rule::exists('seller_orders', 'id')->where(
                    fn ($query) => $query
                        ->where('buyer_id', $buyerId)
                        ->where('seller_profile_id', $sellerProfileId)
                ),
            ],
            'evidence_photos'   => ['nullable', 'array', 'max:' . SellerReport::MAX_EVIDENCE_PHOTOS],
            'evidence_photos.*' => [
                'file',
                'mimetypes:image/jpeg,image/png,image/webp',
                'mimes:jpg,jpeg,png,webp',
                'max:' . SellerReport::MAX_EVIDENCE_KB,
            ],
        ];
    }

    protected function messages(): array
    {
        return [
            'reason_category.required'    => 'Please choose what you are reporting this seller for.',
            'reason_category.in'          => 'Please choose one of the listed reasons.',
            'description.required'        => 'Please describe what happened.',
            'description.min'             => 'Please give a little more detail (at least ' . SellerReport::MIN_DESCRIPTION_LENGTH . ' characters).',
            'description.max'             => 'Your description is too long (maximum ' . SellerReport::MAX_DESCRIPTION_LENGTH . ' characters).',
            'seller_order_id.exists'      => 'That order does not belong to you or to this seller.',
            'evidence_photos.max'         => 'You may upload a maximum of ' . SellerReport::MAX_EVIDENCE_PHOTOS . ' evidence photos.',
            'evidence_photos.*.file'      => 'Each evidence file must be a valid upload.',
            'evidence_photos.*.mimetypes' => 'Evidence photos must be JPG, PNG or WebP.',
            'evidence_photos.*.mimes'     => 'Evidence photos must be JPG, PNG or WebP.',
            'evidence_photos.*.max'       => 'Each evidence photo must not exceed 5MB.',
        ];
    }

    protected function formatReport(SellerReport $report): array
    {
        $seller = $report->sellerProfile;
        $order = $report->sellerOrder;
        [$tone, $note] = $this->statusMeta((string) $report->status);

        return [
            'id'              => (int) $report->id,
            'report_number'   => $report->report_number,
            'status'          => $report->status,
            'status_label'    => $report->status_label,
            'status_tone'     => $tone,
            'status_note'     => $note,
            'is_open'         => $report->isOpen(),
            'is_closed'       => $report->isClosed(),
            'reason_category' => $report->reason_category,
            'category_label'  => $report->category_label,
            'description'     => $report->description,
            'evidence_urls'   => $report->evidence_urls,
            'seller'          => $seller ? [
                'id'            => (int) $seller->id,
                'business_name' => $seller->business_name,
                'logo_url'      => $this->logoUrl($seller),
            ] : null,
            'order'           => $order ? [
                'id'     => (int) $order->id,
                'number' => $order->seller_order_number,
            ] : null,
            'created_at'      => $report->created_at?->toIso8601String(),
            'created_label'   => $report->created_at?->format('M d, Y \a\t h:i A'),
            'resolved_at'     => $report->resolved_at?->toIso8601String(),
            'resolved_label'  => $report->isClosed() && $report->resolved_at
                ? 'Closed on ' . $report->resolved_at->format('M d, Y')
                : null,
        ];
    }

    protected function statusMeta(string $status): array
    {
        return match ($status) {
            SellerReport::STATUS_RESOLVED => [
                'success',
                'Storkia reviewed your report and took action on this store. Thank you for helping keep Storkia safe.',
            ],
            SellerReport::STATUS_UNDER_REVIEW => [
                'info',
                'Storkia is reviewing your report. This can take a little while.',
            ],
            SellerReport::STATUS_DISMISSED => [
                'neutral',
                'Storkia reviewed your report and did not find a policy violation at this time. If you have new evidence, you are welcome to send a new report.',
            ],
            default => [
                'warning',
                'Your report is in the queue. Storkia will review it soon.',
            ],
        };
    }

    protected function logoUrl(SellerProfile $seller): ?string
    {
        return empty($seller->logo_path) ? null : asset('storage/' . ltrim($seller->logo_path, '/'));
    }

    protected function failure(string $message, int $status, string $code): JsonResponse
    {
        return response()->json([
            'success' => false,
            'code'    => $code,
            'message' => $message,
        ], $status);
    }

    protected function storeEvidencePhoto($file): string
    {
        $path = 'seller_report_evidence/report_' . uniqid('', true) . '.webp';

        try {
            $encoded = (new ImageManager(new Driver()))
                ->read($file->getRealPath())
                ->scaleDown(1200, 1200)
                ->toWebp(84);

            Storage::disk('public')->put($path, (string) $encoded);

            return $path;
        } catch (\Throwable $e) {
            return $file->store('seller_report_evidence', 'public');
        }
    }

    protected function deleteEvidencePhotos(array $paths): void
    {
        foreach ($paths as $path) {
            try {
                Storage::disk('public')->delete($path);
            } catch (\Throwable $e) {
                continue;
            }
        }
    }
}