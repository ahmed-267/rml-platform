<?php

namespace App\Http\Requests;

use App\Rules\PhoneNumber;
use Illuminate\Foundation\Http\FormRequest;

class HomeownerEnquiryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'full_name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:50', new PhoneNumber],
            'email' => ['required', 'email', 'max:255'],
            'property_address' => ['required', 'string', 'max:500'],
            'postcode' => ['required', 'string', 'max:20'],
            'service' => ['required', 'string', 'max:100'],
            'message' => ['nullable', 'string', 'max:2000'],
            'consent' => ['accepted'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'full_name' => __('rml.landing.install_full_name'),
            'phone' => __('rml.landing.install_phone'),
            'email' => __('rml.landing.install_email'),
            'property_address' => __('rml.landing.install_address'),
            'postcode' => __('rml.landing.install_postcode'),
            'service' => __('rml.landing.install_service'),
            'message' => __('rml.landing.install_message'),
            'consent' => __('rml.landing.install_consent'),
        ];
    }
}
