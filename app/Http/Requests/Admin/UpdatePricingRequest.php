<?php

namespace App\Http\Requests\Admin;

use App\Support\Permissions;
use Illuminate\Foundation\Http\FormRequest;

class UpdatePricingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(Permissions::MANAGE_SETTINGS)
            || $this->user()?->hasRole('super_admin');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'price_per_m2' => ['sometimes', 'numeric', 'min:0'],
            'basic_price' => ['nullable', 'numeric', 'min:0'],
            'zone_factor' => ['nullable', 'numeric', 'min:0'],
            'size_factor' => ['nullable', 'numeric', 'min:0'],
            'distance_factor' => ['nullable', 'numeric', 'min:0'],
            'active' => ['sometimes', 'boolean'],
            'effective_from' => ['nullable', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
        ];
    }
}
