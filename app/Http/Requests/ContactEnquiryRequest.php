<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ContactEnquiryRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'enquiry_type' => ['required', 'string', 'max:100'],
            'subject' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => __('rml.landing.contact_name'),
            'email' => __('rml.landing.contact_email'),
            'enquiry_type' => __('rml.landing.contact_type'),
            'subject' => __('rml.landing.contact_subject'),
            'message' => __('rml.landing.contact_message'),
        ];
    }
}
