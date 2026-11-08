<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BuyerAccountSanction;
use App\Models\SellerReport;
use App\Notifications\SellerReportNotification;
use App\Services\AuditLoggerService;
use App\Services\BuyerAbandonmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Admin side of "Report Seller".
 *
 *   GET  /admin/seller-reports                       -> queue (pending / under review / resolved / dismissed)
 *   GET  /admin/seller-reports/{report}              -> one report: evidence, seller history, actions
 *   POST /admin/seller-reports/{report}/start-review -> pending -> under review
 *   POST /admin/seller-reports/{report}/dismiss      -> no violation found
 *   POST /admin/seller-reports/{report}/resolve      -> take action: warn, 7-day suspension or permanent ban
 *
 * Suspensions and bans use the same BuyerAccountSanction rows as Admin\AccountController::toggleStatus,
 * so the existing EnsureAccountActive middleware signs the seller out and blocks them straight away.
 * "Warn" changes nothing on the account: it is recorded on the report, sent to the seller and audit-logged.
 *
 * The buyer who reported is only ever told THAT the report was reviewed or acted on, never which action,
 * and the seller is never told who reported them.
 */
class SellerReportController extends Controller
{
    protected const TABS = [
        SellerReport::STATUS_PENDING,
        SellerReport::STATUS_UNDER_REVIEW,
        SellerReport::STATUS_RESOLVED,
        SellerReport::STATUS_DISMISSED,
        'all',
    ];

    public function __construct(protected BuyerAbandonmentService $abandonmentService)
    {
    }

    // ------------------------------------------------------------------ pages

    public function index(Request $request): View
    {
        $tab = (string) $request->query('tab', SellerReport::STATUS_PENDING);
        if (! in_array($tab, self::TABS, true)) {
            $tab = SellerReport::STATUS_PENDING;
        }

        $search = trim((string) $request->query('search', ''));

        $statusTotals = SellerReport::selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $counts = [
            'all'                            => (int) $statusTotals->sum(),
            SellerReport::STATUS_PENDING      => (int) ($statusTotals[SellerReport::STATUS_PENDING] ?? 0),
            SellerReport::STATUS_UNDER_REVIEW => (int) ($statusTotals[SellerReport::STATUS_UNDER_REVIEW] ?? 0),
            SellerReport::STATUS_RESOLVED     => (int) ($statusTotals[SellerReport::STATUS_RESOLVED] ?? 0),
            SellerReport::STATUS_DISMISSED    => (int) ($statusTotals[SellerReport::STATUS_DISMISSED] ?? 0),
        ];

        $query = SellerReport::with(['reporter', 'sellerProfile', 'sellerOrder']);

        if ($tab !== 'all') {
            $query->where('status', $tab);
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('report_number', 'like', "%{$search}%")
                    ->orWhereHas('sellerProfile', fn ($sq) => $sq->where('business_name', 'like', "%{$search}%"))
                    ->orWhereHas('reporter', function ($rq) use ($search) {
                        $rq->where('email', 'like', "%{$search}%")
                            ->orWhere('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%");
                    });
            });
        }

        // Open reports are a work queue (oldest first); closed ones read like a history (newest first).
        if (in_array($tab, [SellerReport::STATUS_PENDING, SellerReport::STATUS_UNDER_REVIEW], true)) {
            $query->orderBy('created_at', 'asc')->orderBy('id', 'asc');
        } else {
            $query->orderByDesc('created_at')->orderByDesc('id');
        }

        $reports = $query->paginate(15)->withQueryString();

        // How many reports each listed seller has received in total (all statuses).
        $sellerReportTotals = SellerReport::whereIn('seller_profile_id', $reports->pluck('seller_profile_id')->unique())
            ->selectRaw('seller_profile_id, COUNT(*) as total')
            ->groupBy('seller_profile_id')
            ->pluck('total', 'seller_profile_id');

