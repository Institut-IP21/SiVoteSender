<?php

namespace App\Policies;

use App\Models\ApiUser as User;
use App\Models\Verification;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Auth\Access\Response;

class VerificationPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can list verifications. Allowed for any API user;
     * the listing query itself is owner-scoped (VerificationApiController::list
     * filters `whereHas('voterlist', owner = getOwner())`). Mirrors VoterListPolicy.
     */
    public function viewAny(User $user): Response|bool
    {
        return true;
    }

    /**
     * Determine whether the user can create the model.
     *
     * @param  \App\Models\ApiUser  $user
     * @return string
     */
    public function create(User $user): string
    {
        return $user->owner;
    }
    /**
     * Determine whether the user can view the model.
     *
     * @param  \App\Models\ApiUser  $user
     * @param Verification $verification
     * @return Response|bool
     */
    public function view(User $user, Verification $verification): Response|bool
    {
        return $user->owner === $verification->voterList->owner
            ? Response::allow()
            : Response::deny('You do not own this.');
    }

    /**
     * Determine whether the user can update the model.
     *
     * @param  \App\Models\ApiUser  $user
     * @param Verification $verification
     * @return Response|bool
     */
    public function update(User $user, Verification $verification): Response|bool
    {
        return $user->owner === $verification->voterList->owner
            ? Response::allow()
            : Response::deny('You do not own this.');
    }

    /**
     * Determine whether the user can delete the model.
     *
     * @param  \App\Models\ApiUser  $user
     * @param Verification $verification
     * @return Response|bool
     */
    public function delete(User $user, Verification $verification): Response|bool
    {
        return $user->owner === $verification->voterList->owner
            ? Response::allow()
            : Response::deny('You do not own this.');
    }
}
