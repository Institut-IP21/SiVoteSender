<?php

namespace App\Http\Controllers;

use App\Http\Resources\VoterFull;
use App\Models\Voter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VoterController extends Controller
{

    public function show(Voter $voter): VoterFull
    {
        return new VoterFull($voter);
    }

    /**
     * Update a single voter's identity fields. Only the keys actually sent are
     * touched. Changing the email invalidates the prior verification/block state for
     * the old address — the new address is unverified and unblocked until it proves
     * otherwise. Ownership is enforced by the can:update,voter policy on the route.
     */
    public function update(Voter $voter, Request $request): VoterFull|JsonResponse
    {
        $params = $request->all();
        $settings = [
            'title' => 'sometimes|nullable|string|max:255',
            'email' => 'sometimes|nullable|email|max:255',
            'phone' => 'sometimes|nullable|string|max:255',
        ];

        if ($errors = $this->findErrors($params, $settings)) {
            return $errors;
        }

        $attributes = [];
        foreach (['title', 'email', 'phone'] as $key) {
            if (array_key_exists($key, $params)) {
                $attributes[$key] = $params[$key];
            }
        }

        if (array_key_exists('email', $params) && $params['email'] !== $voter->email) {
            $attributes['email_verified'] = null;
            $attributes['email_blocked'] = false;
        }

        $voter->update($attributes);

        return new VoterFull($voter->refresh());
    }

    public function delete(Voter $voter): JsonResponse
    {
        $voter->delete();
        return $this->basicResponse(200);
    }
}
