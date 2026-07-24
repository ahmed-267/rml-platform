<?php

namespace App\Http\Requests\Seller;

use Illuminate\Foundation\Http\FormRequest;

class UpdateLeadEvidenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        $lead = $this->route('lead');

        return $lead && ($this->user()?->can('update', $lead) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'evidence_photos' => ['nullable', 'array'],
            'evidence_photos.*' => ['file', 'mimes:jpg,jpeg,png,webp,heic', 'max:10240'],
            'evidence_video' => ['nullable', 'file', 'mimes:mp4,mov,webm', 'max:51200'],
            'evidence_agreement' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
            'evidence_eligibility' => ['nullable', 'array'],
            'evidence_eligibility.*' => ['file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function uploadedEvidence(): array
    {
        return [
            'photo' => $this->file('evidence_photos', []),
            'video' => $this->file('evidence_video'),
            'signed_homeowner_agreement' => $this->file('evidence_agreement'),
            'eligibility_document' => $this->file('evidence_eligibility', []),
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $hasAny = $this->hasFile('evidence_photos')
                || $this->hasFile('evidence_video')
                || $this->hasFile('evidence_agreement')
                || $this->hasFile('evidence_eligibility');

            if (! $hasAny) {
                $validator->errors()->add('evidence_photos', 'At least one evidence file is required.');
            }
        });
    }
}
