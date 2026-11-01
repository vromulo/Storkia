<?php

namespace App\Http\Controllers\Buyer;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AccountArchiveService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;

/**
 * Account Management > Delete Account.
 *
 * Nothing is removed from the database: the account is archived (see
 * AccountArchiveService) and shows up under Admin > Account Management >
 * Archived Accounts.
 */
class DeleteAccountController extends Controller
{
    /** Wrong passwords allowed before the form locks for LOCKOUT_SECONDS. */
    protected const MAX_PASSWORD_ATTEMPTS = 5;

    protected const LOCKOUT_SECONDS = 900;

    public function __construct(protected AccountArchiveService $archive)
    {
    }

    /**
     * Called when the Delete Account modal opens, so a buyer who cannot delete
     * yet (open orders, dispute, refund) sees why before filling anything in.
     */
    public function eligibility(Request $request): JsonResponse
    {
        $blockers = $this->archive->blockers($request->user());

        return response()->json([
            'success'    => true,
            'can_delete' => $blockers === [],
            'blockers'   => $blockers,
        ]);
    }

    public function destroy(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $validated = $request->validate([
            'reason'       => ['required', 'string', Rule::in(array_keys(User::ARCHIVE_REASONS))],
            'note'         => ['nullable', 'string', 'max:300'],
            'acknowledged' => ['accepted'],
            'password'     => ['required', 'string'],
        ], [
            'reason.required'       => 'Please tell us why you are leaving.',
            'reason.in'             => 'Please choose one of the listed reasons.',
            'note.max'              => 'Your note can be up to 300 characters.',
            'acknowledged.accepted' => 'Please confirm that you understand before continuing.',
            'password.required'     => 'Please enter your password to confirm.',
        ]);

        // Re-checked here, not only in eligibility(): an order may have been
        // placed since the modal was opened.
        $blockers = $this->archive->blockers($user);

        if ($blockers !== []) {
            return response()->json([
                'success'  => false,
                'message'  => 'Your account cannot be deleted yet.',
                'blockers' => $blockers,
            ], 422);
        }

        $limiterKey = 'delete-account:' . $user->id;

        if (RateLimiter::tooManyAttempts($limiterKey, self::MAX_PASSWORD_ATTEMPTS)) {
            $minutes = (int) ceil(RateLimiter::availableIn($limiterKey) / 60);

            return response()->json([
                'success' => false,
                'message' => "Too many incorrect passwords. Please try again in {$minutes} minute(s).",
            ], 429);
        }

        if (! Hash::check($validated['password'], $user->password)) {
            RateLimiter::hit($limiterKey, self::LOCKOUT_SECONDS);

            $remaining = RateLimiter::remaining($limiterKey, self::MAX_PASSWORD_ATTEMPTS);
            $lockoutMinutes = (int) (self::LOCKOUT_SECONDS / 60);

            $message = $remaining > 0
                ? "Incorrect password. {$remaining} attempt(s) remaining."
                : "Incorrect password. For your security, please wait {$lockoutMinutes} minutes before trying again.";

            return response()->json([
                'success' => false,
                'message' => $message,
                'errors'  => ['password' => [$message]],
            ], 422);
        }

        RateLimiter::clear($limiterKey);

        // Signs the person out on every device (AccountSecurityObserver).
        $this->archive->archive($user, $validated['reason'], $validated['note'] ?? null);

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        $request->session()->flash('info', 'Your account has been deleted.');

        return response()->json([
            'success'  => true,
            'message'  => 'Your account has been deleted.',
            'redirect' => route('home'),
        ]);
    }
}