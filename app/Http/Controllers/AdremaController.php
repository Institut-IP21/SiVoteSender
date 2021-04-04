<?php

namespace App\Http\Controllers;

use App\Http\Resources\AdremaBasic;
use App\Http\Resources\AdremaFull;
use App\Http\Resources\VoterBasic;
use App\Models\Adrema;
use App\Models\Voter;
use App\Services\Ballot;
use Illuminate\Database\Eloquent\Builder;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * @Controller(prefix="api/adrema")
 * @Middleware("api")
 */
class AdremaController extends Controller
{

    /**
     * @Get("/{adrema}", as="adrema.show")
     * @Middleware("can:view,adrema")
     */
    public function show(Adrema $adrema)
    {
        return new AdremaFull($adrema);
    }

    /**
     * @Get("/", as="adrema.list")
     * @Middleware("can:viewAny,App\Models\Adrema")
     */
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

        $query = Adrema::with('voters', 'sentMessages');

        $query->where('owner', $this->getOwner());

        if (!empty($params['sort_by'])) {
            $query->orderBy(
                $params['sort_by'],
                $params['sort_direction']
            );
        }

        return AdremaBasic::collection(
            $query
                ->paginate($params['size'] ?? 5)
                ->appends($params)
        );
    }

    /**
     * @Get("/{adrema}/voters", as="adrema.voters.list")
     * @Middleware("can:view,adrema")
     */
    public function listVoters(Request $request, Adrema $adrema)
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

        $query = $adrema->voters();

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

    /**
     * @Delete("/{adrema}", as="adrema.remove")
     * @Middleware("can:delete,adrema")
     */
    public function delete(Adrema $adrema)
    {
        $adrema->delete();
        return $this->basicResponse(200);
    }

    /**
     * @Post("/", as="adrema.create")
     * @Middleware("can:create,App\Models\Adrema")
     */
    public function create(Request $request)
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

        $adrema = Adrema::create($data);

        return new AdremaFull($adrema);
    }

    /**
     * @Post("/{adrema}", as="adrema.update")
     * @Middleware("can:update,adrema")
     */
    public function update(Request $request, Adrema $adrema)
    {
        $params = $request->all();
        $settings = [
            'title' =>
            'required|string',
        ];

        if ($errors = $this->findErrors($params, $settings)) {
            return $errors;
        }

        $adrema->title = $params['title'];
        $adrema->save();

        return new AdremaFull($adrema);
    }

    /**
     * @Post("/{adrema}/voters", as="adrema.voters.add")
     * @Middleware("can:update,adrema")
     */
    public function addVoters(Request $request, Adrema $adrema)
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

        $voters = json_decode($params['voters']);
        foreach ($voters as $voterData) {
            $voter = Voter::create(
                [
                    'title' => $voterData->title,
                    'email' => $voterData->email ?? null,
                    'phone' => $voterData->phone ?? null,
                ]
            );
            $adrema->voters()->attach($voter);
        }

        $adrema->save();

        return new AdremaFull($adrema);
    }

    /**
     * @Delete("/{adrema}/voters", as="adrema.voters.remove")
     * @Middleware("can:update,adrema")
     */
    public function removeVoters(Request $request, Adrema $adrema)
    {
        $params = $request->all();
        $settings = [
            'voters' =>
            'required|json', // Should be just array
        ];

        if ($errors = $this->findErrors($params, $settings)) {
            return $errors;
        }

        $voters = json_decode($params['voters']);
        if (!is_array($voters) && !is_numeric($voters[0])) {
            return $this->basicResponse(400, ['error' => 'Voters have to be an array of IDs!']);
        }
        Voter::destroy($voters);

        return new AdremaFull($adrema);
    }

    /**
     * @Post("/{adrema}/send-invites", as="adrema.invite")
     * @Middleware("can:update,adrema")
     */
    public function sendInvites(Ballot $service, Request $request, Adrema $adrema)
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

        ];

        if ($errors = $this->findErrors($params, $settings)) {
            return $errors;
        }

        if ($adrema->checkAdremaHasBlockedVoters()) {
            return $this->basicResponse(409, [
                'error' => 'Adrema contains blocked voters.'
            ]);
        }

        $codes    = $params['codes'];
        $url      = $params['url'];
        $batch    = $params['batch'];
        $template = $params['template'];
        $subject  = $params['subject'];

        try {
            $status = $service->sendInvites($adrema, $codes, $url, $batch, $template, $subject);
        } catch (\Exception $e) {
            Log::alert('Error while sending invites', ['error' => $e->getMessage()]);
            return $this->basicResponse(500, ['error' => $e->getMessage()]);
        }

        return $this->basicResponse(200);
    }

    /**
     * @Post("/{adrema}/send-results", as="adrema.invite")
     * @Middleware("can:update,adrema")
     */
    public function sendResults(Ballot $service, Request $request, Adrema $adrema)
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

        ];

        if ($errors = $this->findErrors($params, $settings)) {
            return $errors;
        }

        $batch    = $params['batch'];
        $template = $params['template'];
        $subject  = $params['subject'];
        $csv      = $params['csv'];

        try {
            $status = $service->sendResults($adrema, $batch, $template, $subject, $csv);
        } catch (\Exception $e) {
            Log::alert('Error while sending results', ['error' => $e->getMessage()]);
            return $this->basicResponse(500, ['error' => $e->getMessage()]);
        }

        return $this->basicResponse(200);
    }

    /**
     * @Post("send/test", as="adrema.start.test")
     */
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

        ];

        if ($errors = $this->findErrors($params, $settings)) {
            return $errors;
        }

        $to       = json_decode($params['to']);
        $url      = $params['url'];
        $template = $params['template'];
        $subject  = $params['subject'];

        try {
            $status = $service->sendInvitesTest($to, $url, $template, $subject);
        } catch (\Exception $e) {
            Log::alert('Error while sending test invites', ['error' => $e->getMessage()]);
            return $this->basicResponse(500, ['error' => $e->getMessage()]);
        }

        return $this->basicResponse(200);
    }
}
