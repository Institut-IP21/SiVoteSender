<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use App\Http\Resources\VoterListBasic;
use App\Http\Resources\VoterListFull;
use App\Http\Resources\VoterBasic;
use App\Models\SentMessage;
use App\Models\VoterList;
use App\Models\Voter;
use App\Services\Ballot;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class VoterListApiController extends Controller
{

    public function show(VoterList $voterlist): VoterListFull
    {
        return new VoterListFull($voterlist);
    }

    /**
     * Pre-launch aggregate check used before opening a ballot on this list.
     */
    public function launchCheck(VoterList $voterlist): JsonResponse
    {
        return response()->json($voterlist->launchStats());
    }

    public function showBasic(VoterList $voterlist): VoterListBasic
    {
        return new VoterListBasic($voterlist);
    }

    public function list(Request $request): AnonymousResourceCollection|JsonResponse
    {
        $params = $request->all();
        $settings = [
            'page' =>
            'integer',
            'size' =>
            'integer',
            'sort_by' =>
            'string|in:id,owner,title,created_at|required_with:sort_direction',
            'sort_direction' =>
            'in:desc,asc|required_with:sort_by',
        ];

        if ($errors = $this->findErrors($params, $settings)) {
            return $errors;
        }

        $query = VoterList::with('voters', 'sentMessages');

        $query->where('owner', $this->getOwner());

        if (!empty($params['sort_by'])) {
            $query->orderBy(
                $params['sort_by'],
                $params['sort_direction']
            );
        }

        return VoterListBasic::collection(
            $query
                ->paginate($params['size'] ?? 5)
                ->appends($params)
        );
    }

    public function listVoters(Request $request, VoterList $voterlist): AnonymousResourceCollection|JsonResponse
    {
        $params = $request->all();
        $settings = [
            'page' =>
            'integer',
            'size' =>
            'integer',
            'sort_by' =>
            'string|in:id,email,title,phone|required_with:sort_direction',
            'sort_direction' =>
            'in:desc,asc|required_with:sort_by',
            'filter' =>
            'string|in:all,verified,unverified,bounced',
        ];

        if ($errors = $this->findErrors($params, $settings)) {
            return $errors;
        }

        $query = $voterlist->voters();

        switch ($params['filter'] ?? 'all') {
            case 'verified':
                $query->whereNotNull('voters.email_verified');
                break;
            case 'unverified':
                $query->whereNull('voters.email_verified');
                break;
            case 'bounced':
                $query->where('voters.email_blocked', true);
                break;
        }

        if (!empty($params['sort_by'])) {
            $query->orderBy(
                $params['sort_by'],
                $params['sort_direction']
            );
        }

        return VoterBasic::collection(
            $query
                ->paginate($params['size'] ?? 50)
                ->appends($params)
        );
    }

    public function delete(VoterList $voterlist): JsonResponse
    {
        $voterlist->delete();
        return $this->basicResponse(200);
    }

    public function create(Request $request): JsonResponse|VoterListFull
    {
        $params = $request->all();
        $settings = [
            'title' =>
            'required|string',
        ];

        if ($errors = $this->findErrors($params, $settings)) {
            return $errors;
        }

        $data = [
            'title' => $params['title'],
            'owner' => $this->getOwner(),
        ];

        $voterlist = VoterList::create($data);

        return new VoterListFull($voterlist);
    }

    public function update(Request $request, VoterList $voterlist): JsonResponse|VoterListFull
    {
        $params = $request->all();
        $settings = [
            'title' =>
            'required|string',
        ];

        if ($errors = $this->findErrors($params, $settings)) {
            return $errors;
        }

        $voterlist->title = $params['title'];
        $voterlist->save();

        return new VoterListFull($voterlist);
    }

    public function addVoters(Request $request, VoterList $voterlist): JsonResponse
    {
        $params = $request->all();

        // `voters` arrives as a JSON string; `voters.*` rules silently match nothing until it is decoded.
        if ($errors = $this->findErrors($params, ['voters' => 'required|json'])) {
            return $errors;
        }

        $decoded = json_decode((string) $params['voters'], true);

        $validator = Validator::make(['voters' => $decoded], [
            'voters'         => 'required|array|min:1',
            'voters.*.title' => ['required', 'string', 'max:255', 'not_regex:/[\r\n]/'],
            'voters.*.email' => ['nullable', 'email:rfc', 'max:255'],
            'voters.*.phone' => ['nullable', 'string', 'max:255', 'not_regex:/[\r\n]/'],
        ]);

        if ($validator->fails()) {
            return $this->basicResponse(422, [
                'error'        => 'Request invalid.',
                'field_errors' => $validator->errors(),
            ]);
        }

        /** @var array<int, array<string, mixed>> $voters */
        $voters = $validator->validated()['voters'];

        // A voter who appears twice on a list gets two voting codes and can vote twice,
        // so duplicates (by email, or by phone for email-less rows) are skipped. The
        // list row is locked so two concurrent uploads cannot both insert the same
        // address, and the whole batch is atomic.
        [$added, $skipped] = DB::transaction(function () use ($voterlist, $voters): array {
            VoterList::whereKey($voterlist->id)->lockForUpdate()->first();

            $seenEmails = [];
            $seenPhones = [];
            foreach ($voterlist->voters()->get(['voters.email', 'voters.phone']) as $existing) {
                $email = Voter::normalizeEmail($existing->email);
                if ($email !== null) {
                    $seenEmails[$email] = true;
                } elseif (($phone = Voter::normalizePhone($existing->phone)) !== null) {
                    $seenPhones[$phone] = true;
                }
            }

            $added = 0;
            $skipped = 0;
            foreach ($voters as $voterData) {
                $email = Voter::normalizeEmail($voterData['email'] ?? null);
                $phone = Voter::normalizePhone($voterData['phone'] ?? null);

                if ($email !== null) {
                    if (isset($seenEmails[$email])) {
                        $skipped++;
                        continue;
                    }
                    $seenEmails[$email] = true;
                } elseif ($phone !== null) {
                    if (isset($seenPhones[$phone])) {
                        $skipped++;
                        continue;
                    }
                    $seenPhones[$phone] = true;
                }

                $voter = Voter::create(
                    [
                        'title' => $voterData['title'],
                        'email' => $voterData['email'] ?? null,
                        'phone' => $voterData['phone'] ?? null,
                    ]
                );
                $voterlist->voters()->attach($voter);
                $added++;
            }

            return [$added, $skipped];
        });

        $voterlist->save();
        $voterlist->unsetRelation('voters');

        // `added` / `skipped_duplicates` are repeated inside `data` because web_app's
        // API wrapper unwraps the `data` envelope.
        $counts = ['added' => $added, 'skipped_duplicates' => $skipped];

        return response()->json(
            array_merge(
                ['data' => array_merge((new VoterListFull($voterlist))->resolve(), $counts)],
                $counts
            )
        );
    }

    public function removeVoters(Request $request, VoterList $voterlist): JsonResponse|VoterListFull
    {
        $params = $request->all();
        $settings = [
            'voters' =>
            'required|json', // Should be just array
        ];

        if ($errors = $this->findErrors($params, $settings)) {
            return $errors;
        }

        $voters = json_decode((string) $params['voters'], true);
        if (!is_array($voters) || $voters === [] || !array_is_list($voters)) {
            return $this->basicResponse(400, ['error' => 'Voters have to be an array of IDs!']);
        }
        foreach ($voters as $voterId) {
            if (!is_int($voterId) && !(is_string($voterId) && ctype_digit($voterId))) {
                return $this->basicResponse(400, ['error' => 'Voters have to be an array of IDs!']);
            }
        }

        // Only touch voters that actually belong to THIS list, so a caller who
        // owns one list cannot delete another owner's voters by passing foreign IDs.
        $ownIds = $voterlist->voters()->whereIn('voters.id', $voters)->pluck('voters.id')->all();
        $voterlist->voters()->detach($ownIds);
        Voter::destroy($ownIds);

        return new VoterListFull($voterlist);
    }

    /**
     * Run a send under a lock on the list row, in one transaction: the voter set cannot
     * change between validation and per-voter code assignment, a repeat of the same
     * list + batch is refused (409 already_sent), and a failure part-way rolls back every
     * SentMessage and queued job.
     *
     * @param \Closure(): mixed $send
     */
    private function lockedSend(VoterList $voterlist, int $codeCount, string $batch, \Closure $send): JsonResponse
    {
        return DB::transaction(function () use ($voterlist, $codeCount, $batch, $send): JsonResponse {
            VoterList::whereKey($voterlist->id)->lockForUpdate()->first();
            $voterlist->unsetRelation('voters');

            if (SentMessage::where('voterlist_id', $voterlist->id)->where('batch_uuid', $batch)->exists()) {
                return $this->basicResponse(409, [
                    'message' => 'This batch has already been sent for this voter list.',
                    'error'   => 'already_sent',
                ]);
            }

            if ($problem = $this->sendProblems($voterlist, $codeCount)) {
                return $problem;
            }

            $send();

            return $this->basicResponse(200);
        });
    }

    /**
     * Validate a list is sendable BEFORE anything is queued: every voter has an email,
     * none is blocked, and the code count equals the voter count.
     */
    private function sendProblems(VoterList $voterlist, int $codeCount): ?JsonResponse
    {
        $stats = $voterlist->launchStats();
        $mismatch = $codeCount !== $stats['total'];

        $errors = [];
        if ($stats['without_email'] > 0) {
            $errors[] = $stats['without_email'] . ' voter(s) have no email address.';
        }
        if ($stats['blocked'] > 0) {
            $errors[] = $stats['blocked'] . ' voter(s) have a blocked email address.';
        }
        if ($mismatch) {
            $errors[] = 'Number of codes (' . $codeCount . ') does not match number of voters (' . $stats['total'] . ').';
        }

        if ($errors === []) {
            return null;
        }

        Log::warning('Refused to send: voter list is not sendable', [
            'voterlist' => $voterlist->id,
            'problems'  => $stats,
            'codes'     => $codeCount,
        ]);

        return $this->basicResponse(422, [
            'message'  => 'The voter list cannot be sent: ' . implode(' ', $errors),
            'errors'   => ['voters' => $errors],
            'problems' => [
                'without_email'  => $stats['without_email'],
                'blocked'        => $stats['blocked'],
                'count_mismatch' => $mismatch,
            ],
        ]);
    }

    public function sendInvites(Ballot $service, Request $request, VoterList $voterlist): JsonResponse
    {
        $params = $request->all();
        $settings = [
            // UUID of the ballot
            'batch' =>
            'required|uuid',

            // JSON Array of codes, should match in number to number of voters
            'codes' =>
            'required|array',

            // Email template, can have placeholders for:
            // %%CODE%% - the code that the user got
            // %%LINK%% - voting link
            // Link is probably not needed as a btn is added automatically.
            'template' =>
            'required|string',

            'subject' =>
            'required|string',

            // URL of the ballot, MUST have %%CODE%% so it can be replaced
            'url' =>
            'required|string',

            // Locale the email body/subject were rendered in (organizer's
            // locale). Used so the auto-appended button label matches.
            'locale' =>
            'sometimes|nullable|string',

        ];

        if ($errors = $this->findErrors($params, $settings)) {
            return $errors;
        }


        $codes    = $params['codes'];
        $url      = $params['url'];
        $batch    = $params['batch'];
        $template = $params['template'];
        $subject  = $params['subject'];
        $locale   = $params['locale'] ?? null;

        try {
            $response = $this->lockedSend(
                $voterlist,
                count($codes),
                $batch,
                fn () => $service->sendInvites($voterlist, $codes, $url, $batch, $template, $subject, $locale)
            );
        } catch (\Exception $e) {
            Log::alert('Error while sending invites', ['error' => $e->getMessage()]);
            return $this->basicResponse(500, ['error' => $e->getMessage()]);
        }

        return $response;
    }

    public function sendSessionInvites(Ballot $service, Request $request, VoterList $voterlist): JsonResponse
    {
        $params = $request->all();
        $settings = [
            // UUID of the ballot
            'batch' =>
            'required|uuid',

            // JSON Array of codes, should match in number to number of voters
            'codes' =>
            'required|array',

            // Email template, can have placeholders for:
            // %%CODE%% - the code that the user got
            // Link is probably not needed as a btn is added automatically.
            'template' =>
            'required|string',

            'subject' =>
            'required|string',

            'locale' =>
            'sometimes|nullable|string',

        ];

        if ($errors = $this->findErrors($params, $settings)) {
            return $errors;
        }


        $codes    = $params['codes'];
        $batch    = $params['batch'];
        $template = $params['template'];
        $subject  = $params['subject'];
        $locale   = $params['locale'] ?? null;

        try {
            $response = $this->lockedSend(
                $voterlist,
                count($codes),
                $batch,
                fn () => $service->sendSessionInvites($voterlist, $codes, $batch, $template, $subject, $locale)
            );
        } catch (\Exception $e) {
            Log::alert('Error while sending session invites', ['error' => $e->getMessage()]);
            return $this->basicResponse(500, ['error' => $e->getMessage()]);
        }

        return $response;
    }

    public function sendResults(Ballot $service, Request $request, VoterList $voterlist): JsonResponse
    {

        $params = $request->all();
        $settings = [
            // UUID of the ballot
            'batch' =>
            'required|uuid',

            'template' =>
            'required|string',

            'subject' =>
            'required|string',

            'csv' =>
            'required|string',

            'resultLink' =>
            'required|string',

            'locale' =>
            'sometimes|nullable|string',
        ];

        if ($errors = $this->findErrors($params, $settings)) {
            return $errors;
        }

        $batch      = $params['batch'];
        $template   = $params['template'];
        $subject    = $params['subject'];
        $csv        = $params['csv'];
        $resultLink = $params['resultLink'];
        $locale     = $params['locale'] ?? null;

        try {
            DB::transaction(function () use ($service, $voterlist, $batch, $template, $subject, $csv, $resultLink, $locale): void {
                VoterList::whereKey($voterlist->id)->lockForUpdate()->first();
                $voterlist->unsetRelation('voters');
                $service->sendResults($voterlist, $batch, $template, $subject, $csv, $resultLink, $locale);
            });
        } catch (\Exception $e) {
            Log::alert('Error while sending results', ['error' => $e->getMessage()]);
            return $this->basicResponse(500, ['error' => $e->getMessage()]);
        }

        return $this->basicResponse(200);
    }

    public function startTest(Ballot $service, Request $request): JsonResponse
    {
        $params = $request->all();
        $settings = [
            // Array of emails to send the test email to
            'to' =>
            'required|json',
            'to.*' =>
            'email',

            // Email template, can have placeholders for:
            // %%CODE%% - the code that the user got
            // %%LINK%% - voting link
            // Link is probably not needed as a btn is added automatically.
            'template' =>
            'required|string',

            'subject' =>
            'required|string',

            // URL of the ballot, MUST have %%CODE%% so it can be replaced
            'url' =>
            'required|string',

            'locale' =>
            'sometimes|nullable|string',

        ];

        if ($errors = $this->findErrors($params, $settings)) {
            return $errors;
        }

        $to       = json_decode((string) $params['to']);
        $url      = $params['url'];
        $template = $params['template'];
        $subject  = $params['subject'];
        $locale   = $params['locale'] ?? null;

        try {
            $status = $service->sendInvitesTest($to, $url, $template, $subject, $locale);
        } catch (\Exception $e) {
            Log::alert('Error while sending test invites', ['error' => $e->getMessage()]);
            return $this->basicResponse(500, ['error' => $e->getMessage()]);
        }

        return $this->basicResponse(200);
    }
}
