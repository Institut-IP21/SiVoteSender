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
     * Determine whether the user can view the model.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Verification  $verification
     * @return mixed
     */
    public function view(User $user, Verification $verification)
    {
        return $user->owner === $verification->adrema->owner
            ? Response::allow()
            : Response::deny('You do not own this.');
    }

    /**
     * Determine whether the user can update the model.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Verification  $verification
     * @return mixed
     */
    public function update(User $user, Verification $verification)
    {
        return $user->owner === $verification->adrema->owner
            ? Response::allow()
            : Response::deny('You do not own this.');
    }

    /**
     * Determine whether the user can delete the model.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Verification  $verification
     * @return mixed
     */
    public function delete(User $user, Verification $verification)
    {
        return $user->owner === $verification->adrema->owner
            ? Response::allow()
            : Response::deny('You do not own this.');
    }
}
