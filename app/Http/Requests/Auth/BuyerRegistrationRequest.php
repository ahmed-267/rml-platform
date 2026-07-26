<?php

namespace App\Http\Requests\Auth;

use App\Rules\PhoneNumber;
use App\Support\Countries;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class BuyerRegistrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->input('max_distance_km') === '' || $this->input('max_distance_km') === null) {
            $this->merge(['max_distance_km' => null]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'company_name' => ['required', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['required', 'string', 'max:50', new PhoneNumber],
            'whatsapp' => ['nullable', 'string', 'max:50', new PhoneNumber],
            'address' => ['required', 'string', 'max:500'],
            'city' => ['required', 'string', 'max:120'],
            'postcode' => ['required', 'string', 'max:20'],
            'country' => ['nullable', 'string', 'size:2', Rule::in(Countries::codes())],
            'password' => ['required', 'confirmed', Password::defaults()],
            'services_offered' => ['required', 'array', 'min:1'],
            'services_offered.*' => ['string', 'in:insulation,double_glazing,heat_pumps'],
            'preferred_zones' => ['required', 'array', 'min:1'],
            'preferred_zones.*' => ['string', 'in:D1,D2,E1,E2'],
            'max_distance_km' => ['nullable', 'integer', 'min:1', 'max:500'],
            'agreement' => ['accepted'],
            'gdpr' => ['accepted'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'company_name' => __('rml.auth.company_name'),
            'name' => __('rml.auth.contact_name'),
            'email' => __('rml.auth.email'),
            'phone' => __('rml.auth.phone'),
            'whatsapp' => __('rml.auth.whatsapp'),
            'address' => __('rml.auth.address'),
            'city' => __('rml.auth.city'),
            'postcode' => __('rml.auth.postcode'),
            'country' => __('rml.auth.country'),
            'password' => __('rml.auth.password'),
            'services_offered' => __('rml.auth.services_offered'),
            'preferred_zones' => __('rml.auth.preferred_zones'),
            'max_distance_km' => __('rml.auth.max_distance'),
            'agreement' => __('rml.auth.agreement'),
            'gdpr' => __('rml.auth.gdpr'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function registrationPayload(): array
    {
        return [
            'company_name' => $this->string('company_name')->toString(),
            'name' => $this->string('name')->toString(),
            'email' => $this->string('email')->toString(),
            'password' => $this->string('password')->toString(),
            'phone' => $this->input('phone'),
            'whatsapp' => $this->input('whatsapp'),
            'address' => $this->input('address'),
            'city' => $this->input('city'),
            'postcode' => $this->input('postcode'),
            'country' => $this->input('country', Countries::DEFAULT),
            'services_offered' => $this->input('services_offered', []),
            'preferred_zones' => $this->input('preferred_zones', []),
            'max_distance_km' => $this->input('max_distance_km'),
        ];
    }
}
