<?php

namespace App\Http\Requests\Catastro;

use Illuminate\Foundation\Http\FormRequest;

class LookupCatastroByAddressRequest extends FormRequest
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
            'province' => ['required', 'string', 'max:120'],
            'municipality' => ['required', 'string', 'max:120'],
            'street_type' => ['nullable', 'string', 'max:20'],
            'street_name' => ['required', 'string', 'max:180'],
            'street_number' => ['nullable', 'string', 'max:20'],
            'block' => ['nullable', 'string', 'max:20'],
            'staircase' => ['nullable', 'string', 'max:20'],
            'floor' => ['nullable', 'string', 'max:20'],
            'door' => ['nullable', 'string', 'max:20'],
            'postcode' => ['nullable', 'string', 'max:16'],
        ];
    }
}
