<?php

namespace App\Http\Requests\Seller;

use App\Rules\PhoneNumber;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class AcceptStaffInvitationRequest extends FormRequest
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
        return [
            'name' => ['required', 'string', 'max:255'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'phone' => ['nullable', 'string', 'max:50', new PhoneNumber],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => __('rml.auth.full_name'),
            'password' => __('rml.auth.password'),
            'phone' => __('rml.auth.phone'),
        ];
    }

    /**
     * @return array{name: string, password: string, phone: string|null}
     */
    public function userPayload(): array
    {
        return [
            'name' => $this->string('name')->toString(),
            'password' => $this->string('password')->toString(),
            'phone' => $this->input('phone'),
        ];
    }
}
