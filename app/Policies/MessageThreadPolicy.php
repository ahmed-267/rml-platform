<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\MessageThread;
use App\Models\User;
use App\Support\Permissions;

class MessageThreadPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permissions::MANAGE_MESSAGES)
            || $user->can(Permissions::MESSAGE_SUPPORT)
            || $user->hasRole(UserRole::SuperAdmin->value);
    }

    public function view(User $user, MessageThread $thread): bool
    {
        if ($user->can(Permissions::MANAGE_MESSAGES) || $user->hasRole(UserRole::SuperAdmin->value)) {
            return true;
        }

        return $thread->created_by_user_id === $user->id
            || $thread->assigned_to_user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->can(Permissions::MESSAGE_SUPPORT)
            || $user->can(Permissions::MANAGE_MESSAGES);
    }

    public function update(User $user, MessageThread $thread): bool
    {
        return $user->can(Permissions::MANAGE_MESSAGES)
            || $thread->created_by_user_id === $user->id
            || $user->hasRole(UserRole::SuperAdmin->value);
    }

    public function reply(User $user, MessageThread $thread): bool
    {
        return $this->view($user, $thread);
    }
}
