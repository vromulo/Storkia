<?php

namespace App\Observers;

use App\Models\Admin;
use App\Models\BuyerAccountSanction;
use App\Models\Courier;
use App\Models\Rider;
use App\Models\User;
use App\Services\SessionRevocationService;
use Illuminate\Database\Eloquent\Model;

/**
 * One observer registered on User, Admin, Rider, Courier and
 * BuyerAccountSanction (see AppServiceProvider).
 *
 * Doing this at model level means every code path is covered, no matter which
 * controller, Livewire component or service changes the data: the admin
 * accounts page, the SLA guard, the logistics hub screens, password change in
 * any portal or in the API.
 *
 *  - Password changed       -> all other sessions, remember-me cookies and API
 *                              tokens of that account end.
 *  - Account archived       -> the owner deleted the account (Account
 *                              Management > Delete Account): every web session,
 *                              remember-me cookie and API token ends at once.
 *  - Ban / suspension       -> web sessions and remember-me cookies end at
 *                              once. A banned buyer keeps only the API token
 *                              needed to read the sanction and appeal; the
 *                              "account.active" middleware blocks the rest.
 *  - Rider / courier status -> tokens and remember-me cookies end when the
 *    no longer allowed         status leaves config('security.allowed_statuses').
 */
class AccountSecurityObserver
{
    /** Sanction columns whose change can start or deepen a block. */
    protected const SANCTION_BLOCK_FIELDS = [
        'account_status',
        'appeal_required',
        'ban_level',
        'temp_ban_started_at',
        'temp_ban_ends_at',
        'permanently_banned_at',
    ];

    public function __construct(protected SessionRevocationService $revocation)
    {
    }

    public function updated(Model $model): void
    {
        match (true) {
            $model instanceof User    => $this->userUpdated($model),
            $model instanceof Rider   => $this->fieldStaffUpdated($model, 'rider'),
            $model instanceof Courier => $this->fieldStaffUpdated($model, 'courier'),
            $model instanceof Admin   => $this->adminUpdated($model),
            default                   => null,
        };
    }

    /**
     * "saved" fires for both create and update, and a sanction row is often
     * created already blocked.
     */
    public function saved(Model $model): void
    {
        if ($model instanceof BuyerAccountSanction) {
            $this->sanctionSaved($model);
        }
    }

    protected function userUpdated(User $user): void
    {
        if ($user->wasChanged('password')) {
            // Keep the device that made the change, end everything else.
            $this->revocation->revokeUser($user, includeTokens: true, keepCurrent: true);
        }

        if ($user->wasChanged('archived_at') && $user->archived_at !== null) {
            // Nothing is kept, not even the current device: an archived
            // account cannot sign back in until an admin restores it.
            $this->revocation->revokeUser($user, includeTokens: true);
        }
    }

    protected function sanctionSaved(BuyerAccountSanction $sanction): void
    {
        if (! $sanction->isBlockedFromMarketplace()) {
            return;
        }

        if (! $sanction->wasRecentlyCreated && ! $sanction->wasChanged(self::SANCTION_BLOCK_FIELDS)) {
            return;
        }

        $user = User::find($sanction->buyer_id);

        if ($user) {
            // API tokens stay so a banned buyer can still open the sanction
            // status and appeal screens in the mobile app.
            $this->revocation->revokeUser($user, includeTokens: false);
        }
    }

    protected function fieldStaffUpdated(Model $staff, string $type): void
    {
        $allowed = (array) config("security.allowed_statuses.{$type}", []);

        if ($staff->wasChanged('status') && ! in_array($staff->status, $allowed, true)) {
            $this->revocation->revokeActor($staff);

            return;
        }

        if ($staff->wasChanged('password')) {
            $this->revocation->revokeActor($staff, keepCurrent: true);
        }
    }

    protected function adminUpdated(Admin $admin): void
    {
        if ($admin->wasChanged('password')) {
            $this->revocation->revokeActor($admin);
        }
    }
}