        return view('admin.seller-reports.index', compact(
            'tab',
            'search',
            'counts',
            'reports',
            'sellerReportTotals'
        ));
    }

    public function show(SellerReport $report): View
    {
        $report->load(['reporter', 'sellerProfile.user', 'sellerOrder']);

        $sellerProfile = $report->sellerProfile;
        $sellerUser = $sellerProfile?->user;

        $sanction = $sellerUser
            ? BuyerAccountSanction::where('buyer_id', $sellerUser->id)->first()
            : null;

        $sellerIsBlocked = $sanction ? $sanction->isBlockedFromMarketplace() : false;

        $sellerReportsQuery = SellerReport::where('seller_profile_id', $report->seller_profile_id);

        $sellerReportTotal = (clone $sellerReportsQuery)->count();
        $sellerUniqueReporters = (clone $sellerReportsQuery)->distinct()->count('reporter_id');

        $otherReports = (clone $sellerReportsQuery)
            ->with('reporter')
            ->where('id', '!=', $report->id)
            ->latest()
            ->limit(10)
            ->get();

        // Helps spot someone who files lots of reports that keep getting dismissed.
        $reporterTotal = SellerReport::where('reporter_id', $report->reporter_id)->count();
        $reporterDismissed = SellerReport::where('reporter_id', $report->reporter_id)
            ->where('status', SellerReport::STATUS_DISMISSED)
            ->count();

        return view('admin.seller-reports.show', [
            'report'                => $report,
            'sellerProfile'         => $sellerProfile,
            'sellerUser'            => $sellerUser,
            'sanction'              => $sanction,
            'sellerIsBlocked'       => $sellerIsBlocked,
            'sellerReportTotal'     => $sellerReportTotal,
            'sellerUniqueReporters' => $sellerUniqueReporters,
            'otherReports'          => $otherReports,
            'reporterTotal'         => $reporterTotal,
            'reporterDismissed'     => $reporterDismissed,
            'canStartReview'        => $report->status === SellerReport::STATUS_PENDING,
            'canDecide'             => $report->isOpen(),
        ]);
    }

    // ------------------------------------------------------------------ actions

    public function startReview(SellerReport $report): RedirectResponse
    {
        $adminId = (int) Auth::guard('admin')->id();

        $started = DB::transaction(function () use ($report, $adminId) {
            $locked = SellerReport::whereKey($report->id)->lockForUpdate()->first();

            if (! $locked || $locked->status !== SellerReport::STATUS_PENDING) {
                return false;
            }

            $locked->update([
                'status'               => SellerReport::STATUS_UNDER_REVIEW,
                'reviewed_by_admin_id' => $adminId,
                'reviewed_at'          => now(),
            ]);

            return true;
        });

        if (! $started) {
            return back()->withErrors(['report' => 'This report is no longer pending, so it could not be moved to review.']);
        }

        $report->refresh()->load(['reporter', 'sellerProfile']);

        $this->notifyReporter(
            $report,
            'Your Seller Report Is Under Review',
            "Storkia is now reviewing your report {$report->report_number} about {$this->storeName($report)}.",
            'info'
        );

        AuditLoggerService::log(
            action: 'seller_report_review_started',
            targetType: 'SellerReport',
            targetId: (int) $report->id,
            targetReference: $report->report_number,
            newValues: ['status' => SellerReport::STATUS_UNDER_REVIEW]
        );

        return redirect()
            ->route('admin.seller-reports.show', $report->id)
            ->with('success', "{$report->report_number} is now under review.");
    }

    public function dismiss(Request $request, SellerReport $report): RedirectResponse
    {
        $validated = $request->validate([
            'admin_note' => ['required', 'string', 'min:5', 'max:1000'],
        ], [
            'admin_note.required' => 'Please add an internal note explaining why the report is dismissed.',
            'admin_note.min'      => 'The note must be at least 5 characters.',
            'admin_note.max'      => 'The note is too long (maximum 1000 characters).',
        ]);

        $adminId = (int) Auth::guard('admin')->id();
        $note = trim($validated['admin_note']);

        $dismissed = DB::transaction(function () use ($report, $adminId, $note) {
            $locked = SellerReport::whereKey($report->id)->lockForUpdate()->first();

            if (! $locked || ! $locked->isOpen()) {
                return false;
            }

            $locked->update([
                'status'               => SellerReport::STATUS_DISMISSED,
                'admin_action'         => null,
                'admin_note'           => $note,
                'reviewed_by_admin_id' => $locked->reviewed_by_admin_id ?? $adminId,
                'reviewed_at'          => $locked->reviewed_at ?? now(),
                'resolved_by_admin_id' => $adminId,
                'resolved_at'          => now(),
            ]);

            return true;
        });

        if (! $dismissed) {
            return back()->withErrors(['report' => 'This report has already been closed.']);
        }

        $report->refresh()->load(['reporter', 'sellerProfile']);

        $this->notifyReporter(
            $report,
            'Your Seller Report Was Reviewed',
            "Storkia reviewed report {$report->report_number} about {$this->storeName($report)} and did not find a policy violation at this time. If you have new evidence, you are welcome to send a new report.",
            'info'
        );

        AuditLoggerService::log(
            action: 'seller_report_dismissed',
            targetType: 'SellerReport',
            targetId: (int) $report->id,
            targetReference: $report->report_number,
            newValues: ['status' => SellerReport::STATUS_DISMISSED],
            reason: $note
        );

        return redirect()
            ->route('admin.seller-reports.show', $report->id)
            ->with('success', "{$report->report_number} was dismissed.");
    }

    public function resolve(Request $request, SellerReport $report): RedirectResponse
    {
        $validated = $request->validate([
            'action'         => ['required', Rule::in(SellerReport::ACTIONS)],
            'seller_message' => ['required', 'string', 'min:10', 'max:500'],
            'admin_note'     => ['required', 'string', 'min:5', 'max:1000'],
        ], [
            'action.required'         => 'Please choose what action to take against the seller.',
            'action.in'               => 'Please choose one of the listed actions.',
            'seller_message.required' => 'Please write the message the seller will receive.',
            'seller_message.min'      => 'The message to the seller must be at least 10 characters.',
            'seller_message.max'      => 'The message to the seller is too long (maximum 500 characters).',
            'admin_note.required'     => 'Please add an internal note for the audit trail.',
            'admin_note.min'          => 'The internal note must be at least 5 characters.',
            'admin_note.max'          => 'The internal note is too long (maximum 1000 characters).',
        ]);

        $action = $validated['action'];
        $sellerMessage = trim($validated['seller_message']);
        $note = trim($validated['admin_note']);
        $adminId = (int) Auth::guard('admin')->id();

        $report->loadMissing(['reporter', 'sellerProfile.user']);
        $sellerUser = $report->sellerProfile?->user;

        if (! $sellerUser) {
            return back()
                ->withErrors(['report' => 'The reported seller account no longer exists, so no action can be taken. Dismiss this report instead.'])
                ->withInput();
        }

        $sanctionReason = "Seller report {$report->report_number} ({$report->category_label}): {$sellerMessage}";

        $outcome = DB::transaction(function () use ($report, $action, $note, $adminId, $sellerUser, $sanctionReason) {
            $locked = SellerReport::whereKey($report->id)->lockForUpdate()->first();

            if (! $locked || ! $locked->isOpen()) {
                return ['error' => 'This report has already been closed by another admin.'];
            }

            $sanction = null;

            if (in_array($action, [SellerReport::ACTION_SUSPEND_TEMP, SellerReport::ACTION_SUSPEND_PERM], true)) {
                $sanction = $this->abandonmentService->getOrCreateSanction((int) $sellerUser->id);

                if ($sanction->isBlockedFromMarketplace()) {
                    return ['error' => 'This seller is already suspended or banned. Choose "Warn seller" or dismiss this report.'];
                }

                $this->applySanction($sanction, $action, $sanctionReason);
            }

            $locked->update([
                'status'               => SellerReport::STATUS_RESOLVED,
                'admin_action'         => $action,
                'admin_note'           => $note,
                'reviewed_by_admin_id' => $locked->reviewed_by_admin_id ?? $adminId,
                'reviewed_at'          => $locked->reviewed_at ?? now(),
                'resolved_by_admin_id' => $adminId,
                'resolved_at'          => now(),
            ]);

            return ['report' => $locked, 'sanction' => $sanction];
        });

        if (isset($outcome['error'])) {
            return back()->withErrors(['report' => $outcome['error']])->withInput();
        }

        $report = $outcome['report']->load(['reporter', 'sellerProfile']);
        $sanction = $outcome['sanction'];
        $store = $this->storeName($report);

        // The seller hears what was decided and why, but never who reported them.
        $this->notifySeller($report, $sellerUser, $action, $sellerMessage);

        // The reporter hears THAT action was taken, not which action.
        $this->notifyReporter(
            $report,
            'Action Taken on Your Seller Report',
            "Storkia reviewed your report {$report->report_number} and took action on {$store}. Thank you for helping keep Storkia safe.",
            'success'
        );

        AuditLoggerService::log(
            action: 'seller_report_resolved',
            targetType: 'SellerReport',
            targetId: (int) $report->id,
            targetReference: $report->report_number,
            newValues: [
                'status'            => SellerReport::STATUS_RESOLVED,
                'admin_action'      => $action,
                'seller_profile_id' => (int) $report->seller_profile_id,
                'seller_message'    => $sellerMessage,
            ],
            reason: $note
        );

        if ($sanction) {
            // Same audit entry the Accounts page writes, so the seller's account history stays complete.
            AuditLoggerService::log(
                action: 'admin_updated_user_status',
                targetType: 'User',
                targetId: (int) $sellerUser->id,
                targetReference: $sellerUser->email,
                newValues: ['account_status' => $sanction->account_status, 'action' => $action],
                reason: $sanctionReason
            );
        }

        $message = match ($action) {
            SellerReport::ACTION_SUSPEND_PERM => "{$report->report_number} resolved. {$store} has been permanently banned.",
            SellerReport::ACTION_SUSPEND_TEMP => "{$report->report_number} resolved. {$store} has been suspended for " . BuyerAccountSanction::TEMP_BAN_DAYS . ' days.',
            default                           => "{$report->report_number} resolved. {$store} has been warned.",
        };

        return redirect()
            ->route('admin.seller-reports.show', $report->id)
            ->with('success', $message);
    }

    // ------------------------------------------------------------------ helpers

    /**
     * Same account changes as Admin\AccountController::toggleStatus() for "suspend_temp" / "suspend_perm".
     */
    protected function applySanction(BuyerAccountSanction $sanction, string $action, string $reason): void
    {
        if ($action === SellerReport::ACTION_SUSPEND_PERM) {
            $sanction->update([
                'ban_level'               => 2,
                'account_status'          => 'permanently_banned',
                'appeal_required'         => false,
                'permanently_banned_at'   => now(),
                'last_abandonment_reason' => $reason,
            ]);

            return;
        }

        $sanction->update([
            'ban_level'               => max(1, (int) $sanction->ban_level),
            'account_status'          => 'temp_banned',
            'temp_ban_started_at'     => now(),
            'temp_ban_ends_at'        => now()->addDays(BuyerAccountSanction::TEMP_BAN_DAYS),
            'appeal_required'         => true,
            'last_abandonment_reason' => $reason,
        ]);
    }

    protected function storeName(SellerReport $report): string
    {
        return $report->sellerProfile?->business_name ?: 'the store';
    }

    protected function notifyReporter(SellerReport $report, string $title, string $message, string $type): void
    {
        try {
            $report->reporter?->notify(new SellerReportNotification(
                title: $title,
                message: $message,
                type: $type,
                url: route('user.seller-reports', ['report' => $report->id]),
                reportId: (int) $report->id,
                reportNumber: $report->report_number
            ));
        } catch (\Throwable $e) {
            // A failed notification must never undo or block the admin's decision.
            report($e);
        }
    }

    protected function notifySeller(SellerReport $report, $sellerUser, string $action, string $sellerMessage): void
    {
        [$title, $type] = match ($action) {
            SellerReport::ACTION_SUSPEND_PERM => ['Your Store Has Been Permanently Banned', 'danger'],
            SellerReport::ACTION_SUSPEND_TEMP => ['Your Store Has Been Suspended for ' . BuyerAccountSanction::TEMP_BAN_DAYS . ' Days', 'danger'],
            default                           => ['Warning From Storkia About Your Store', 'warning'],
        };

        try {
            $sellerUser->notify(new SellerReportNotification(
                title: $title,
                message: "Storkia reviewed a report about your store ({$report->category_label}). {$sellerMessage} Reference: {$report->report_number}.",
                type: $type,
                url: route('seller.seller-dashboard'),
                reportId: (int) $report->id,
                reportNumber: $report->report_number
            ));
        } catch (\Throwable $e) {
            report($e);
        }
    }
}