<?php

namespace App\Http\Requests\Auditor;

use App\Enums\MessageThreadCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAuditorMessageRequest extends FormRequest
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
        return [
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'min:2', 'max:5000'],
            'category' => ['nullable', Rule::in([
                MessageThreadCategory::Internal->value,
                MessageThreadCategory::AuditQuestion->value,
                MessageThreadCategory::EvidenceIssue->value,
                MessageThreadCategory::LeadReview->value,
            ])],
            'related_lead_id' => ['nullable', 'integer', 'exists:leads,id'],
        ];
    }
}
