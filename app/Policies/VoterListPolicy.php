<?php

namespace App\Policies;

use App\Models\VoterList;
use App\Models\ApiUser as User;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Auth\Access\Response;

class VoterListPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     *
     * @param  \App\Models\ApiUser  $user
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function viewAny(User $user): Response|bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     *
     * @param  \App\Models\ApiUser  $user
     * @param  \App\Models\VoterList  $voterlist
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function view(User $user, VoterList $voterlist): Response|bool
    {
        return $user->owner === $voterlist->owner
            ? Response::allow()
            : Response::deny('You do not own this.');
    }

    /**
     * Determine whether the user can create models.
     *
     * @param  \App\Models\ApiUser  $user
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function create(User $user): Response|bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the model.
     *
     * @param  \App\Models\ApiUser  $user
     * @param  \App\Models\VoterList  $voterlist
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function update(User $user, VoterList $voterlist): Response|bool
    {
        return $user->owner === $voterlist->owner
            ? Response::allow()
            : Response::deny('You do not own this.');
    }

    /**
     * Determine whether the user can delete the model.
     *
     * @param  \App\Models\ApiUser  $user
     * @param  \App\Models\VoterList  $voterlist
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function delete(User $user, VoterList $voterlist): Response|bool
    {
        return $user->owner === $voterlist->owner
            ? Response::allow()
            : Response::deny('You do not own this.');
    }
}
