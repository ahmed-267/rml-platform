<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Lead;
use App\Models\LeadAudit;
use App\Models\User;
use App\Support\Permissions;

class LeadAuditPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permissions::AUDIT_LEADS)
            || $user->can(Permissions::ACCEPT_REJECT_LEADS)
            || $user->hasRole(UserRole::SuperAdmin->value);
    }

    public function view(User $user, LeadAudit $leadAudit): bool
    {
        if ($user->hasRole(UserRole::SuperAdmin->value) || $user->can(Permissions::ACCEPT_REJECT_LEADS)) {
            return true;
        }

        if (! $user->can(Permissions::AUDIT_LEADS)) {
            return false;
        }

        if ($user->hasRole(UserRole::AdminStaff->value)) {
            return true;
        }

        return (int) $leadAudit->auditor_user_id === (int) $user->id;
    }

    public function update(User $user, LeadAudit $leadAudit): bool
    {
        return $this->view($user, $leadAudit);
    }

    public function recommend(User $user, LeadAudit $leadAudit): bool
    {
        if ($user->hasRole(UserRole::SuperAdmin->value) || $user->can(Permissions::ACCEPT_REJECT_LEADS)) {
            return true;
        }

        if (! $user->can(Permissions::AUDIT_LEADS)) {
            return false;
        }

        return (int) $leadAudit->auditor_user_id === (int) $user->id
            || $user->hasRole(UserRole::AdminStaff->value);
    }

    public function auditLead(User $user, Lead $lead): bool
    {
        if ($user->hasRole(UserRole::SuperAdmin->value) || $user->can(Permissions::ACCEPT_REJECT_LEADS)) {
            return true;
        }

        if (! $user->can(Permissions::AUDIT_LEADS)) {
            return false;
        }

        if ($user->hasRole(UserRole::AdminStaff->value)) {
            return true;
        }

        $assigned = LeadAudit::query()
            ->where('lead_id', $lead->id)
            ->where('auditor_user_id', $user->id)
            ->exists();

        return $assigned;
    }

    public function assign(User $user): bool
    {
        return $user->hasRole(UserRole::SuperAdmin->value)
            || $user->can(Permissions::ACCEPT_REJECT_LEADS);
    }
}
