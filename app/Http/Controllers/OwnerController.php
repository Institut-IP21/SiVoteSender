<?php

namespace App\Http\Controllers;

use App\Http\Resources\AdremaBasic;
use App\Http\Resources\AdremaFull;
use App\Http\Resources\PersonalizationFull;
use App\Http\Resources\VoterBasic;
use App\Models\Adrema;
use App\Models\Personalization;
use App\Models\Voter;
use App\Services\Ballot;
use Illuminate\Database\Eloquent\Builder;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * @Controller(prefix="api/owner")
 * @Middleware("api")
 */
class OwnerController extends Controller
{
    /**
     * @Post("/personalization", as="owner.personalization")
     */
    public function updatePersonalization(Request $request)
    {
        $params = $request->all();
        $settings = [
            'photo_url' => 'required|string',
        ];

        if ($errors = $this->findErrors($params, $settings)) {
            return $errors;
        }

        $owner = $this->getOwner();

        $personalization = Personalization::updateOrCreate(
            [
                'owner' => $owner
            ],
            [
                'photo_url' => $params['photo_url']
            ]
        );

        return new PersonalizationFull($personalization);
    }
}
