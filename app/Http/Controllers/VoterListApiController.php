<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use App\Http\Resources\VoterListBasic;
use App\Http\Resources\VoterListFull;
use App\Http\Resources\VoterBasic;
use App\Models\VoterList;
use App\Models\Voter;
use App\Services\Ballot;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class VoterListApiController extends Controller
{

    public function show(VoterList $voterlist): VoterListFull
    {
        return new VoterListFull($voterlist);
    }

    public function showBasic(VoterList $voterlist): VoterListBasic
    {
        return new VoterListBasic($voterlist);
    }

    public function list(Request $request)
    {
        $params = $request->all();
        $settings = [
            'page' =>
            'integer',
            'size' =>
            'integer',
            'sort_by' =>
            'string|in:id,owner,title|required_with:sort_direction',
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

    public function listVoters(Request $request, VoterList $voterlist)
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
        ];

        if ($errors = $this->findErrors($params, $settings)) {
            return $errors;
        }

        $query = $voterlist->voters();

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

    public function addVoters(Request $request, VoterList $voterlist): JsonResponse|VoterListFull
    {
        $params = $request->all();
        $settings = [
            'voters' =>
            'required|json',
            'voters.*.title' =>
            'required|string',
            'voters.*.email' =>
            'sometimes|email',
            'voters.*.phone' =>
            'sometimes|string',
        ];

        if ($errors = $this->findErrors($params, $settings)) {
            return $errors;
        }

        $voters = json_decode((string) $params['voters']);
        foreach ($voters as $voterData) {
            $voter = Voter::create(
                [
                    'title' => $voterData->title,
                    'email' => $voterData->email ?? null,
                    'phone' => $voterData->phone ?? null,
                ]
            );
            $voterlist->voters()->attach($voter);
        }

        $voterlist->save();

        return new VoterListFull($voterlist);
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

    public function sendInvites(Ballot $service, Request $request, VoterList $voterlist)
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

        if ($voterlist->checkVoterListHasBlockedVoters()) {
            return $this->basicResponse(409, [
                'error' => 'VoterList contains blocked voters.'
            ]);
        }

        $codes    = $params['codes'];
        $url      = $params['url'];
        $batch    = $params['batch'];
        $template = $params['template'];
        $subject  = $params['subject'];
        $locale   = $params['locale'] ?? null;

        try {
            $status = $service->sendInvites($voterlist, $codes, $url, $batch, $template, $subject, $locale);
        } catch (\Exception $e) {
            Log::alert('Error while sending invites', ['error' => $e->getMessage()]);
            return $this->basicResponse(500, ['error' => $e->getMessage()]);
        }

        return $this->basicResponse(200);
    }

    public function sendSessionInvites(Ballot $service, Request $request, VoterList $voterlist)
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

        if ($voterlist->checkVoterListHasBlockedVoters()) {
            return $this->basicResponse(409, [
                'error' => 'VoterList contains blocked voters.'
            ]);
        }

        $codes    = $params['codes'];
        $batch    = $params['batch'];
        $template = $params['template'];
        $subject  = $params['subject'];
        $locale   = $params['locale'] ?? null;

        try {
            $status = $service->sendSessionInvites($voterlist, $codes, $batch, $template, $subject, $locale);
        } catch (\Exception $e) {
            Log::alert('Error while sending session invites', ['error' => $e->getMessage()]);
            return $this->basicResponse(500, ['error' => $e->getMessage()]);
        }

        return $this->basicResponse(200);
    }

    public function sendResults(Ballot $service, Request $request, VoterList $voterlist)
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
            $status = $service->sendResults($voterlist, $batch, $template, $subject, $csv, $resultLink, $locale);
        } catch (\Exception $e) {
            Log::alert('Error while sending results', ['error' => $e->getMessage()]);
            return $this->basicResponse(500, ['error' => $e->getMessage()]);
        }

        return $this->basicResponse(200);
    }

    public function startTest(Ballot $service, Request $request)
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
