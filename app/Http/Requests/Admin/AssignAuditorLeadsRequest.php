<?php

namespace App\Http\Requests\Admin;

use App\Enums\UserRole;
use App\Support\Permissions;
use Illuminate\Foundation\Http\FormRequest;

class AssignAuditorLeadsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole(UserRole::SuperAdmin->value)
            || $this->user()?->can(Permissions::ACCEPT_REJECT_LEADS);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'lead_ids' => ['required', 'array', 'min:1', 'max:50'],
            'lead_ids.*' => ['integer', 'distinct', 'exists:leads,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'lead_ids' => __('rml.admin.users.assign_leads'),
            'lead_ids.*' => __('rml.admin.common.lead_id'),
        ];
    }
}
