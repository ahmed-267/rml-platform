<?php

namespace App\Http\Requests\Catastro;

use Illuminate\Foundation\Http\FormRequest;

class UpdateLeadCadastralReferenceRequest extends FormRequest
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
            'cadastral_reference' => ['nullable', 'string', 'max:40'],
        ];
    }
}
