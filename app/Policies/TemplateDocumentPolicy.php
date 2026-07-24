<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\TemplateDocument;
use App\Models\User;
use App\Support\Permissions;

class TemplateDocumentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permissions::MANAGE_SETTINGS)
            || $user->hasRole(UserRole::SuperAdmin->value);
    }

    public function view(User $user, TemplateDocument $templateDocument): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->can(Permissions::MANAGE_SETTINGS)
            || $user->hasRole(UserRole::SuperAdmin->value);
    }

    public function update(User $user, TemplateDocument $templateDocument): bool
    {
        return $this->create($user);
    }

    public function delete(User $user, TemplateDocument $templateDocument): bool
    {
        return $user->hasRole(UserRole::SuperAdmin->value);
    }
}
