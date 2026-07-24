<?php

namespace App\Http\Requests\Admin;

use App\Support\Permissions;
use Illuminate\Foundation\Http\FormRequest;

class RequestLeadAuditInfoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(Permissions::ACCEPT_REJECT_LEADS)
            || $this->user()?->can(Permissions::AUDIT_LEADS)
            || $this->user()?->hasRole('super_admin');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'requested_info' => ['required', 'string', 'min:5', 'max:2000'],
            'audit_notes' => ['nullable', 'string', 'max:5000'],
            'checklist' => ['nullable', 'array'],
            'checklist.*.id' => ['nullable', 'integer'],
            'checklist.*.checked' => ['nullable', 'boolean'],
            'checklist.*.notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
