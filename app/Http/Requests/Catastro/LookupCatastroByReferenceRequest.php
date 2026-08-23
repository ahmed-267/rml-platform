<?php

namespace App\Http\Requests\Catastro;

use Illuminate\Foundation\Http\FormRequest;

class LookupCatastroByReferenceRequest extends FormRequest
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
            'cadastral_reference' => ['required', 'string', 'max:40'],
        ];
    }
}
