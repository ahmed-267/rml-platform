<?php

namespace App\Http\Requests;

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
            'phone' => ['required', 'string', 'max:50'],
            'email' => ['required', 'email', 'max:255'],
            'property_address' => ['required', 'string', 'max:500'],
            'postcode' => ['required', 'string', 'max:20'],
            'service' => ['required', 'string', 'max:100'],
            'message' => ['nullable', 'string', 'max:2000'],
            'consent' => ['accepted'],
        ];
    }
}
