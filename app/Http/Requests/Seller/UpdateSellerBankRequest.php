<?php

namespace App\Http\Requests\Seller;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSellerBankRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->sellerProfile !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'bank_account_iban' => ['nullable', 'string', 'max:64'],
            'bank_account_name' => ['nullable', 'string', 'max:255'],
            'payout_method' => ['nullable', 'string', 'max:50'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'bank_account_iban' => __('rml.seller.payments.iban'),
            'bank_account_name' => __('rml.seller.payments.account_name'),
            'payout_method' => __('rml.seller.payments.payout_method'),
        ];
    }
}
