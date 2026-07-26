<?php

namespace App\Http\Requests\Admin;

use App\Enums\ApprovalStatus;
use App\Enums\UserRole;
use App\Rules\PhoneNumber;
use App\Support\Permissions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(Permissions::MANAGE_USERS)
            || $this->user()?->hasRole(UserRole::SuperAdmin->value);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'phone' => ['nullable', 'string', 'max:50', new PhoneNumber],
            'locale' => ['nullable', 'string', 'in:en,es,fr'],
            'role' => ['required', 'string', Rule::in($this->creatableRoles())],
            'approval_status' => [
                'nullable',
                'string',
                Rule::in([
                    ApprovalStatus::Pending->value,
                    ApprovalStatus::Approved->value,
                ]),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => __('rml.admin.common.name'),
            'email' => __('rml.admin.common.email'),
            'password' => __('rml.admin.users.password'),
            'phone' => __('rml.admin.common.phone'),
            'locale' => __('rml.common.language'),
            'role' => __('rml.admin.users.role'),
            'approval_status' => __('rml.admin.common.status'),
        ];
    }

    /**
     * Roles that can be assigned when creating a user from the admin UI.
     * Super Admin is intentionally excluded.
     *
     * @return list<string>
     */
    private function creatableRoles(): array
    {
        return [
            UserRole::AdminStaff->value,
            UserRole::InternalAuditor->value,
            UserRole::SellerCompanyAdmin->value,
            UserRole::SellerStaff->value,
            UserRole::IndividualSellerAgent->value,
            UserRole::BuyerAdmin->value,
        ];
    }
}
