<?php

namespace App\Policies;

use App\Models\ApiUser as User;
use App\Models\Voter;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Auth\Access\Response;

class VoterPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view the model.
     *
     * @param  \App\Models\ApiUser  $user
     * @param  \App\Models\Voter  $voter
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function view(User $user, Voter $voter): Response|bool
    {
        return $voter->voterLists()->where('voterlists.owner', $user->owner)->exists()
            ? Response::allow()
            : Response::deny('You do not own this.');
    }

    /**
     * Determine whether the user can update the model.
     *
     * @param  \App\Models\ApiUser  $user
     * @param  \App\Models\Voter  $voter
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function update(User $user, Voter $voter): Response|bool
    {
        return $voter->voterLists()->where('voterlists.owner', $user->owner)->exists()
            ? Response::allow()
            : Response::deny('You do not own this.');
    }

    /**
     * Determine whether the user can delete the model.
     *
     * @param  \App\Models\ApiUser  $user
     * @param  \App\Models\Voter  $voter
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function delete(User $user, Voter $voter): Response|bool
    {
        return $voter->voterLists()->where('voterlists.owner', $user->owner)->exists()
            ? Response::allow()
            : Response::deny('You do not own this.');
    }
}
