<?php

namespace App\Http\Requests\Buyer;

use App\Enums\PaymentMethod;
use App\Support\Permissions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLeadPurchaseRequest extends FormRequest
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
            'lead_ids' => ['required', 'array', 'min:1'],
            'lead_ids.*' => ['integer', 'exists:leads,id'],
            'payment_method' => ['required', 'string', Rule::in(PaymentMethod::values())],
        ];
    }
}
