<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BuyerAccountSanction;
use App\Models\BuyerBanAppeal;
use App\Models\Courier;
use App\Models\Rider;
use App\Models\User;
use App\Services\AccountArchiveService;
use App\Services\AuditLoggerService;
use App\Services\BuyerAbandonmentService;
use App\Services\OperationalSlaGuardService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AccountController extends Controller
{
    public function __construct(
        protected BuyerAbandonmentService $abandonmentService,
        protected AccountArchiveService $archiveService,
        protected OperationalSlaGuardService $slaGuardService
    ) {}

    public function index(Request $request)
    {
        $rawRole = (string) $request->query('role', $request->query('tab', 'Buyer'));
        $search = trim((string) $request->query('search', ''));

        $roleTab = match (strtolower($rawRole)) {
            'buyer', 'buyers'       => 'Buyer',
            'seller', 'sellers'     => 'Seller',
            'logistics'             => 'Logistics',
            'courier', 'couriers'   => 'Courier',
            'rider', 'riders'       => 'Rider',
            'appeal', 'appeals'     => 'Appeals',
            'archived', 'archive'   => 'Archived',
            default                 => 'Buyer',
        };
        $tab = strtolower($roleTab);

        $usersQuery = User::with(['buyerProfile', 'sellerProfile', 'logisticsProfile', 'identityVerification'])
            ->whereNull('archived_at');

        if (in_array($roleTab, ['Buyer', 'Seller', 'Logistics'], true)) {
            $usersQuery->where('role', $roleTab);
        }

        if ($search !== '' && in_array($roleTab, ['Buyer', 'Seller', 'Logistics'], true)) {
            $usersQuery->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('contact_no', 'like', "%{$search}%")
                  ->orWhereHas('sellerProfile', fn ($sq) => $sq->where('business_name', 'like', "%{$search}%"))
                  ->orWhereHas('logisticsProfile', fn ($lq) => $lq->where('business_name', 'like', "%{$search}%"));
            });
        }

        $users = $usersQuery->orderByDesc('created_at')->paginate(20)->withQueryString();
        $accounts = $users;

        $sanctionsByBuyerId = BuyerAccountSanction::whereIn('buyer_id', $users->pluck('id'))
            ->get()
            ->keyBy('buyer_id');
        $sanctionsMap = $sanctionsByBuyerId;

        $appealsQuery = BuyerBanAppeal::with(['buyer', 'sanction'])
            ->orderByRaw("CASE WHEN status = 'pending' THEN 0 ELSE 1 END")
            ->orderByDesc('created_at');

        if ($search !== '' && $roleTab === 'Appeals') {
            $appealsQuery->whereHas('buyer', function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }
        $appeals = $appealsQuery->get();

        $archivedQuery = User::with(['buyerProfile', 'sellerProfile', 'logisticsProfile'])
            ->whereNotNull('archived_at')
            ->orderByDesc('archived_at');

        if ($search !== '' && $roleTab === 'Archived') {
            $archivedQuery->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('contact_no', 'like', "%{$search}%");
            });
        }
        $archivedUsers = $archivedQuery->paginate(20, ['*'], 'arch_page')->withQueryString();

        $couriersQuery = Courier::with('logisticsProfile')->orderByDesc('created_at');
        if ($search !== '' && $roleTab === 'Courier') {
            $couriersQuery->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('plate_number', 'like', "%{$search}%")
                  ->orWhere('license_no', 'like', "%{$search}%");
            });
        }
        $couriers = $couriersQuery->paginate(20, ['*'], 'cour_page')->withQueryString();

        $ridersQuery = Rider::with(['logisticsProfile', 'activeBarangayCoverages'])->orderByDesc('created_at');
        if ($search !== '' && $roleTab === 'Rider') {
            $ridersQuery->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('plate_number', 'like', "%{$search}%")
                  ->orWhere('license_no', 'like', "%{$search}%");
            });
        }
        $riders = $ridersQuery->paginate(20, ['*'], 'rid_page')->withQueryString();

        $buyersTotal = User::where('role', 'Buyer')->whereNull('archived_at')->count();
        $sellersTotal = User::where('role', 'Seller')->whereNull('archived_at')->count();
        $logisticsTotal = User::where('role', 'Logistics')->whereNull('archived_at')->count();
        $couriersTotal = Courier::count();
        $ridersTotal = Rider::count();
        $appealsPending = BuyerBanAppeal::where('status', 'pending')->count();
        $archivedTotal = User::whereNotNull('archived_at')->count();

        $counts = [
            'Buyer'     => $buyersTotal,
            'Seller'    => $sellersTotal,
            'Logistics' => $logisticsTotal,
            'Courier'   => $couriersTotal,
            'Rider'     => $ridersTotal,
            'Appeals'   => $appealsPending,
            'Archived'  => $archivedTotal,
            'buyers'    => $buyersTotal,
            'sellers'   => $sellersTotal,
            'logistics' => $logisticsTotal,
            'couriers'  => $couriersTotal,
            'riders'    => $ridersTotal,
            'appeals'   => $appealsPending,
            'archived'  => $archivedTotal,
        ];

        return view()->first(
            ['admin.accounts.index', 'admin.accounts'],
            compact(
                'roleTab',
                'tab',
                'search',
                'users',
                'accounts',
                'sanctionsMap',
                'sanctionsByBuyerId',
                'appeals',
                'archivedUsers',
                'riders',
                'couriers',
                'counts'
            )
        );
    }

    public function toggleStatus(Request $request)
    {
        $validated = $request->validate([
            'entity_type' => ['required', 'in:user,courier,rider'],
            'entity_id'   => ['required', 'integer'],
            'action'      => ['required', 'in:reactivate,suspend_temp,suspend_perm'],
            'reason'      => ['nullable', 'string', 'max:500'],
        ]);

        $entityType = $validated['entity_type'];
        $entityId = (int) $validated['entity_id'];
        $action = $validated['action'];
        $reason = trim((string) ($validated['reason'] ?? 'Updated by Storkia Administrator.'));

        if ($entityType === 'user') {
            $user = User::findOrFail($entityId);
            $sanction = $this->abandonmentService->getOrCreateSanction((int) $user->id);

            if ($action === 'reactivate') {
                $sanction->update([
                    'account_status'        => 'active',
                    'appeal_required'       => false,
                    'temp_ban_ends_at'      => null,
                    'permanently_banned_at' => null,
                ]);
                $msg = "Account {$user->full_name} ({$user->email}) has been reactivated.";
            } elseif ($action === 'suspend_perm') {
                $sanction->update([
                    'ban_level'               => 2,
                    'account_status'          => 'permanently_banned',
                    'appeal_required'         => false,
                    'permanently_banned_at'   => now(),
                    'last_abandonment_reason' => $reason,
                ]);
                $msg = "Account {$user->full_name} ({$user->email}) has been permanently banned.";
            } else {
                $sanction->update([
                    'ban_level'               => max(1, (int) $sanction->ban_level),
                    'account_status'          => 'temp_banned',
                    'temp_ban_started_at'     => now(),
                    'temp_ban_ends_at'        => now()->addDays(7),
                    'appeal_required'         => true,
                    'last_abandonment_reason' => $reason,
                ]);
                $msg = "Account {$user->full_name} ({$user->email}) has been suspended for 7 days.";
            }

            AuditLoggerService::log(
                action: 'admin_updated_user_status',
                targetType: 'User',
                targetId: (int) $user->id,
                targetReference: $user->email,
                newValues: ['account_status' => $sanction->account_status, 'action' => $action],
                reason: $reason
            );

            return back()->with('success', $msg);
        }

        if ($entityType === 'courier') {
            $courier = Courier::findOrFail($entityId);
            $newStatus = $action === 'reactivate' ? 'active' : 'suspended';
            $courier->update([
                'status'    => $newStatus,
                'is_online' => $newStatus === 'active' ? $courier->is_online : false,
            ]);

            AuditLoggerService::log(
                action: 'admin_updated_courier_status',
                targetType: 'Courier',
                targetId: (int) $courier->id,
                targetReference: $courier->email,
                newValues: ['status' => $newStatus],
                reason: $reason
            );

            return back()->with('success', "Courier {$courier->full_name} status updated to {$newStatus}.");
        }

        $rider = Rider::findOrFail($entityId);
        if ($action === 'reactivate') {
            $rider->update([
                'status'      => 'approved',
                'approved_at' => now(),
                'rejected_at' => null,
            ]);
            $msg = "Rider {$rider->full_name} has been reactivated.";
        } else {
            $admin = Auth::guard('admin')->user();
            $this->slaGuardService->suspendRiderWithActiveJobHandling(
                rider: $rider,
                reason: strlen($reason) >= 10 ? $reason : 'Suspended by Storkia Administrator.',
                actorType: 'Admin',
                actorId: (int) ($admin?->id ?? 1),
                actorName: $admin?->name ?? 'Storkia Admin'
            );
            $msg = "Rider {$rider->full_name} has been suspended.";
        }

        return back()->with('success', $msg);
    }

    public function toggleBan(Request $request, int $id)
    {
        $user = User::findOrFail($id);
        $sanction = $this->abandonmentService->getOrCreateSanction((int) $user->id);

        if ($sanction->isBlockedFromMarketplace()) {
            $sanction->update([
                'account_status'        => 'active',
                'appeal_required'       => false,
                'temp_ban_ends_at'      => null,
                'permanently_banned_at' => null,
            ]);
            $msg = "Account {$user->full_name} ({$user->email}) has been unbanned and reactivated.";
        } else {
            $banType = $request->input('ban_type', 'temp');
            $reason = trim((string) $request->input('reason', 'Suspended by Storkia Administrator.'));

            if ($banType === 'permanent') {
                $sanction->update([
                    'ban_level'               => 2,
                    'account_status'          => 'permanently_banned',
                    'appeal_required'         => false,
                    'permanently_banned_at'   => now(),
                    'last_abandonment_reason' => $reason,
                ]);
            } else {
                $sanction->update([
                    'ban_level'               => max(1, (int) $sanction->ban_level),
                    'account_status'          => 'temp_banned',
                    'temp_ban_started_at'     => now(),
                    'temp_ban_ends_at'        => now()->addDays(7),
                    'appeal_required'         => true,
                    'last_abandonment_reason' => $reason,
                ]);
            }
            $msg = "Account {$user->full_name} ({$user->email}) has been suspended.";
        }

        AuditLoggerService::log(
            action: 'admin_toggled_account_ban',
            targetType: 'User',
            targetId: (int) $user->id,
            targetReference: $user->email,
            newValues: ['account_status' => $sanction->account_status]
        );

        return back()->with('success', $msg);
    }

    public function resolveAppeal(Request $request, BuyerBanAppeal $appeal)
    {
        $validated = $request->validate([
            'decision' => ['required', 'in:approve,reject,approved,rejected'],
            'remarks'  => ['nullable', 'string', 'max:500'],
        ]);

        $normalizedDecision = str_starts_with($validated['decision'], 'approve') ? 'approve' : 'reject';
        $remarks = trim((string) ($validated['remarks'] ?? ($normalizedDecision === 'approve' ? 'Appeal approved by Admin.' : 'Appeal declined by Admin.')));

        $this->abandonmentService->resolveBuyerAppeal(
            appeal: $appeal,
            adminId: (int) (Auth::guard('admin')->id() ?? 1),
            decision: $normalizedDecision,
            remarks: $remarks
        );

        return back()->with('success', 'Buyer suspension appeal resolved.');
    }

    public function restoreArchived(Request $request, int $id)
    {
        $user = User::findOrFail($id);
        $this->archiveService->restore($user, $request->input('remarks'));

        return back()->with('success', "Archived account {$user->full_name} ({$user->email}) has been restored!");
    }
}