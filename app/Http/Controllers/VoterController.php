<?php

namespace App\Http\Controllers;

use App\Http\Resources\VoterFull;
use App\Models\Voter;

class VoterController extends Controller
{

    public function show(Voter $voter)
    {
        return new VoterFull($voter);
    }

    public function delete(Voter $voter)
    {
        $voter->delete();
        return $this->basicResponse(200);
    }
}
