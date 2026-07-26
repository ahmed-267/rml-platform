<?php

namespace App\Http\Requests\Admin;

use App\Enums\TemplateDocumentType;
use App\Support\Permissions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(Permissions::MANAGE_SETTINGS)
            || $this->user()?->hasRole('super_admin');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', 'string', Rule::in(TemplateDocumentType::values()), Rule::unique('template_documents', 'type')],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'content' => ['required', 'string', 'max:50000'],
        ];
    }
}
