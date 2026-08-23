<?php

namespace App\Http\Requests\Catastro;

use App\Enums\CatastroVerificationStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReviewCatastroSnapshotRequest extends FormRequest
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
            'status' => ['required', 'string', Rule::in(CatastroVerificationStatus::values())],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
