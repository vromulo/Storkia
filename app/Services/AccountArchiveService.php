<?php

namespace App\Services;

use App\Models\EmailChangeRequest;
use App\Models\SellerOrder;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * "Delete account" without deleting anything.
 *
 * Archiving only stamps users.archived_at (plus the reason the person gave).
 * The row, its orders, reviews, addresses and wallet history all stay, so
 * seller order pages, settlements and reports keep working, and the account
 * can be brought back from Admin > Account Management > Archived Accounts.
 *
 * What an archived account loses is access:
 *  - every web session, remember-me cookie and API token ends the moment
 *    archived_at is set (AccountSecurityObserver),
 *  - sign-in is refused on the web (AuthService) and in the mobile API
 *    (Api\AuthController), and any session that slips through is signed out
 *    on its next request by EnsureAccountActive.
 */
class AccountArchiveService
{
    /** SellerOrder statuses that mean the order is finished for good. */
    protected const FINISHED_ORDER_STATUSES = [
        'completed',
        'cancelled',
        'rejected',
        'returned_to_seller',
    ];

    /**
     * Reasons the account cannot be archived right now, written as sentences
     * that are safe to show to the account owner. An empty array means the
     * account is free to go.
     *
     * @return list<string>
     */
    public function blockers(User $user): array
    {
        $blockers = [];

        $inProgress = SellerOrder::where('buyer_id', $user->id)
            ->whereNotIn('status', self::FINISHED_ORDER_STATUSES)
            ->count();

        if ($inProgress > 0) {
            $blockers[] = $inProgress === 1
                ? 'You have 1 order that is still in progress. Wait until it is completed, or cancel it first.'
                : "You have {$inProgress} orders that are still in progress. Wait until they are completed, or cancel them first.";
        }

        // The two checks below only look at finished orders: an unfinished
        // order that also has a dispute or a refund is already counted above.
        $openDispute = SellerOrder::where('buyer_id', $user->id)
            ->whereIn('status', self::FINISHED_ORDER_STATUSES)
            ->where('has_unresolved_dispute', true)
            ->exists();

        if ($openDispute) {
            $blockers[] = 'You have a dispute that Storkia Admin has not resolved yet.';
        }

        $pendingRefund = SellerOrder::where('buyer_id', $user->id)
            ->whereIn('status', self::FINISHED_ORDER_STATUSES)
            ->whereHas('extra', fn ($query) => $query->where('refund_status', 'pending_approval'))
            ->exists();

        if ($pendingRefund) {
            $blockers[] = 'You have a refund request that is still being reviewed.';
        }

        return $blockers;
    }

    /**
     * Archive the account of the person who asked to delete it.
     *
     * Signing the person out everywhere is not done here: it happens in
     * AccountSecurityObserver as soon as archived_at changes, so it also holds
     * for any other code path that archives a user.
     */
    public function archive(User $user, string $reason, ?string $note = null): User
    {
        if ($user->isArchived()) {
            return $user;
        }

        $reason = array_key_exists($reason, User::ARCHIVE_REASONS) ? $reason : 'other';
        $note = trim((string) $note);
        $note = $note === '' ? null : mb_substr($note, 0, 500);

        DB::transaction(function () use ($user, $reason, $note) {
            // forceFill on purpose: the archive columns are not mass assignable.
            $user->forceFill([
                'archived_at'    => now(),
                'archive_reason' => $reason,
                'archive_note'   => $note,
            ])->save();

            // A half-finished email change must not outlive the account.
            EmailChangeRequest::where('user_id', $user->id)->delete();
        });

        AuditLoggerService::log(
            action: 'account_archived',
            targetType: 'User',
            targetId: (int) $user->id,
            targetReference: $this->reference($user),
            oldValues: ['archived_at' => null],
            newValues: [
                'archived_at'    => $user->archived_at?->toDateTimeString(),
                'archive_reason' => $reason,
                'archive_note'   => $note,
            ],
            reason: $note ?? User::ARCHIVE_REASONS[$reason],
            // Named explicitly: the person is the actor, even if an admin
            // happens to be signed in on the same browser.
            actorType: $user->role ?: 'User',
            actorId: (int) $user->id,
            actorName: $user->full_name ?: $user->email,
        );

        return $user;
    }

    /**
     * Bring an archived account back. The password and every record on the
     * account are untouched, so the owner can simply sign in again.
     */
    public function restore(User $user, ?string $remarks = null): User
    {
        if (! $user->isArchived()) {
            return $user;
        }

        $before = [
            'archived_at'    => $user->archived_at?->toDateTimeString(),
            'archive_reason' => $user->archive_reason,
        ];

        $user->forceFill([
            'archived_at'    => null,
            'archive_reason' => null,
            'archive_note'   => null,
        ])->save();

        $remarks = trim((string) $remarks);

        // The actor is detected from the signed-in admin guard.
        AuditLoggerService::log(
            action: 'account_restored',
            targetType: 'User',
            targetId: (int) $user->id,
            targetReference: $this->reference($user),
            oldValues: $before,
            newValues: ['archived_at' => null],
            reason: $remarks !== '' ? $remarks : 'Archived account restored by Storkia Admin.',
        );

        return $user;
    }

    protected function reference(User $user): string
    {
        $name = trim((string) $user->full_name);

        return $name !== '' ? "{$name} ({$user->email})" : (string) $user->email;
    }
}