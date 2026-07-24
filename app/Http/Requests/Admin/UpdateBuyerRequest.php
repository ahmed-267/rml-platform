<?php

namespace App\Http\Requests\Admin;

use App\Support\Permissions;
use Illuminate\Foundation\Http\FormRequest;

class UpdateBuyerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(Permissions::MANAGE_BUYERS)
            || $this->user()?->hasRole('super_admin');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'locale' => ['nullable', 'string', 'in:en,es,fr'],
            'services_offered' => ['nullable', 'array'],
            'services_offered.*' => ['string', 'max:120'],
            'preferred_zones' => ['nullable', 'array'],
            'preferred_zones.*' => ['string', 'max:20'],
            'max_distance_km' => ['nullable', 'numeric', 'min:0', 'max:5000'],
            'billing_status' => ['nullable', 'string', 'max:50'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'company_email' => ['nullable', 'email', 'max:255'],
            'company_phone' => ['nullable', 'string', 'max:50'],
            'company_address' => ['nullable', 'string', 'max:500'],
            'company_city' => ['nullable', 'string', 'max:120'],
            'company_postcode' => ['nullable', 'string', 'max:20'],
            'company_country' => ['nullable', 'string', 'max:120'],
            'company_notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
