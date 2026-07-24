<?php

namespace App\Http\Requests\Admin;

use App\Support\Permissions;
use Illuminate\Foundation\Http\FormRequest;

class AssignLeadAuditorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(Permissions::ACCEPT_REJECT_LEADS)
            || $this->user()?->hasRole('super_admin');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'auditor_user_id' => ['required', 'integer', 'exists:users,id'],
        ];
    }
}
