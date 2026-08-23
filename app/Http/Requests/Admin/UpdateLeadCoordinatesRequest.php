<?php

namespace App\Http\Requests\Admin;

use App\Support\Permissions;
use Illuminate\Foundation\Http\FormRequest;

class UpdateLeadCoordinatesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('super_admin')
            || $this->user()?->can(Permissions::VIEW_LEADS)
            || $this->user()?->can(Permissions::ACCEPT_REJECT_LEADS);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'cadastral_reference' => ['nullable', 'string', 'max:40'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'latitude' => __('rml.location.latitude'),
            'longitude' => __('rml.location.longitude'),
            'cadastral_reference' => __('rml.location.cadastral_reference'),
        ];
    }
}
