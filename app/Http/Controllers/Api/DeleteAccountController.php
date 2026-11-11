<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AccountArchiveService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;

class DeleteAccountController extends Controller
{
    protected const MAX_PASSWORD_ATTEMPTS = 5;

    protected const LOCKOUT_SECONDS = 900;

    protected const NOTE_MAX_LENGTH = 300;

    public function __construct(protected AccountArchiveService $archive)
    {
    }

    public function eligibility(Request $request): JsonResponse
    {
        $blockers = $this->archive->blockers($request->user());

        return response()->json([
            'success'         => true,
            'can_delete'      => $blockers === [],
            'blockers'        => $blockers,
            'reasons'         => $this->reasons(),
            'note_max_length' => self::NOTE_MAX_LENGTH,
        ]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'reason'       => ['required', 'string', Rule::in(array_keys(User::ARCHIVE_REASONS))],
            'note'         => ['nullable', 'string', 'max:' . self::NOTE_MAX_LENGTH],
            'acknowledged' => ['accepted'],
            'password'     => ['required', 'string'],
        ], [
            'reason.required'       => 'Please tell us why you are leaving.',
            'reason.in'             => 'Please choose one of the listed reasons.',
            'note.max'              => 'Your note can be up to ' . self::NOTE_MAX_LENGTH . ' characters.',
            'acknowledged.accepted' => 'Please confirm that you understand before continuing.',
            'password.required'     => 'Please enter your password to confirm.',
        ]);

        $blockers = $this->archive->blockers($user);

        if ($blockers !== []) {
            return response()->json([
                'success'  => false,
                'message'  => 'Your account cannot be deleted yet.',
                'code'     => 'deletion_blocked',
                'blockers' => $blockers,
            ], 422);
        }

        $limiterKey = 'delete-account:' . $user->id;

        if (RateLimiter::tooManyAttempts($limiterKey, self::MAX_PASSWORD_ATTEMPTS)) {
            $minutes = (int) ceil(RateLimiter::availableIn($limiterKey) / 60);

            return response()->json([
                'success' => false,
                'message' => "Too many incorrect passwords. Please try again in {$minutes} minute(s).",
                'code'    => 'too_many_attempts',
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

        $this->archive->archive($user, $validated['reason'], $validated['note'] ?? null);

        $user->tokens()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Your account has been deleted.',
        ]);
    }

    protected function reasons(): array
    {
        $reasons = [];

        foreach (User::ARCHIVE_REASONS as $key => $label) {
            $reasons[] = ['key' => $key, 'label' => $label];
        }

        return $reasons;
    }
}