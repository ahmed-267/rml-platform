<?php

namespace App\Http\Requests\Admin;

use App\Enums\ApprovalStatus;
use App\Enums\CompanyType;
use App\Enums\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAdminSaleRequest extends FormRequest
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
        $type = $this->string('type')->toString();

        return [
            'type' => ['required', 'string', Rule::in(['lead', 'package'])],
            'buyer_company_id' => [
                'required',
                'integer',
                Rule::exists('companies', 'id')
                    ->where('type', CompanyType::Buyer->value)
                    ->where('approval_status', ApprovalStatus::Approved->value),
            ],
            'buyer_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'payment_method' => ['required', 'string', Rule::in(PaymentMethod::values())],
            'lead_ids' => [$type === 'lead' ? 'required' : 'nullable', 'array', 'min:1'],
            'lead_ids.*' => ['integer', 'distinct', 'exists:leads,id'],
            'package_id' => [$type === 'package' ? 'required' : 'nullable', 'integer', 'exists:lead_packages,id'],
        ];
    }
}
