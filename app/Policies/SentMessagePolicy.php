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
     * @param SentMessage $sentMessage
     * @return Response|bool
     */
    public function view(User $user, SentMessage $sentMessage): Response|bool
    {
        $listOwner = $sentMessage->voterList()->value('owner');
        return $listOwner !== null && $user->owner === $listOwner
            ? Response::allow()
            : Response::deny('You do not own this.');
    }

    /**
     * Determine whether the user can update the model.
     *
     * @param  \App\Models\ApiUser  $user
     * @param SentMessage $sentMessage
     * @return Response|bool
     */
    public function update(User $user, SentMessage $sentMessage): Response|bool
    {
        $listOwner = $sentMessage->voterList()->value('owner');
        return $listOwner !== null && $user->owner === $listOwner
            ? Response::allow()
            : Response::deny('You do not own this.');
    }

    /**
     * Determine whether the user can delete the model.
     *
     * @param  \App\Models\ApiUser  $user
     * @param SentMessage $sentMessage
     * @return Response|bool
     */
    public function delete(User $user, SentMessage $sentMessage): Response|bool
    {
        $listOwner = $sentMessage->voterList()->value('owner');
        return $listOwner !== null && $user->owner === $listOwner
            ? Response::allow()
            : Response::deny('You do not own this.');
    }
}
