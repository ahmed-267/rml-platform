<?php

namespace App\Http\Requests\Admin;

use App\Enums\UserRole;
use App\Support\Permissions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
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
        $userId = $this->route('user')?->id;

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', 'max:255', Rule::unique('users', 'email')->ignore($userId)],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'phone' => ['nullable', 'string', 'max:50'],
            'locale' => ['nullable', 'string', 'in:en,es,fr'],
            'role' => ['sometimes', 'string', Rule::in($this->assignableRoles())],
        ];
    }

    /**
     * @return list<string>
     */
    private function assignableRoles(): array
    {
        $roles = [
            UserRole::AdminStaff->value,
            UserRole::InternalAuditor->value,
        ];

        if ($this->user()?->hasRole(UserRole::SuperAdmin->value)) {
            $roles[] = UserRole::SuperAdmin->value;
        }

        return $roles;
    }
}
