<?php

namespace App\Http\Requests\Admin;

use App\Rules\PhoneNumber;
use App\Support\Permissions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateGeneralSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(Permissions::MANAGE_SETTINGS)
            || $this->user()?->hasRole('super_admin');
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('default_currency') && is_string($this->input('default_currency'))) {
            $this->merge([
                'default_currency' => strtoupper($this->input('default_currency')),
            ]);
        }

        foreach ([
            'package_allow_mixed_scheme',
            'package_allow_without_buyer',
            'package_reservation_lock',
        ] as $boolKey) {
            if ($this->has($boolKey)) {
                $this->merge([
                    $boolKey => filter_var($this->input($boolKey), FILTER_VALIDATE_BOOLEAN),
                ]);
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'support_email' => ['nullable', 'email', 'max:255'],
            'support_phone' => ['nullable', 'string', 'max:50', new PhoneNumber],
            'default_locale' => ['nullable', 'string', 'in:en,es,fr'],
            'default_currency' => ['nullable', 'string', Rule::in(['EUR', 'GBP', 'USD'])],
            'bank_transfer_instructions' => ['nullable', 'string', 'max:5000'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'package_default_status' => ['nullable', 'string', Rule::in(['draft', 'available'])],
            'package_allow_mixed_scheme' => ['nullable', 'boolean'],
            'package_allow_without_buyer' => ['nullable', 'boolean'],
            'package_reservation_lock' => ['nullable', 'boolean'],
            'package_expiry_days' => ['nullable', 'integer', 'min:1', 'max:365'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'support_email' => __('rml.admin.settings.support_email'),
            'support_phone' => __('rml.admin.settings.support_phone'),
            'default_locale' => __('rml.admin.settings.default_locale'),
            'default_currency' => __('rml.admin.settings.default_currency'),
            'bank_transfer_instructions' => __('rml.admin.settings.bank_transfer_instructions'),
            'company_name' => __('rml.admin.settings.company_name'),
        ];
    }
}
