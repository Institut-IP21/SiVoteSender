<?php

namespace App\Http\Controllers;

use App\Models\Voter;
use App\Models\VoterList;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Platform-wide aggregate counts for the operator-panel dashboard (web_app /admin).
 *
 * Behind the same shared-token ApiAuth as the rest of the API; web_app is the only
 * caller and gates operator access on its side. No per-owner scope — these are global.
 */
class AdminStatsController extends Controller
{
    private const TREND_MONTHS = 12;

    /** Cross-org totals + a zero-filled 12-month voter-signup trend (derived from created_at). */
    public function stats(): JsonResponse
    {
        return $this->basicResponse(200, [
            'stats' => [
                'voters_total'      => Voter::query()->count(),
                'voter_lists_total' => VoterList::query()->count(),
                'voters_by_month'   => $this->votersByMonth(),
            ],
        ]);
    }

    /**
     * Voters created per calendar month over the last self::TREND_MONTHS months,
     * ascending and zero-filled. Counts are bucketed in PHP from the raw created_at
     * timestamps (no SQL date functions, no model SQL-aliases) and exclude trashed rows.
     *
     * @return array<int, array{month: string, count: int}>
     */
    private function votersByMonth(): array
    {
        // Window start = first day of the month, (TREND_MONTHS - 1) months back.
        $start = Carbon::now()->startOfMonth()->subMonths(self::TREND_MONTHS - 1);

        // Seed every bucket with zero so empty months still appear.
        $buckets = [];
        $cursor = $start->copy();
        for ($i = 0; $i < self::TREND_MONTHS; $i++) {
            $buckets[$cursor->format('Y-m')] = 0;
            $cursor->addMonth();
        }

        // Query builder -> stdClass rows (avoids the model-property phpstan trap).
        $timestamps = DB::table('voters')
            ->whereNull('deleted_at')
            ->where('created_at', '>=', $start)
            ->pluck('created_at');

        foreach ($timestamps as $timestamp) {
            if ($timestamp === null) {
                continue;
            }
            $month = Carbon::parse((string) $timestamp)->format('Y-m');
            if (array_key_exists($month, $buckets)) {
                $buckets[$month]++;
            }
        }

        $out = [];
        foreach ($buckets as $month => $count) {
            $out[] = ['month' => $month, 'count' => $count];
        }

        return $out;
    }
}
