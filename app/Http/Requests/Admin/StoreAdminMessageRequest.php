<?php

namespace App\Http\Requests\Admin;

use App\Enums\ApprovalStatus;
use App\Enums\MessageThreadCategory;
use App\Enums\UserRole;
use App\Support\Permissions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAdminMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && (
            $user->can(Permissions::MANAGE_MESSAGES)
            || $user->hasRole(UserRole::SuperAdmin->value)
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'recipient_user_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id')->where(
                    fn ($query) => $query->where('approval_status', ApprovalStatus::Approved->value),
                ),
            ],
            'category' => ['required', 'string', Rule::in(MessageThreadCategory::values())],
            'subject' => ['nullable', 'string', 'max:255'],
            'body' => ['required', 'string', 'min:1', 'max:5000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'recipient_user_id' => __('rml.admin.messages.recipient'),
            'category' => __('rml.admin.messages.filter_category'),
            'subject' => __('rml.admin.messages.subject'),
            'body' => __('rml.admin.messages.body'),
        ];
    }
}
