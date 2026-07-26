<?php

namespace App\Http\Requests\Buyer;

use App\Support\Permissions;
use Illuminate\Foundation\Http\FormRequest;

class PreviewPackageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(Permissions::BUY_LEADS) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'lead_count' => ['required', 'integer', 'min:1', 'max:50'],
            'scheme_id' => ['nullable', 'integer', 'exists:schemes,id'],
            'zone_codes' => ['nullable', 'array'],
            'zone_codes.*' => ['string', 'max:10'],
            'min_size' => ['nullable', 'numeric', 'min:0', 'max:10000'],
            'max_size' => ['nullable', 'numeric', 'min:0', 'max:10000'],
            'min_distance' => ['nullable', 'numeric', 'min:0', 'max:500'],
            'max_distance' => ['nullable', 'numeric', 'min:0', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'lead_count' => __('rml.buyer.packages.lead_count'),
            'scheme_id' => __('rml.auditor.common.scheme'),
            'zone_codes' => __('rml.buyer.packages.zones'),
            'min_size' => __('rml.buyer.leads.min_size'),
            'max_size' => __('rml.buyer.leads.max_size'),
            'min_distance' => __('rml.buyer.leads.min_distance'),
            'max_distance' => __('rml.buyer.leads.max_distance'),
        ];
    }
}
