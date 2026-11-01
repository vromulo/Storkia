<?php

namespace App\Http\Controllers\Api\Concerns;

use Illuminate\Database\Eloquent\Model;

/**
 * Shared token issuing for the buyer, rider and courier API logins.
 *
 * - The token carries one ability ("buyer", "rider" or "courier") that the
 *   api.actor middleware checks, so a token only works for its own portal.
 * - Only the newest few tokens per account are kept. Every login used to add a
 *   token that was never removed; now the oldest devices are signed out once
 *   the limit is reached.
 */
trait IssuesApiTokens
{
    protected function maxApiTokensPerAccount(): int
    {
        return 5;
    }

    protected function issueApiToken(Model $actor, string $ability, string $name): string
    {
        $keepIds = $actor->tokens()
            ->latest('id')
            ->limit(max(0, $this->maxApiTokensPerAccount() - 1))
            ->pluck('id');

        $actor->tokens()->whereNotIn('id', $keepIds)->delete();

        return $actor->createToken($name, [$ability])->plainTextToken;
    }
}