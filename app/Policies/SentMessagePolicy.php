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
     * @param  \App\Models\ApiUser  $user
     * @param  \App\Models\SentMessage  $sentMessage
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function view(User $user, SentMessage $sentMessage): Response|bool
    {
        return $user->owner === $sentMessage->voterList->owner
            ? Response::allow()
            : Response::deny('You do not own this.');
    }

    /**
     * Determine whether the user can update the model.
     *
     * @param  \App\Models\ApiUser  $user
     * @param  \App\Models\SentMessage  $sentMessage
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function update(User $user, SentMessage $sentMessage): Response|bool
    {
        return $user->owner === $sentMessage->voterList->owner
            ? Response::allow()
            : Response::deny('You do not own this.');
    }

    /**
     * Determine whether the user can delete the model.
     *
     * @param  \App\Models\ApiUser  $user
     * @param  \App\Models\SentMessage  $sentMessage
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function delete(User $user, SentMessage $sentMessage): Response|bool
    {
        return $user->owner === $sentMessage->voterList->owner
            ? Response::allow()
            : Response::deny('You do not own this.');
    }
}
