<?php

namespace App\Http\Requests\Admin;

use App\Rules\PhoneNumber;
use App\Services\Admin\AdminLeadCreationService;
use App\Support\Countries;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreAdminLeadRequest extends FormRequest
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
        $asDraft = $this->boolean('as_draft');
        $source = $this->string('lead_source')->toString();

        $sellerCompanyRules = ['nullable', 'integer', 'exists:companies,id'];
        $sellerAgentRules = ['nullable', 'integer', 'exists:users,id'];

        if (! $asDraft && $source === AdminLeadCreationService::SOURCE_SELLER_COMPANY) {
            $sellerCompanyRules = ['required', 'integer', 'exists:companies,id'];
        }

        if (! $asDraft && $source === AdminLeadCreationService::SOURCE_SELLER_AGENT) {
            $sellerAgentRules = ['required', 'integer', 'exists:users,id'];
        }

        return [
            'as_draft' => ['sometimes', 'boolean'],
            'lead_source' => [
                'required',
                'string',
                Rule::in([
                    AdminLeadCreationService::SOURCE_SELLER_COMPANY,
                    AdminLeadCreationService::SOURCE_SELLER_AGENT,
                    AdminLeadCreationService::SOURCE_RML_INTERNAL,
                ]),
            ],
            'seller_company_id' => $sellerCompanyRules,
            'seller_user_id' => $sellerAgentRules,
            'scheme_id' => ['required', 'integer', 'exists:schemes,id'],
            'zone_id' => ['nullable', 'integer', 'exists:zones,id'],
            'zone_code' => ['nullable', 'string', 'max:50'],
            'customer_first_name' => [$asDraft ? 'nullable' : 'required', 'string', 'max:120'],
            'customer_last_name' => [$asDraft ? 'nullable' : 'required', 'string', 'max:120'],
            'customer_phone' => [$asDraft ? 'nullable' : 'required', 'string', 'max:50', new PhoneNumber],
            'customer_whatsapp' => ['nullable', 'string', 'max:50', new PhoneNumber],
            'customer_email' => [$asDraft ? 'nullable' : 'required', 'email', 'max:255'],
            'address_line_1' => [$asDraft ? 'nullable' : 'required', 'string', 'max:255'],
            'address_line_2' => ['nullable', 'string', 'max:255'],
            'city' => [$asDraft ? 'nullable' : 'required', 'string', 'max:120'],
            'postcode' => [$asDraft ? 'nullable' : 'required', 'string', 'max:20'],
            'country' => [
                $asDraft ? 'nullable' : 'required',
                'string',
                'size:2',
                Rule::in(Countries::codes()),
            ],
            'cadastral_reference' => ['nullable', 'string', 'max:40'],
            'latitude' => ['nullable', 'numeric'],
            'longitude' => ['nullable', 'numeric'],
            'size_m2' => ['nullable', 'numeric', 'min:0'],
            'property_type' => [$asDraft ? 'nullable' : 'required', 'string', 'max:120'],
            'occupancy_type' => ['nullable', 'string', 'max:120'],
            'epc_rating' => ['nullable', 'string', 'max:20'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'buying_price' => ['nullable', 'numeric', 'min:0'],
            'metrics' => ['nullable', 'array'],
            'evidence_photos' => ['nullable', 'array'],
            'evidence_photos.*' => ['file', 'mimes:jpg,jpeg,png,webp,heic', 'max:10240'],
            'evidence_video' => ['nullable', 'file', 'mimes:mp4,mov,webm', 'max:51200'],
            'evidence_agreement' => [
                $asDraft ? 'nullable' : 'required',
                'file',
                'mimes:pdf,jpg,jpeg,png',
                'max:10240',
            ],
            'evidence_eligibility' => ['nullable', 'array'],
            'evidence_eligibility.*' => ['file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->boolean('as_draft')) {
                return;
            }

            $source = $this->string('lead_source')->toString();

            if ($source === AdminLeadCreationService::SOURCE_SELLER_COMPANY
                && ! $this->filled('seller_company_id')
            ) {
                $validator->errors()->add(
                    'seller_company_id',
                    __('rml.admin.leads.create.seller_company_required'),
                );
            }

            if ($source === AdminLeadCreationService::SOURCE_SELLER_AGENT
                && ! $this->filled('seller_user_id')
            ) {
                $validator->errors()->add(
                    'seller_user_id',
                    __('rml.admin.leads.create.seller_agent_required'),
                );
            }
        });
    }
}
