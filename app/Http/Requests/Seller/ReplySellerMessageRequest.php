<?php

namespace App\Http\Requests\Seller;

use App\Support\Permissions;
use Illuminate\Foundation\Http\FormRequest;

class ReplySellerMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(Permissions::MESSAGE_SUPPORT) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:5000'],
        ];
    }
}
