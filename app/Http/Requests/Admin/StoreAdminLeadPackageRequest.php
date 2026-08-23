<?php

namespace App\Http\Requests\Admin;

use App\Services\Admin\AdminNearbyLeadMatchService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAdminLeadPackageRequest extends FormRequest
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
            'match_installer_id' => ['nullable', 'integer', 'exists:companies,id'],
            'lead_ids' => ['required', 'array', 'min:1'],
            'lead_ids.*' => ['integer', 'distinct', 'exists:leads,id'],
            'name' => ['nullable', 'string', 'max:255'],
            'radius_km' => [
                'nullable',
                'integer',
                Rule::in(AdminNearbyLeadMatchService::RADIUS_OPTIONS_KM),
            ],
        ];
    }
}
