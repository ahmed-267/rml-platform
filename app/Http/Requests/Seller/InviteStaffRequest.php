<?php

namespace App\Http\Requests\Seller;

use App\Support\Permissions;
use Illuminate\Foundation\Http\FormRequest;

class InviteStaffRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(Permissions::MANAGE_SELLER_STAFF) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'email' => __('rml.seller.staff.invite_email'),
        ];
    }
}
