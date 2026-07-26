<?php

namespace App\Http\Requests\Buyer;

use App\Rules\PhoneNumber;
use App\Support\Countries;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBuyerProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50', new PhoneNumber],
            'company_name' => ['nullable', 'string', 'max:255'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'company_email' => ['nullable', 'email', 'max:255'],
            'company_phone' => ['nullable', 'string', 'max:50', new PhoneNumber],
            'whatsapp' => ['nullable', 'string', 'max:50', new PhoneNumber],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:120'],
            'postcode' => ['nullable', 'string', 'max:20'],
            'country' => ['nullable', 'string', 'size:2', Rule::in(Countries::codes())],
            'services_offered' => ['nullable', 'array'],
            'services_offered.*' => ['string', 'max:50'],
            'preferred_zones' => ['nullable', 'array'],
            'preferred_zones.*' => ['string', 'max:10'],
            'max_distance_km' => ['nullable', 'numeric', 'min:1', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => __('rml.buyer.profile.name'),
            'phone' => __('rml.buyer.profile.phone'),
            'company_name' => __('rml.buyer.profile.company_name'),
            'contact_name' => __('rml.buyer.profile.contact_name'),
            'company_email' => __('rml.buyer.profile.email'),
            'company_phone' => __('rml.buyer.profile.phone'),
            'whatsapp' => __('rml.buyer.profile.whatsapp'),
            'address' => __('rml.buyer.profile.address'),
            'city' => __('rml.buyer.profile.city'),
            'postcode' => __('rml.buyer.profile.postcode'),
            'country' => __('rml.buyer.profile.country'),
            'services_offered' => __('rml.buyer.profile.services'),
            'preferred_zones' => __('rml.buyer.profile.preferred_zones'),
            'max_distance_km' => __('rml.buyer.profile.max_distance'),
        ];
    }
}
