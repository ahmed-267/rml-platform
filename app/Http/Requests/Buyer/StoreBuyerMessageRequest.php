<?php

namespace App\Http\Requests\Buyer;

use App\Enums\MessageThreadCategory;
use App\Support\Permissions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBuyerMessageRequest extends FormRequest
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
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:5000'],
            'category' => [
                'nullable',
                'string',
                Rule::in([
                    MessageThreadCategory::BuyerIssue->value,
                    MessageThreadCategory::PaymentQuery->value,
                    MessageThreadCategory::Complaint->value,
                    MessageThreadCategory::Dispute->value,
                    MessageThreadCategory::Internal->value,
                ]),
            ],
            'related_lead_id' => ['nullable', 'integer', 'exists:leads,id'],
            'related_purchase_id' => ['nullable', 'integer', 'exists:purchases,id'],
        ];
    }
}
