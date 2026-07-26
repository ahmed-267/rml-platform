<?php

namespace App\Http\Requests\Admin;

use App\Rules\PhoneNumber;
use App\Support\Permissions;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSellerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(Permissions::MANAGE_SELLERS)
            || $this->user()?->hasRole('super_admin');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50', new PhoneNumber],
            'locale' => ['nullable', 'string', 'in:en,es,fr'],
            'commission_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'bank_account_iban' => ['nullable', 'string', 'max:64'],
            'bank_account_name' => ['nullable', 'string', 'max:255'],
            'payout_method' => ['nullable', 'string', 'max:50'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'company_email' => ['nullable', 'email', 'max:255'],
            'company_phone' => ['nullable', 'string', 'max:50', new PhoneNumber],
            'company_address' => ['nullable', 'string', 'max:500'],
            'company_city' => ['nullable', 'string', 'max:120'],
            'company_postcode' => ['nullable', 'string', 'max:20'],
            'company_country' => ['nullable', 'string', 'max:120'],
            'company_notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => __('rml.admin.common.name'),
            'email' => __('rml.admin.common.email'),
            'phone' => __('rml.admin.common.phone'),
            'locale' => __('rml.common.language'),
            'commission_rate' => __('rml.seller.staff.commission_rate'),
            'bank_account_iban' => __('rml.seller.payments.iban'),
            'bank_account_name' => __('rml.seller.payments.account_name'),
            'payout_method' => __('rml.seller.payments.payout_method'),
            'company_name' => __('rml.auth.company_name'),
            'company_email' => __('rml.admin.common.email'),
            'company_phone' => __('rml.admin.common.phone'),
            'company_address' => __('rml.auth.company_address'),
            'company_city' => __('rml.admin.common.city'),
            'company_postcode' => __('rml.auth.postcode'),
            'company_country' => __('rml.auth.country'),
            'company_notes' => __('rml.seller.leads.notes'),
        ];
    }
}
