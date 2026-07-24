<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class SellerRegistrationRequest extends FormRequest
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
        $isCompany = $this->input('account_type') === 'company';

        return [
            'account_type' => ['required', 'in:company,individual'],
            'company_name' => [$isCompany ? 'required' : 'nullable', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['required', 'string', 'max:50'],
            'whatsapp' => ['nullable', 'string', 'max:50'],
            'postcode' => ['required', 'string', 'max:20'],
            'address' => ['required', 'string', 'max:500'],
            'city' => ['required', 'string', 'max:120'],
            'country' => ['nullable', 'string', 'size:2'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'agreement' => ['accepted'],
            'gdpr' => ['accepted'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function registrationPayload(): array
    {
        return [
            'account_type' => $this->string('account_type')->toString(),
            'company_name' => $this->input('company_name'),
            'name' => $this->string('name')->toString(),
            'email' => $this->string('email')->toString(),
            'password' => $this->string('password')->toString(),
            'phone' => $this->input('phone'),
            'whatsapp' => $this->input('whatsapp') ?: $this->input('phone'),
            'postcode' => $this->input('postcode'),
            'address' => $this->input('address'),
            'city' => $this->input('city'),
            'country' => $this->input('country', 'ES'),
        ];
    }
}
