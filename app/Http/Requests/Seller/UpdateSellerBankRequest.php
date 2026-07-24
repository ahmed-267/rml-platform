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
}
