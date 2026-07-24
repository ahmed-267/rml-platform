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
            'min_size' => ['nullable', 'numeric', 'min:0'],
            'max_size' => ['nullable', 'numeric', 'min:0'],
            'min_distance' => ['nullable', 'numeric', 'min:0'],
            'max_distance' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
