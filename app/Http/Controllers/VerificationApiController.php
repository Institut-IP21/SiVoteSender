<?php

namespace App\Http\Controllers;

use App\Http\Resources\VerificationBasic;
use App\Http\Resources\VerificationFull;
use App\Models\VoterList;
use App\Models\Verification;
use App\Models\Voter;
use App\Services\Verification as ServicesVerification;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class VerificationApiController extends Controller
{

    public function show(Verification $verification): VerificationFull
    {
        return new VerificationFull($verification);
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
            'string|in:id,sent_at,voterlist_id|required_with:sort_direction',
            'sort_direction' =>
            'in:desc,asc|required_with:sort_by',
            'filter_by_voterlist' =>
            'sometimes|integer',
        ];

        if ($errors = $this->findErrors($params, $settings)) {
            return $errors;
        }

        $query = Verification::with('sentMessages');

        $query->whereHas(
            'voterlist',
            function (Builder $query) {
                /** @var Builder<VoterList> $query */
                $query->where('owner', $this->getOwner());
            }
        );

        if (!empty($params['filter_by_voterlist'])) {
            $query->where('voterlist_id', $params['filter_by_voterlist']);
        }

        if (!empty($params['sort_by'])) {
            $query->orderBy(
                $params['sort_by'],
                $params['sort_direction']
            );
        }

        return VerificationBasic::collection(
            $query
                ->paginate($params['size'] ?? 5)
                ->appends($params)
        );
    }

    public function delete(Verification $verification): JsonResponse
    {
        if (!empty($verification->sent_at)) {
            return $this->basicResponse(403, ['error' => 'The verification was already sent!']);
        }

        $verification->delete();
        return $this->basicResponse(200);
    }

    public function create(Request $request): VerificationFull|JsonResponse
    {

        $params = $request->all();
        $settings = [
            'voterlist_id' =>
            'required|integer|exists:' . VoterList::class . ',id',
            'redirect_url' =>
            'sometimes|nullable|url',
            'template' =>
            'required|string',
            'subject' =>
            'sometimes|string',
        ];

        if ($errors = $this->findErrors($params, $settings)) {
            return $errors;
        }

        /** @var VoterList $voterlist */
        $voterlist = VoterList::findOrFail($params['voterlist_id']);
        abort_if($this->checkOwner($voterlist->owner), 403);


        $data = [
            'voterlist_id'    => $params['voterlist_id'],
            'template'     => $params['template'],
            'subject'      => $params['subject'] ?? null,
            'redirect_url' => $params['redirect_url'] ?? null,
        ];

        $verification = Verification::create($data);

        return new VerificationFull($verification);
    }

    public function update(Request $request, Verification $verification): VerificationFull|JsonResponse
    {
        $params = $request->all();
        $settings = [
            'redirect_url' =>
            'sometimes|url',
            'template' =>
            'sometimes|string',
            'subject' =>
            'sometimes|string|nullable',
        ];

        if ($errors = $this->findErrors($params, $settings)) {
            return $errors;
        }

        if (array_key_exists('template', $params)) {
            $verification->template = $params['template'];
        }

        if (array_key_exists('subject', $params)) {
            $verification->subject = $params['subject'];
        }

        if (array_key_exists('redirect_url', $params)) {
            $verification->redirect_url = $params['redirect_url'];
        }

        $verification->save();

        return new VerificationFull($verification);
    }

    public function start(ServicesVerification $service, Verification $verification): JsonResponse
    {
        try {
            $status = $service->sendInvites($verification);
        } catch (\Exception $e) {
            return $this->basicResponse(500, ['error' => $e->getMessage()]);
        }

        return $this->basicResponse(200);
    }

    public function startSingle(Request $request, ServicesVerification $service, Voter $voter): JsonResponse
    {
        // $params = $request->all();
        // $settings = [
        //     'subject' =>
        //     'required|string',
        //     'template' =>
        //     'string',
        // ];

        // if ($errors = $this->findErrors($params, $settings)) {
        //     return $errors;
        // }

        $params = [
            'subject' => __('verification.email_subject_template'),
            'template' => __('verification.email_body_template', [
                'org' => $voter->voterLists->first()?->title ?? ''
                ]
            )
        ];

        try {
            $status = $service->sendInviteSingle(
                $voter,
                $params['subject'],
                $params['template']
            );
        } catch (\Exception $e) {
            return $this->basicResponse(500, ['error' => $e->getMessage()]);
        }

        return $this->basicResponse(200);
    }

    public function startTest(Request $request, ServicesVerification $service, Verification $verification): JsonResponse
    {
        $params = $request->all();
        $settings = [
            'to' =>
            'required|json',
            'to.*' =>
            'email',
        ];

        if ($errors = $this->findErrors($params, $settings)) {
            return $errors;
        }

        $to = json_decode($params['to']);

        try {
            $status = $service->sendTestInvites($verification, $to);
        } catch (\Exception $e) {
            return $this->basicResponse(500, ['error' => $e->getMessage()]);
        }

        return $this->basicResponse(200);
    }
}
