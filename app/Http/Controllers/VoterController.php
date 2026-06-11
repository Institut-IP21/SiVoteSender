<?php

namespace App\Http\Controllers;

use App\Http\Resources\VoterFull;
use App\Models\Voter;
use Illuminate\Http\JsonResponse;

class VoterController extends Controller
{

    public function show(Voter $voter): VoterFull
    {
        return new VoterFull($voter);
    }

    public function delete(Voter $voter): JsonResponse
    {
        $voter->delete();
        return $this->basicResponse(200);
    }
}
