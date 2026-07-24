<?php

namespace App\Http\Requests\Auditor;

use Illuminate\Foundation\Http\FormRequest;

class RecommendAcceptRequest extends FormRequest
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
            'audit_notes' => ['nullable', 'string', 'max:5000'],
            'checklist' => ['nullable', 'array'],
            'checklist.*.id' => ['nullable', 'integer'],
            'checklist.*.checked' => ['nullable', 'boolean'],
            'checklist.*.notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
