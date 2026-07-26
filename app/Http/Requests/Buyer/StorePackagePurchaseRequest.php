<?php

namespace App\Http\Requests\Buyer;

use App\Enums\PaymentMethod;
use App\Support\Permissions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePackagePurchaseRequest extends FormRequest
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
            'type' => ['required', 'string', Rule::in(['prebuilt', 'custom', 'mixed_zone'])],
            'package_id' => ['required_if:type,prebuilt', 'nullable', 'integer', 'exists:lead_packages,id'],
            'payment_method' => ['required', 'string', Rule::in(PaymentMethod::values())],
            'lead_count' => ['required_if:type,custom', 'nullable', 'integer', 'min:1', 'max:50'],
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
            'type' => __('rml.buyer.packages.package_name'),
            'package_id' => __('rml.buyer.packages.package_name'),
            'payment_method' => __('rml.buyer.leads.payment_method'),
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
