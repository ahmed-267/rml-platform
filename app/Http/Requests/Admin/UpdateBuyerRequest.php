<?php

namespace App\Http\Requests\Admin;

use App\Rules\PhoneNumber;
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
            'phone' => ['nullable', 'string', 'max:50', new PhoneNumber],
            'locale' => ['nullable', 'string', 'in:en,es,fr'],
            'services_offered' => ['nullable', 'array'],
            'services_offered.*' => ['string', 'max:120'],
            'preferred_zones' => ['nullable', 'array'],
            'preferred_zones.*' => ['string', 'max:20'],
            'max_distance_km' => ['nullable', 'numeric', 'min:0', 'max:5000'],
            'billing_status' => ['nullable', 'string', 'max:50'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'company_email' => ['nullable', 'email', 'max:255'],
            'company_phone' => ['nullable', 'string', 'max:50', new PhoneNumber],
            'company_address' => ['nullable', 'string', 'max:500'],
            'company_city' => ['nullable', 'string', 'max:120'],
            'company_postcode' => ['nullable', 'string', 'max:20'],
            'company_country' => ['nullable', 'string', 'max:120'],
            'company_notes' => ['nullable', 'string', 'max:2000'],
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
            'phone' => __('rml.admin.common.phone'),
            'locale' => __('rml.common.language'),
            'services_offered' => __('rml.auth.services_offered'),
            'preferred_zones' => __('rml.auth.preferred_zones'),
            'max_distance_km' => __('rml.auth.max_distance'),
            'billing_status' => __('rml.buyer.profile.billing_status'),
            'company_name' => __('rml.auth.company_name'),
            'company_email' => __('rml.admin.common.email'),
            'company_phone' => __('rml.admin.common.phone'),
            'company_address' => __('rml.auth.company_address'),
            'company_city' => __('rml.admin.common.city'),
            'company_postcode' => __('rml.auth.postcode'),
            'company_country' => __('rml.auth.country'),
            'company_notes' => __('rml.seller.leads.notes'),
        ];
    }
}
