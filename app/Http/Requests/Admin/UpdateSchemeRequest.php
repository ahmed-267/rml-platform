<?php

namespace App\Http\Requests\Admin;

use App\Support\Permissions;
use App\Support\SchemeConfig;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSchemeRequest extends FormRequest
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
        $schemeId = $this->route('scheme')?->id;

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'slug' => ['sometimes', 'string', 'max:120', Rule::unique('schemes', 'slug')->ignore($schemeId)],
            'description' => ['nullable', 'string', 'max:5000'],
            'active' => ['sometimes', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'metadata' => ['nullable', 'array'],
            'metadata.lead_type' => ['nullable', 'string', Rule::in(SchemeConfig::LEAD_TYPES)],
            'metadata.pricing_basis' => ['nullable', 'string', Rule::in(SchemeConfig::PRICING_BASES)],
            'metadata.pricing_basis_explanation' => ['nullable', 'string', 'max:2000'],
            'metadata.required_inputs' => ['nullable', 'array'],
            'metadata.required_inputs.*' => ['string', Rule::in(SchemeConfig::INPUT_KEYS)],
            'metadata.evidence' => ['nullable', 'array'],
            'metadata.evidence.*' => ['string', 'max:120'],
            'metadata.pricing_factors' => ['nullable', 'array'],
            'metadata.pricing_factors.price_per_window' => ['nullable', 'numeric', 'min:0'],
            'metadata.pricing_factors.window_count_factor' => ['nullable', 'numeric', 'min:0'],
            'metadata.pricing_factors.glazing_area_factor' => ['nullable', 'numeric', 'min:0'],
            'metadata.pricing_factors.quality_factor' => ['nullable', 'numeric', 'min:0'],
            'metadata.pricing_factors.kw_factor' => ['nullable', 'numeric', 'min:0'],
            'metadata.pricing_factors.price_per_kw' => ['nullable', 'numeric', 'min:0'],
            'metadata.pricing_factors.feasibility_factor' => ['nullable', 'numeric', 'min:0'],
            'metadata.pricing_factors.system_type_factor' => ['nullable', 'numeric', 'min:0'],
            'metadata.eligibility' => ['nullable', 'array'],
            'metadata.eligibility.conditions' => ['nullable', 'string', 'max:5000'],
            'metadata.eligibility.income' => ['nullable', 'string', 'max:2000'],
            'metadata.eligibility.property_notes' => ['nullable', 'string', 'max:5000'],
            'metadata.eligibility.required_documents' => ['nullable', 'string', 'max:5000'],
            'metadata.requirements' => ['nullable', 'array'],
            'metadata.requirements.zone_required' => ['nullable', 'boolean'],
            'metadata.requirements.area_required' => ['nullable', 'boolean'],
            'metadata.requirements.distance_required' => ['nullable', 'boolean'],
            'metadata.requirements.constraints' => ['nullable', 'string', 'max:5000'],
            'metadata.requirements.other' => ['nullable', 'string', 'max:5000'],
            'metadata.pricing_notes' => ['nullable', 'string', 'max:5000'],
            'metadata.algorithm' => ['nullable', 'array'],
            'metadata.algorithm.formula' => ['nullable', 'string', 'max:2000'],
            'metadata.algorithm.notes' => ['nullable', 'string', 'max:5000'],
            'metadata.technical_details' => ['nullable', 'string', 'max:5000'],
            'zone_prices' => ['nullable', 'array'],
            'zone_prices.*' => ['nullable', 'numeric', 'min:0'],
            'base_price' => ['nullable', 'numeric', 'min:0'],
            'price_per_m2' => ['nullable', 'numeric', 'min:0'],
            'zone_factor' => ['nullable', 'numeric', 'min:0'],
            'size_factor' => ['nullable', 'numeric', 'min:0'],
            'distance_factor' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            if (! $this->boolean('active')) {
                return;
            }

            if (! SchemeConfig::isReadyForActivation($this->all())) {
                $validator->errors()->add(
                    'active',
                    __('rml.admin.settings.scheme_activation_incomplete'),
                );
            }
        });
    }
}
