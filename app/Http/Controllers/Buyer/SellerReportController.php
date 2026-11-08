<?php

namespace App\Http\Controllers\Buyer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Buyer\StoreSellerReportRequest;
use App\Models\SellerOrder;
use App\Models\SellerProfile;
use App\Models\SellerReport;
use App\Services\AuditLoggerService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

/**
 * Buyer side of "Report Seller".
 *
 *   GET  /sellers/{seller}/report/context -> data the report modal needs (JSON)
 *   POST /sellers/{seller}/report         -> submit a report
 *   GET  /user/seller-reports             -> "My Reports" status page
 *
 * The report modal is shared by the storefront, product page and order page, so it loads
 * its own context over JSON instead of every one of those pages having to prepare it.
 * Rules: any logged-in buyer may report; at most one OPEN report per buyer per seller
 * (also enforced by the unique `open_lock` column); an optional related order must be
 * one of the buyer's own orders from that seller.
 */
class SellerReportController extends Controller
{
    private const DUPLICATE_MESSAGE = 'You already have an open report against this store. Please wait for Storkia to review it before sending another one.';

    public function index(Request $request): View
    {
        $user = $request->user();

        $status = $request->query('status', 'all');
        if (! in_array($status, ['all', 'open', 'closed'], true)) {
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

        $reports = $reportsQuery->latest()->paginate(10)->withQueryString();

        return view('buyer.option.seller-reports', compact('reports', 'status', 'counts'));
    }

    public function context(Request $request, SellerProfile $seller): JsonResponse
    {
        $user = $request->user();

        if ((int) $user->id === (int) $seller->user_id) {
            return response()->json([
                'can_report' => false,
                'message'    => 'You cannot report your own store.',
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

        return response()->json([
            'can_report'      => true,
            'seller_name'     => $seller->business_name,
            'has_open_report' => $openReport !== null,
            'open_report'     => $openReport ? [
                'number'       => $openReport->report_number,
                'status_label' => $openReport->status_label,
            ] : null,
            'my_reports_url'  => route('user.seller-reports'),
            'orders'          => $orders->map(fn (SellerOrder $order) => [
                'id'        => $order->id,
                'number'    => $order->seller_order_number,
                'status'    => Str::headline((string) $order->status),
                'placed_at' => $order->created_at?->format('M d, Y'),
            ])->values(),
        ]);
    }

    public function store(StoreSellerReportRequest $request, SellerProfile $seller): JsonResponse|RedirectResponse
    {
        $user = $request->user();

        if ((int) $user->id === (int) $seller->user_id) {
            return $this->fail($request, 'You cannot report your own store.', 403);
        }

        if (SellerReport::hasOpenReport((int) $user->id, (int) $seller->id)) {
            return $this->fail($request, self::DUPLICATE_MESSAGE, 409);
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
            // Two requests raced each other: the unique open_lock caught the second one.
            $this->deleteEvidencePhotos($storedPhotos);

            return $this->fail($request, self::DUPLICATE_MESSAGE, 409);
        } catch (\Throwable $e) {
            $this->deleteEvidencePhotos($storedPhotos);
            report($e);

            return $this->fail($request, 'We could not submit your report right now. Please try again in a moment.', 500);
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

        $message = "Your report ({$report->report_number}) has been submitted. Storkia will review it.";

        if ($request->expectsJson()) {
            return response()->json([
                'success'       => true,
                'message'       => $message,
                'report_number' => $report->report_number,
                'redirect'      => route('user.seller-reports'),
            ], 201);
        }

        return redirect()->route('user.seller-reports')->with('success', $message);
    }

    protected function fail(Request $request, string $message, int $status): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return response()->json(['success' => false, 'message' => $message], $status);
        }

        return back()->withErrors(['report' => $message])->withInput();
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

    /**
     * @param  array<int, string>  $paths
     */
    protected function deleteEvidencePhotos(array $paths): void
    {
        foreach ($paths as $path) {
            try {
                Storage::disk('public')->delete($path);
            } catch (\Throwable $e) {
                // Best effort only: an orphaned file must never break the response.
            }
        }
    }
}