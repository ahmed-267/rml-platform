<?php

namespace App\Http\Requests\Auditor;

use App\Support\RejectionReasons;
use Illuminate\Foundation\Http\FormRequest;

class RecommendRejectRequest extends FormRequest
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
        return array_merge(
            RejectionReasons::validationRules(
                RejectionReasons::CONTEXT_LEADS,
                'reason_code',
                'comment',
            ),
            [
                'audit_notes' => ['nullable', 'string', 'max:5000'],
                'checklist' => ['nullable', 'array'],
                'checklist.*.id' => ['nullable', 'integer'],
                'checklist.*.checked' => ['nullable', 'boolean'],
                'checklist.*.notes' => ['nullable', 'string', 'max:1000'],
            ],
        );
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'reason_code' => __('rml.rejection.reason'),
            'comment' => __('rml.rejection.comment'),
        ];
    }
}
