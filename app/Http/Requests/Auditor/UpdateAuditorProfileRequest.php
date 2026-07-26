<?php

namespace App\Http\Requests\Auditor;

use App\Rules\PhoneNumber;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAuditorProfileRequest extends FormRequest
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
            'locale' => ['nullable', Rule::in(['en', 'es', 'fr'])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => __('rml.auditor.profile.name'),
            'phone' => __('rml.auditor.profile.phone'),
            'locale' => __('rml.auditor.profile.language'),
        ];
    }
}
