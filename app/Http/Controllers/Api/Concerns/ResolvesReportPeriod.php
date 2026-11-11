<?php

namespace App\Http\Controllers\Api\Concerns;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

trait ResolvesReportPeriod
{
    protected function resolveReportPeriod(Request $request): array
    {
        $validated = $request->validate([
            'from_date' => ['nullable', 'date_format:Y-m-d'],
            'to_date'   => ['nullable', 'date_format:Y-m-d'],
        ], [
            'from_date.date_format' => 'The start date must be in YYYY-MM-DD format.',
            'to_date.date_format'   => 'The end date must be in YYYY-MM-DD format.',
        ]);

        $fromDate = $validated['from_date'] ?? now()->startOfMonth()->toDateString();
        $toDate = $validated['to_date'] ?? now()->toDateString();

        $from = Carbon::parse($fromDate)->startOfDay();
        $to = Carbon::parse($toDate)->endOfDay();

        if ($to->lt($from)) {
            throw ValidationException::withMessages([
                'to_date' => 'The end date must be on or after the start date.',
            ]);
        }

        return [$fromDate, $toDate, $from, $to];
    }
}