<?php

namespace App\Http\Requests\Admin;

use App\Support\Permissions;
use Illuminate\Foundation\Http\FormRequest;

class UpdateGeneralSettingsRequest extends FormRequest
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
            'support_email' => ['nullable', 'email', 'max:255'],
            'support_phone' => ['nullable', 'string', 'max:50'],
            'default_locale' => ['nullable', 'string', 'in:en,es,fr'],
            'default_currency' => ['nullable', 'string', 'max:10'],
            'bank_transfer_instructions' => ['nullable', 'string', 'max:5000'],
            'company_name' => ['nullable', 'string', 'max:255'],
        ];
    }
}
