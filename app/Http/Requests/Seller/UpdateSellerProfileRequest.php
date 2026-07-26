<?php

namespace App\Http\Requests\Seller;

use App\Rules\PhoneNumber;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSellerProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $userId = $this->user()?->id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($userId)],
            'phone' => ['nullable', 'string', 'max:50', new PhoneNumber],
            'locale' => ['nullable', 'string', 'in:en,es,fr'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => __('rml.seller.profile.name'),
            'email' => __('rml.seller.profile.email'),
            'phone' => __('rml.seller.profile.phone'),
            'locale' => __('rml.common.language'),
        ];
    }
}
