<?php

namespace App\Http\Requests\Admin;

use App\Support\Permissions;
use App\Support\RejectionReasons;
use Illuminate\Foundation\Http\FormRequest;

class RejectRegistrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('approve_sellers')
            || $this->user()?->can('approve_buyers')
            || $this->user()?->can(Permissions::MANAGE_USERS)
            || $this->user()?->hasRole('super_admin');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return RejectionReasons::validationRules(RejectionReasons::CONTEXT_ACCOUNTS);
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
