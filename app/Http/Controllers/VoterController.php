<?php

namespace App\Http\Controllers;

use App\Http\Resources\VoterFull;
use App\Models\Voter;
use App\Models\VoterList;
use Illuminate\Support\Facades\DB;
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

        // Two voters with the same address on one list would receive two voting codes.
        // Lock this voter's list rows so a concurrent upload/edit cannot slip one in.
        return DB::transaction(function () use ($voter, $params, $attributes): VoterFull|JsonResponse {
            $listIds = $voter->voterLists()->pluck('voterlists.id');
            VoterList::whereIn('id', $listIds)->orderBy('id')->lockForUpdate()->get();

            $email = Voter::normalizeEmail($params['email'] ?? null);
            if ($email !== null && $this->emailTakenOnSharedList($voter, $email, $listIds->all())) {
                return response()->json([
                    'message' => 'This email address is already used by another voter on the list.',
                    'errors'  => ['email' => ['This email address is already used by another voter on the list.']],
                ], 422);
            }

            $voter->update($attributes);

            return new VoterFull($voter->refresh());
        });
    }

    /**
     * @param array<int, int> $listIds
     */
    private function emailTakenOnSharedList(Voter $voter, string $normalizedEmail, array $listIds): bool
    {
        if ($listIds === []) {
            return false;
        }

        return Voter::query()
            ->whereKeyNot($voter->id)
            ->whereRaw('LOWER(TRIM(email)) = ?', [$normalizedEmail])
            ->whereIn('id', function ($query) use ($listIds): void {
                $query->select('voter_id')->from('voterlist_voter')->whereIn('voterlist_id', $listIds);
            })
            ->exists();
    }

    public function delete(Voter $voter): JsonResponse
    {
        $voter->delete();
        return $this->basicResponse(200);
    }
}
