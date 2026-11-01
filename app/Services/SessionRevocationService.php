<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;
use Throwable;

/**
 * Ends an account's logins on every device.
 *
 * Three things keep a person signed in, and all three are handled here:
 *  1. The server-side web session (database "sessions" table).
 *  2. The remember-me cookie (its token is rotated, so old cookies stop working).
 *  3. Sanctum API tokens (mobile apps).
 *
 * Only plain Users (buyer / seller / logistics) have their database sessions
 * deleted: Laravel records user_id in the sessions table for the default web
 * guard only. Rider, courier and admin web sessions cannot be found there, so
 * they are cut off on their next request by EnsureAccountActive (rider and
 * courier) or simply by the rotated remember token.
 *
 * Nothing here may break the action that triggered it (a ban, a password
 * change), so every step is wrapped and logged on failure.
 */
class SessionRevocationService
{
    /**
     * Sign a User out everywhere.
     *
     * @param  bool  $includeTokens  Also delete Sanctum API tokens.
     * @param  bool  $keepCurrent    Keep the session / API token making the
     *                               current request (used for password changes
     *                               so the person is not logged out of the
     *                               device they are using).
     */
    public function revokeUser(User $user, bool $includeTokens = true, bool $keepCurrent = false): void
    {
        $this->safely(fn () => $this->deleteWebSessions($user, $keepCurrent), 'web sessions', $user);
        $this->safely(fn () => $this->rotateRememberToken($user), 'remember token', $user);

        if ($includeTokens) {
            $this->safely(fn () => $this->deleteApiTokens($user, $keepCurrent), 'api tokens', $user);
        }
    }

    /**
     * Cut off a Rider, Courier or Admin: rotate the remember-me token and, for
     * models that support them, delete API tokens.
     */
    public function revokeActor(Model $actor, bool $keepCurrent = false): void
    {
        $this->safely(fn () => $this->rotateRememberToken($actor), 'remember token', $actor);

        if (method_exists($actor, 'tokens')) {
            $this->safely(fn () => $this->deleteApiTokens($actor, $keepCurrent), 'api tokens', $actor);
        }
    }

    protected function deleteWebSessions(User $user, bool $keepCurrent): void
    {
        if (config('session.driver') !== 'database') {
            return;
        }

        $query = DB::connection(config('session.connection'))
            ->table((string) config('session.table', 'sessions'))
            ->where('user_id', $user->getKey());

        if ($keepCurrent && ($currentSessionId = $this->currentSessionIdFor($user)) !== null) {
            $query->where('id', '!=', $currentSessionId);
        }

        $query->delete();
    }

    /**
     * The id of the session making this request, but only when that session
     * belongs to the given user (an admin changing a buyer's data must not
     * "keep" the admin's own session id as if it were the buyer's).
     */
    protected function currentSessionIdFor(User $user): ?string
    {
        $request = request();

        if (! $request->hasSession()) {
            return null;
        }

        return (string) Auth::guard('web')->id() === (string) $user->getKey()
            ? $request->session()->getId()
            : null;
    }

    protected function deleteApiTokens(Model $actor, bool $keepCurrent): void
    {
        $keepTokenId = null;

        if ($keepCurrent) {
            $current = $actor->currentAccessToken();

            if ($current instanceof PersonalAccessToken) {
                $keepTokenId = $current->getKey();
            }
        }

        $actor->tokens()
            ->when($keepTokenId, fn ($query) => $query->where('id', '!=', $keepTokenId))
            ->delete();
    }

    /**
     * Written as a direct query on purpose: it must not touch the model's
     * dirty / changed state, because this runs inside model events.
     */
    protected function rotateRememberToken(Model $model): void
    {
        $table = $model->getTable();

        if (! Schema::hasColumn($table, 'remember_token')) {
            return;
        }

        DB::table($table)
            ->where($model->getKeyName(), $model->getKey())
            ->update(['remember_token' => Str::random(60)]);
    }

    protected function safely(callable $step, string $label, Model $model): void
    {
        try {
            $step();
        } catch (Throwable $e) {
            Log::warning("Session revocation: could not clear {$label}.", [
                'model'   => $model::class,
                'id'      => $model->getKey(),
                'message' => $e->getMessage(),
            ]);
        }
    }
}