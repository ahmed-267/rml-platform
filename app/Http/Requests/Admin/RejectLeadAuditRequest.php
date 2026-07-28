<?php

namespace App\Http\Requests\Admin;

use App\Support\Permissions;
use App\Support\RejectionReasons;
use Illuminate\Foundation\Http\FormRequest;

class RejectLeadAuditRequest extends FormRequest
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
        return array_merge(
            RejectionReasons::validationRules(
                RejectionReasons::CONTEXT_LEADS,
                'reason_code',
                'comment',
            ),
            [
                'audit_notes' => ['nullable', 'string', 'max:5000'],
                'checklist' => ['nullable', 'array'],
                'checklist.*.id' => ['nullable', 'integer'],
                'checklist.*.checked' => ['nullable', 'boolean'],
                'checklist.*.notes' => ['nullable', 'string', 'max:1000'],
            ],
        );
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'reason_code' => __('rml.rejection.reason'),
            'comment' => __('rml.rejection.comment'),
        ];
    }
}
