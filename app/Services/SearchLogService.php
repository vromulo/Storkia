<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Records what buyers search for and answers "what is popular right now?".
 *
 * Privacy: only the normalised search text is stored, plus a one-way hashed key that
 * lets us count distinct shoppers. No user id, IP address or session id is saved.
 */
class SearchLogService
{
    /** How far back "Top Searches" looks. Matches the window the Trends page uses. */
    public const WINDOW_DAYS = 30;

    protected const RETENTION_DAYS = 90;
    protected const MIN_LENGTH = 2;
    protected const MAX_LENGTH = 80;

    /** The same shopper repeating the same search inside this window counts once. */
    protected const DEDUPE_MINUTES = 10;

    /** Top terms are cached briefly so the home page doesn't run the aggregate on every view. */
    protected const CACHE_SECONDS = 60;

    /**
     * Cleans a typed search into the form we store, or returns null when it should not be logged
     * (too short/long, no letters or digits, or it looks personal: email, phone/card number, URL).
     */
    public function normalize(?string $raw): ?string
    {
        if ($raw === null) {
            return null;
        }

        $term = preg_replace('/[\p{C}]+/u', ' ', $raw) ?? '';
        $term = preg_replace('/\s+/u', ' ', $term) ?? '';
        $term = mb_strtolower(trim($term));

        $length = mb_strlen($term);
        if ($length < self::MIN_LENGTH || $length > self::MAX_LENGTH) {
            return null;
        }

        if (! preg_match('/[\p{L}\p{N}]/u', $term)) {
            return null;
        }

        $digitsOnly = preg_replace('/[\s\-().+]/u', '', $term) ?? '';
        if (str_contains($term, '@')
            || preg_match('/\d{7,}/', $digitsOnly)
            || preg_match('~(https?://|www\.)~i', $term)) {
            return null;
        }

        return $term;
    }

    /**
     * Logs one search. Never throws: a logging problem must not break searching.
     */
    public function record(?string $rawTerm, int $resultsCount, Request $request): void
    {
        try {
            $term = $this->normalize($rawTerm);

            if ($term === null || $this->looksLikeBot($request) || ! Schema::hasTable('search_logs')) {
                return;
            }

            $searcherKey = $this->searcherKey($request);

            // Atomic "first time in this window" check: false means they already searched this recently.
            if (! Cache::add('search-log:' . $searcherKey . ':' . md5($term), 1, now()->addMinutes(self::DEDUPE_MINUTES))) {
                return;
            }

            DB::table('search_logs')->insert([
                'term'          => $term,
                'searcher_key'  => $searcherKey,
                'results_count' => max(0, $resultsCount),
                'created_at'    => now(),
            ]);

            $this->pruneOldLogs();
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /**
     * Most searched terms that actually return products, ranked by distinct shoppers
     * (so one person repeating a search can't push a term up), then by total searches.
     *
     * @return Collection<int, object{term: string, searchers: int, searches: int}>
     */
    public function topTerms(int $limit = 10, int $days = self::WINDOW_DAYS): Collection
    {
        if (! Schema::hasTable('search_logs')) {
            return collect();
        }

        $rows = Cache::remember(
            "search-log:top-terms:{$days}:{$limit}",
            now()->addSeconds(self::CACHE_SECONDS),
            function () use ($limit, $days) {
                return DB::table('search_logs')
                    ->where('created_at', '>=', now()->subDays($days))
                    ->where('results_count', '>', 0)
                    ->groupBy('term')
                    ->selectRaw('term, COUNT(DISTINCT searcher_key) as searchers, COUNT(*) as searches, MAX(created_at) as last_at')
                    ->orderByDesc('searchers')
                    ->orderByDesc('searches')
                    ->orderByDesc('last_at')
                    ->limit($limit)
                    ->get()
                    ->map(fn ($row) => [
                        'term'      => (string) $row->term,
                        'searchers' => (int) $row->searchers,
                        'searches'  => (int) $row->searches,
                    ])
                    ->all();
            }
        );

        return collect($rows)->map(fn ($row) => (object) $row);
    }

    /**
     * One real, buyable product for each of the top search terms (never the same product twice).
     * Powers the "Top Searches" tile on the home page.
     *
     * @return Collection<int, object{term: string, searchers: int, product: Product}>
     */
    public function topSearchedProducts(int $limit = 3, int $days = self::WINDOW_DAYS): Collection
    {
        $picked = collect();
        $usedIds = [];

        foreach ($this->topTerms(15, $days) as $row) {
            if ($picked->count() >= $limit) {
                break;
            }

            $product = $this->bestProductForTerm($row->term, $usedIds);
            if (! $product) {
                continue;
            }

            $usedIds[] = $product->id;
            $picked->push((object) [
                'term'      => $row->term,
                'searchers' => $row->searchers,
                'product'   => $product,
            ]);
        }

        return $picked;
    }

    /** Matches the way the search page matches: whole phrase, or any word longer than 2 letters. */
    protected function bestProductForTerm(string $term, array $excludeIds): ?Product
    {
        $clean = trim(str_replace(['\\', '%', '_'], ' ', $term));
        if ($clean === '') {
            return null;
        }

        $words = array_filter(explode(' ', $clean), fn ($w) => mb_strlen($w) > 2);

        return Product::query()
            ->where('stock_quantity', '>', 0)
            ->whereHas('approval', fn ($q) => $q->where('status', 'Approved'))
            ->whereNotIn('id', $excludeIds)
            ->where(function ($q) use ($clean, $words) {
                $q->where('name', 'LIKE', "%{$clean}%")
                  ->orWhere('category', 'LIKE', "%{$clean}%");

                foreach ($words as $word) {
                    $q->orWhere('name', 'LIKE', "%{$word}%");
                }
            })
            ->latest('id')
            ->first();
    }

    /** One-way key for "the same shopper": logged-in user, else browser session, else IP + browser. */
    protected function searcherKey(Request $request): string
    {
        $user = $request->user();

        if ($user) {
            $seed = 'user:' . $user->getAuthIdentifier();
        } elseif ($request->hasSession()) {
            $seed = 'session:' . $request->session()->getId();
        } else {
            $seed = 'client:' . $request->ip() . '|' . $request->userAgent();
        }

        return substr(hash_hmac('sha256', $seed, (string) config('app.key')), 0, 40);
    }

    protected function looksLikeBot(Request $request): bool
    {
        $agent = trim((string) $request->userAgent());

        return $agent === ''
            || (bool) preg_match('/bot|crawl|spider|slurp|headless|facebookexternalhit|curl|wget|python-requests/i', $agent);
    }

    /** Keeps the table small: once a day, drop searches older than the retention period. */
    protected function pruneOldLogs(): void
    {
        if (Cache::add('search-log:pruned', 1, now()->addDay())) {
            DB::table('search_logs')
                ->where('created_at', '<', now()->subDays(self::RETENTION_DAYS))
                ->delete();
        }
    }
}
