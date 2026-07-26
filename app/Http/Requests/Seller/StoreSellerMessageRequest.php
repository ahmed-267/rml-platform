<?php

namespace App\Http\Requests\Seller;

use App\Enums\MessageThreadCategory;
use App\Support\Permissions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSellerMessageRequest extends FormRequest
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
                    MessageThreadCategory::SellerIssue->value,
                    MessageThreadCategory::PaymentQuery->value,
                    MessageThreadCategory::InformationRequest->value,
                    MessageThreadCategory::Internal->value,
                    MessageThreadCategory::Complaint->value,
                ]),
            ],
            'related_lead_id' => ['nullable', 'integer', 'exists:leads,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'subject' => __('rml.seller.messages.subject'),
            'body' => __('rml.seller.messages.body'),
            'category' => __('rml.seller.messages.category'),
            'related_lead_id' => __('rml.seller.messages.related_lead'),
        ];
    }
}
