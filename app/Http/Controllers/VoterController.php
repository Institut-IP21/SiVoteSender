<?php

namespace App\Http\Controllers;

use App\Http\Resources\VoterFull;
use App\Models\Voter;

/**
 * @Controller(prefix="api/voter")
 * @Middleware("api")
 */
class VoterController extends Controller
{

    /**
     * @Get("/{voter}", as="voter.show")
     * @Middleware("can:view,voter")
     */
    public function show(Voter $voter)
    {
        return new VoterFull($voter);
    }

    /**
     * @Delete("/{voter}", as="voter.remove")
     * @Middleware("can:delete,voter")
     */
    public function delete(Voter $voter)
    {
        $voter->delete();
        return $this->basicResponse(200);
    }
}
