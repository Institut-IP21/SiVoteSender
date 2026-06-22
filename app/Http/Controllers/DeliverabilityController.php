<?php

namespace App\Http\Controllers;

use App\Models\EmailSendFailure;
use App\Models\GlobalEmailBlockList;
use App\Models\SentMessage;
use App\Models\Voter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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

    /** The permanent bounce/complaint suppression list (global, no owner). */
    public function blockList(): JsonResponse
    {
        $rows = GlobalEmailBlockList::query()
            ->orderByDesc('created_at')
            ->limit(self::LIMIT)
            ->get()
            ->map(fn (GlobalEmailBlockList $b): array => [
                'email'      => $b->email,
                'status'     => $b->status,
                'status_msg' => $b->status_msg,
                'created_at' => $b->created_at?->toIso8601String(),
            ])->values()->all();

        return $this->basicResponse(200, ['data' => $rows]);
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

    /** Retry-exhausted outbound emails (the feed that otherwise only hits a Slack digest). */
    public function sendFailures(): JsonResponse
    {
        $rows = EmailSendFailure::query()
            ->orderByDesc('created_at')
            ->limit(self::LIMIT)
            ->get()
            ->map(fn (EmailSendFailure $f): array => [
                'id'         => $f->id,
                'recipient'  => $f->recipient,
                'mailable'   => $f->mailable,
                'error'      => $f->error,
                'attempts'   => $f->attempts,
                'created_at' => $f->created_at?->toIso8601String(),
            ])->values()->all();

        return $this->basicResponse(200, ['data' => $rows]);
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
