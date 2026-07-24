<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class RejectRegistrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('approve_sellers')
            || $this->user()?->can('approve_buyers')
            || $this->user()?->hasRole('super_admin');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reason' => ['nullable', 'string', 'max:1000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (! $this->filled('reason')) {
            $this->merge([
                'reason' => __('rml.admin.common.reject_default_reason'),
            ]);
        }
    }
}
