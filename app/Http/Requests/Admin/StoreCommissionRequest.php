<?php

namespace App\Http\Requests\Admin;

use App\Enums\CommissionAppliesTo;
use App\Support\Permissions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCommissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(Permissions::MANAGE_SETTINGS)
            || $this->user()?->hasRole('super_admin');
    }

    /**
     * @return list<string>
     */
    public static function creatableAppliesTo(): array
    {
        return [
            CommissionAppliesTo::SellerCompany->value,
            CommissionAppliesTo::IndividualAgent->value,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'applies_to' => ['required', 'string', Rule::in(self::creatableAppliesTo())],
            'rate_per_m2' => ['required', 'numeric', 'min:0'],
            'percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'active' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
