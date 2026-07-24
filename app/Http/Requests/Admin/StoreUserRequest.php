<?php

namespace App\Http\Requests\Admin;

use App\Enums\UserRole;
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
            'phone' => ['nullable', 'string', 'max:50'],
            'locale' => ['nullable', 'string', 'in:en,es,fr'],
            'role' => ['required', 'string', Rule::in($this->creatableRoles())],
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
