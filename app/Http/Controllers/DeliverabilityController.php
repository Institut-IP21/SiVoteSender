<?php

namespace App\Http\Controllers;

use App\Models\EmailSendFailure;
use App\Models\GlobalEmailBlockList;
use App\Models\SentMessage;
use App\Models\Voter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Platform-wide deliverability views for the operator panel (web_app /admin).
 *
 * These expose GLOBAL tables (the email block list, the send-failure feed) and a
 * cross-owner status aggregate — data that has no per-owner scope. They sit behind
 * the same shared-token ApiAuth as the rest of the API and are only ever called by
 * web_app (the sole API client, which gates operator access on its side); the
 * `Owner` header is irrelevant here. Results are capped newest-first — a platform
 * at this scale never needs more on one screen, and the cap keeps the payload sane.
 */
class DeliverabilityController extends Controller
{
    private const LIMIT = 200;

    /**
     * The permanent bounce/complaint suppression list (global, no owner). Each row is
     * enriched with the org(s) the address belongs to, derived from existing
     * voter -> voterlist -> owner relations (no new tracking). web_app maps the
     * returned owner UUIDs to org names.
     */
    public function blockList(): JsonResponse
    {
        $blocks = GlobalEmailBlockList::query()
            ->orderByDesc('created_at')
            ->limit(self::LIMIT)
            ->get();

        /** @var array<int, string> $emails */
        $emails = $blocks->pluck('email')->all();
        $ownersByEmail = $this->ownersByEmail($emails);

        $rows = $blocks
            ->map(fn (GlobalEmailBlockList $b): array => [
                'email'      => $b->email,
                'status'     => $b->status,
                'status_msg' => $b->status_msg,
                'created_at' => $b->created_at?->toIso8601String(),
                'owners'     => $ownersByEmail[$b->email] ?? [],
            ])->values()->all();

        return $this->basicResponse(200, ['data' => $rows]);
    }

    /**
     * Map each of the given addresses to the distinct org owner UUID(s) it belongs to,
     * via voters.email -> voterlist_voter -> voterlists.owner. One query for the whole
     * set (never N per row). Soft-deleted voters and lists are excluded. Addresses with
     * no list membership simply get no entry (callers default to []).
     *
     * @param  array<int, string>  $emails
     * @return array<string, array<int, string>>
     */
    private function ownersByEmail(array $emails): array
    {
        if ($emails === []) {
            return [];
        }

        $pairs = DB::table('voters')
            ->join('voterlist_voter', 'voters.id', '=', 'voterlist_voter.voter_id')
            ->join('voterlists', 'voterlists.id', '=', 'voterlist_voter.voterlist_id')
            ->whereIn('voters.email', $emails)
            ->whereNull('voters.deleted_at')
            ->whereNull('voterlists.deleted_at')
            ->distinct()
            ->get(['voters.email as email', 'voterlists.owner as owner']);

        $map = [];
        foreach ($pairs as $pair) {
            $email = (string) $pair->email;
            $owner = (string) $pair->owner;
            $map[$email][] = $owner;
        }

        return $map;
    }

    /**
     * Un-suppress an address: drop its block-list rows AND clear the per-voter
     * `email_blocked` flag (the flag, not the list row, is what blocks a send — so
     * removing only the list entry would leave the voter undeliverable). Mirrors a
     * `delivered` SNS notification re-enabling a voter.
     */
    public function removeBlock(Request $request): JsonResponse
    {
        $invalid = $this->findErrors($request->all(), ['email' => 'required|email']);
        if ($invalid !== null) {
            return $invalid;
        }

        $email = (string) $request->input('email');

        $removed = GlobalEmailBlockList::query()->where('email', $email)->delete();
        Voter::query()->where('email', $email)->update(['email_blocked' => false]);

        return $this->basicResponse(200, ['removed' => $removed]);
    }

    /**
     * Retry-exhausted outbound emails (the feed that otherwise only hits a Slack digest).
     * Each row carries the originating org(s), derived from the failure's recipient
     * address and — when known — its voter's list memberships (no new tracking).
     */
    public function sendFailures(): JsonResponse
    {
        $failures = EmailSendFailure::query()
            ->orderByDesc('created_at')
            ->limit(self::LIMIT)
            ->get();

        /** @var array<int, string> $emails */
        $emails = $failures->pluck('recipient')->filter()->unique()->values()->all();
        /** @var array<int, int> $voterIds */
        $voterIds = $failures->pluck('voter_id')->filter()->unique()->values()->all();

        $ownersByEmail   = $this->ownersByEmail($emails);
        $ownersByVoterId = $this->ownersByVoterId($voterIds);

        $rows = $failures
            ->map(function (EmailSendFailure $f) use ($ownersByEmail, $ownersByVoterId): array {
                $owners = $f->recipient !== null ? ($ownersByEmail[$f->recipient] ?? []) : [];
                if ($f->voter_id !== null && isset($ownersByVoterId[$f->voter_id])) {
                    $owners = array_merge($owners, $ownersByVoterId[$f->voter_id]);
                }

                return [
                    'id'         => $f->id,
                    'recipient'  => $f->recipient,
                    'mailable'   => $f->mailable,
                    'error'      => $f->error,
                    'attempts'   => $f->attempts,
                    'created_at' => $f->created_at?->toIso8601String(),
                    'owners'     => array_values(array_unique($owners)),
                ];
            })->values()->all();

        return $this->basicResponse(200, ['data' => $rows]);
    }

    /**
     * Map each voter id to the distinct org owner UUID(s) of its lists, via
     * voterlist_voter -> voterlists.owner. One query for the whole set. Soft-deleted
     * lists are excluded.
     *
     * @param  array<int, int>  $voterIds
     * @return array<int, array<int, string>>
     */
    private function ownersByVoterId(array $voterIds): array
    {
        if ($voterIds === []) {
            return [];
        }

        $pairs = DB::table('voterlist_voter')
            ->join('voterlists', 'voterlists.id', '=', 'voterlist_voter.voterlist_id')
            ->whereIn('voterlist_voter.voter_id', $voterIds)
            ->whereNull('voterlists.deleted_at')
            ->distinct()
            ->get(['voterlist_voter.voter_id as voter_id', 'voterlists.owner as owner']);

        $map = [];
        foreach ($pairs as $pair) {
            $voterId = (int) $pair->voter_id;
            $owner = (string) $pair->owner;
            $map[$voterId][] = $owner;
        }

        return $map;
    }

    /** Platform-wide SES health: message counts by status + the two global-table sizes. */
    public function stats(): JsonResponse
    {
        $stats = [];
        foreach ([
            SentMessage::STATUS_SENT,
            SentMessage::STATUS_DELIVERED,
            SentMessage::STATUS_BOUNCE_SOFT,
            SentMessage::STATUS_BOUNCE,
            SentMessage::STATUS_COMPLAINT,
            SentMessage::STATUS_BLOCKED,
        ] as $status) {
            $stats[$status] = SentMessage::query()->where('status', $status)->count();
        }
        $stats['total'] = SentMessage::query()->count();

        return $this->basicResponse(200, [
            'stats'               => $stats,
            'block_list_count'    => GlobalEmailBlockList::query()->count(),
            'send_failures_count' => EmailSendFailure::query()->count(),
        ]);
    }
}
