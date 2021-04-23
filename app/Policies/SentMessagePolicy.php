<?php

namespace App\Policies;

use App\Models\SentMessage;
use App\Models\ApiUser as User;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Auth\Access\Response;

class SentMessagePolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view the model.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\SentMessage  $sentMessage
     * @return mixed
     */
    public function view(User $user, SentMessage $sentMessage)
    {
        return $user->owner === $sentMessage->voterlist->owner
            ? Response::allow()
            : Response::deny('You do not own this.');
    }

    /**
     * Determine whether the user can update the model.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\SentMessage  $sentMessage
     * @return mixed
     */
    public function update(User $user, SentMessage $sentMessage)
    {
        return $user->owner === $sentMessage->voterlist->owner
            ? Response::allow()
            : Response::deny('You do not own this.');
    }

    /**
     * Determine whether the user can delete the model.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\SentMessage  $sentMessage
     * @return mixed
     */
    public function delete(User $user, SentMessage $sentMessage)
    {
        return $user->owner === $sentMessage->voterlist->owner
            ? Response::allow()
            : Response::deny('You do not own this.');
    }
}
