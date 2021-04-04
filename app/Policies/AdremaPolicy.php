<?php

namespace App\Policies;

use App\Models\Adrema;
use App\Models\ApiUser as User;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Auth\Access\Response;

class AdremaPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     *
     * @param  \App\Models\User  $user
     * @return mixed
     */
    public function viewAny(User $user)
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Adrema  $adrema
     * @return mixed
     */
    public function view(User $user, Adrema $adrema)
    {
        return $user->owner === $adrema->owner
            ? Response::allow()
            : Response::deny('You do not own this.');
    }

    /**
     * Determine whether the user can create models.
     *
     * @param  \App\Models\User  $user
     * @return mixed
     */
    public function create(User $user)
    {
        return true;
    }

    /**
     * Determine whether the user can update the model.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Adrema  $adrema
     * @return mixed
     */
    public function update(User $user, Adrema $adrema)
    {
        return $user->owner === $adrema->owner
            ? Response::allow()
            : Response::deny('You do not own this.');
    }

    /**
     * Determine whether the user can delete the model.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Adrema  $adrema
     * @return mixed
     */
    public function delete(User $user, Adrema $adrema)
    {
        return $user->owner === $adrema->owner
            ? Response::allow()
            : Response::deny('You do not own this.');
    }
}
