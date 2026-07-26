<?php

namespace App\Http\Requests\Seller;

use App\Models\Lead;
use App\Models\SchemeField;
use App\Rules\PhoneNumber;
use App\Support\Countries;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreLeadRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if (! $user) {
            return false;
        }

        if ($this->filled('lead_id')) {
            $lead = Lead::query()->find($this->input('lead_id'));

            return $lead !== null && $user->can('update', $lead);
        }

        return $user->can('create', Lead::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $asDraft = $this->boolean('as_draft');

        return [
            'lead_id' => ['nullable', 'integer', 'exists:leads,id'],
            'as_draft' => ['sometimes', 'boolean'],
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
            'latitude' => ['nullable', 'numeric'],
            'longitude' => ['nullable', 'numeric'],
            'size_m2' => ['nullable', 'numeric', 'min:0'],
            'property_type' => [$asDraft ? 'nullable' : 'required', 'string', 'max:120'],
            'epc_rating' => ['nullable', 'string', 'max:20'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'metrics' => ['nullable', 'array'],
            'metrics.*' => ['nullable'],
            'evidence_photos' => ['nullable', 'array'],
            'evidence_photos.*' => ['file', 'mimes:jpg,jpeg,png,webp,heic', 'max:10240'],
            'evidence_video' => ['nullable', 'file', 'mimes:mp4,mov,webm', 'max:51200'],
            'evidence_agreement' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
            'evidence_eligibility' => ['nullable', 'array'],
            'evidence_eligibility.*' => ['file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
            'consent' => [$asDraft ? 'nullable' : 'accepted'],
            'evidence_genuine' => [$asDraft ? 'nullable' : 'accepted'],
            'info_accurate' => [$asDraft ? 'nullable' : 'accepted'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'scheme_id' => __('rml.seller.leads.scheme'),
            'zone_id' => __('rml.seller.common.zone'),
            'zone_code' => __('rml.seller.common.zone'),
            'customer_first_name' => __('rml.seller.leads.first_name'),
            'customer_last_name' => __('rml.seller.leads.last_name'),
            'customer_phone' => __('rml.seller.leads.phone'),
            'customer_whatsapp' => __('rml.seller.leads.whatsapp'),
            'customer_email' => __('rml.seller.leads.email'),
            'address_line_1' => __('rml.seller.leads.address_1'),
            'address_line_2' => __('rml.seller.leads.address_2'),
            'city' => __('rml.seller.leads.city'),
            'postcode' => __('rml.seller.leads.postcode'),
            'country' => __('rml.seller.leads.country'),
            'property_type' => __('rml.seller.leads.property_type'),
            'epc_rating' => __('rml.seller.leads.epc_rating'),
            'notes' => __('rml.seller.leads.notes'),
            'size_m2' => __('rml.seller.common.size'),
            'evidence_photos' => __('rml.seller.leads.evidence_photos'),
            'evidence_video' => __('rml.seller.leads.evidence_video'),
            'evidence_agreement' => __('rml.seller.leads.evidence_agreement'),
            'evidence_eligibility' => __('rml.seller.leads.evidence_eligibility'),
            'consent' => __('rml.seller.leads.decl_consent'),
            'evidence_genuine' => __('rml.seller.leads.decl_genuine'),
            'info_accurate' => __('rml.seller.leads.decl_accurate'),
            'as_draft' => __('rml.seller.leads.save_draft'),
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->boolean('as_draft') || $validator->errors()->isNotEmpty()) {
                return;
            }

            $schemeId = $this->integer('scheme_id');
            if ($schemeId <= 0) {
                return;
            }

            $metrics = is_array($this->input('metrics')) ? $this->input('metrics') : [];
            $requiredFields = SchemeField::query()
                ->where('scheme_id', $schemeId)
                ->where('active', true)
                ->where('required', true)
                ->get(['key', 'label']);

            foreach ($requiredFields as $field) {
                $key = $field->key;
                if ($key === 'zone') {
                    if (! $this->filled('zone_id') && blank($metrics['zone'] ?? null)) {
                        $validator->errors()->add('zone_id', __('rml.seller.leads.field_required', ['field' => $field->label]));
                    }

                    continue;
                }

                if ($key === 'notes') {
                    $value = $metrics['notes'] ?? $this->input('notes');
                } elseif ($key === 'epc_rating') {
                    $value = $metrics['epc_rating'] ?? $this->input('epc_rating');
                } elseif ($key === 'size_m2') {
                    $value = $metrics['size_m2'] ?? $this->input('size_m2');
                } elseif ($key === 'property_type') {
                    $value = $metrics['property_type'] ?? $this->input('property_type');
                } else {
                    $value = $metrics[$key] ?? null;
                }

                if ($value === null || $value === '' || $value === false) {
                    $validator->errors()->add(
                        in_array($key, ['notes', 'epc_rating', 'property_type'], true) ? $key : 'metrics.'.$key,
                        __('rml.seller.leads.field_required', ['field' => $field->label]),
                    );
                }
            }
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function leadPayload(): array
    {
        return [
            'lead_id' => $this->input('lead_id'),
            'scheme_id' => $this->integer('scheme_id'),
            'zone_id' => $this->input('zone_id'),
            'zone_code' => $this->input('zone_code'),
            'customer_first_name' => $this->input('customer_first_name') ?? '',
            'customer_last_name' => $this->input('customer_last_name') ?? '',
            'customer_phone' => $this->input('customer_phone') ?? '',
            'customer_whatsapp' => $this->input('customer_whatsapp'),
            'customer_email' => $this->input('customer_email'),
            'address_line_1' => $this->input('address_line_1'),
            'address_line_2' => $this->input('address_line_2'),
            'city' => $this->input('city'),
            'postcode' => $this->input('postcode'),
            'country' => $this->input('country', Countries::DEFAULT),
            'latitude' => $this->input('latitude'),
            'longitude' => $this->input('longitude'),
            'size_m2' => $this->input('size_m2'),
            'property_type' => $this->input('property_type'),
            'epc_rating' => $this->input('epc_rating'),
            'notes' => $this->input('notes'),
            'metrics' => $this->input('metrics', []),
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
}
