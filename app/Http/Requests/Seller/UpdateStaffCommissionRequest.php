<?php

namespace App\Http\Requests\Seller;

use App\Models\User;
use App\Support\Permissions;
use Illuminate\Foundation\Http\FormRequest;

class UpdateStaffCommissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $admin = $this->user();
        /** @var User|null $staffUser */
        $staffUser = $this->route('user');

        if (! $admin?->can(Permissions::MANAGE_STAFF_COMMISSIONS)) {
            return false;
        }

        $adminCompanyId = $admin->sellerProfile?->company_id;
        $staffCompanyId = $staffUser?->sellerProfile?->company_id;

        return $adminCompanyId !== null
            && $staffCompanyId !== null
            && $adminCompanyId === $staffCompanyId;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'commission_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->input('commission_rate') === '') {
            $this->merge(['commission_rate' => null]);
        }
    }
}
