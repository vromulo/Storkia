<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Builder;

/**
 * Typo tolerance for the buyer product search (BUY-SRCH-004).
 *
 * The normal search is a plain LIKE match, so "Ratan baskit" finds nothing.
 * When (and only when) a search returns zero results, this service looks at the
 * words that actually exist in the catalog (product names + platform categories)
 * and replaces every query word that is not a real word with the closest real
 * word, e.g. "ratan baskit" => "rattan basket".
 *
 * Matching uses Damerau-Levenshtein distance (insert / delete / replace /
 * swap-two-letters), so "bakset" => "basket" is also caught. The allowed number
 * of mistakes grows with the word length so short words are not "corrected"
 * into unrelated ones:
 *
 *   4-5 letters => 1 mistake, 6-9 letters => 2 mistakes, 10+ letters => 3 mistakes
 *
 * Words shorter than 4 letters and pure numbers are never touched.
 */
class SearchTypoCorrectionService
{
    public const MIN_TOKEN_LENGTH = 4;

    /**
     * Try to correct a query against the catalog behind $scope.
     *
     * @param  Builder  $scope  The same base product query the search uses, so the
     *                          vocabulary always matches what the buyer can find.
     * @return array{query: string, corrections: array<string, string>}|null
     *         null when nothing could be corrected.
     */
    public function correct(string $query, Builder $scope): ?array
    {
        $frequencies = [];

        foreach ((clone $scope)->select(['name', 'category'])->get() as $row) {
            foreach ($this->tokenize($row->name . ' ' . ($row->category ?? '')) as $word) {
                if (mb_strlen($word) < 3 || ctype_digit($word)) {
                    continue;
                }
                $frequencies[$word] = ($frequencies[$word] ?? 0) + 1;
            }
        }

        return $this->correctAgainstVocabulary($query, $frequencies);
    }

    /**
     * Pure function (no database): correct $query using a word => frequency map.
     * Public so it can be unit-tested directly.
     *
     * @param  array<string|int, int>  $frequencies
     * @return array{query: string, corrections: array<string, string>}|null
     */
    public function correctAgainstVocabulary(string $query, array $frequencies): ?array
    {
        $tokens = $this->tokenize($query);

        if ($tokens === [] || $frequencies === []) {
            return null;
        }

        $corrections = [];
        $rebuilt = [];

        foreach ($tokens as $token) {
            $replacement = $this->bestMatch($token, $frequencies);

            if ($replacement !== null && $replacement !== $token) {
                $corrections[$token] = $replacement;
                $rebuilt[] = $replacement;
            } else {
                $rebuilt[] = $token;
            }
        }

        if ($corrections === []) {
            return null;
        }

        return [
            'query'       => implode(' ', $rebuilt),
            'corrections' => $corrections,
        ];
    }

    /**
     * @param  array<string|int, int>  $frequencies
     */
    protected function bestMatch(string $token, array $frequencies): ?string
    {
        $length = mb_strlen($token);

        // Too short, or a number: leave it exactly as typed.
        if ($length < self::MIN_TOKEN_LENGTH || ctype_digit($token)) {
            return null;
        }

        // Already a real word (or part of one): the LIKE search handles it.
        foreach ($frequencies as $word => $count) {
            if (str_contains((string) $word, $token)) {
                return null;
            }
        }

        $maxDistance = $this->maxDistanceFor($length);
        $tokenChars = mb_str_split($token);
        $best = null;

        foreach ($frequencies as $word => $count) {
            $word = (string) $word;

            if (abs(mb_strlen($word) - $length) > $maxDistance) {
                continue;
            }

            $distance = $this->distance($tokenChars, mb_str_split($word), $maxDistance);

            if ($distance > $maxDistance) {
                continue;
            }

            // Lowest distance wins; then the more common word; then the one that
            // shares the longest start with what was typed; then alphabetical.
            $candidate = [$distance, -$count, -$this->commonPrefixLength($token, $word), $word];

            if ($best === null || $candidate < $best) {
                $best = $candidate;
            }
        }

        return $best === null ? null : $best[3];
    }

    protected function maxDistanceFor(int $length): int
    {
        if ($length <= 5) {
            return 1;
        }

        return $length <= 9 ? 2 : 3;
    }

    /**
     * Lowercase words made of letters/numbers only ("USB-C Fast" => usb, fast).
     *
     * @return list<string>
     */
    protected function tokenize(string $text): array
    {
        $parts = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($text), -1, PREG_SPLIT_NO_EMPTY);

        return $parts === false ? [] : $parts;
    }

    protected function commonPrefixLength(string $a, string $b): int
    {
        $max = min(mb_strlen($a), mb_strlen($b));
        $i = 0;

        while ($i < $max && mb_substr($a, $i, 1) === mb_substr($b, $i, 1)) {
            $i++;
        }

        return $i;
    }

    /**
     * Damerau-Levenshtein distance (optimal string alignment) with early exit:
     * returns $max + 1 as soon as the distance is known to exceed $max.
     *
     * @param  list<string>  $a
     * @param  list<string>  $b
     */
    protected function distance(array $a, array $b, int $max): int
    {
        $lenA = count($a);
        $lenB = count($b);

        if (abs($lenA - $lenB) > $max) {
            return $max + 1;
        }

        $twoRowsBack = [];
        $previous = range(0, $lenB);

        for ($i = 1; $i <= $lenA; $i++) {
            $current = [$i];
            $rowMin = $i;

            for ($j = 1; $j <= $lenB; $j++) {
                $cost = $a[$i - 1] === $b[$j - 1] ? 0 : 1;

                $value = min(
                    $previous[$j] + 1,
                    $current[$j - 1] + 1,
                    $previous[$j - 1] + $cost
                );

                if ($i > 1 && $j > 1 && $a[$i - 1] === $b[$j - 2] && $a[$i - 2] === $b[$j - 1]) {
                    $value = min($value, $twoRowsBack[$j - 2] + 1);
                }

                $current[$j] = $value;
                $rowMin = min($rowMin, $value);
            }

            if ($rowMin > $max) {
                return $max + 1;
            }

            $twoRowsBack = $previous;
            $previous = $current;
        }

        return $previous[$lenB];
    }
}