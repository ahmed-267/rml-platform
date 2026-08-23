<?php

namespace App\Http\Requests\Admin;

use App\Enums\LeadStatus;
use App\Support\Permissions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateLeadsPackagesSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(Permissions::MANAGE_SETTINGS)
            || $this->user()?->hasRole('super_admin');
    }

    protected function prepareForValidation(): void
    {
        $boolKeys = [
            'require_internal_audit',
            'allow_evidence_later',
            'allow_seller_company_leads',
            'allow_seller_agent_leads',
            'rml_internal_creates_payouts',
            'allow_rejected_resubmit',
            'allow_mixed_scheme',
            'allow_without_buyer',
            'reservation_lock',
            'allow_manual_creation',
            'allow_installer_based_creation',
            'allow_without_installer',
            'allow_mixed_zone',
            'release_leads_on_expiry',
            'allow_manual_override',
            'pricing_allow_manual_override',
            'company_payouts',
            'agent_payouts',
            'staff_payout_to_company',
            'rml_internal_payouts',
            'payout_allow_manual_override',
            'return_leads_to_listed',
            'return_packages_to_available',
            'allow_edit_reserved_package',
            'catastro_enabled',
        ];

        foreach ($boolKeys as $key) {
            if ($this->has($key)) {
                $this->merge([
                    $key => filter_var($this->input($key), FILTER_VALIDATE_BOOLEAN),
                ]);
            }
        }

        if ($this->has('schemes') && is_string($this->input('schemes'))) {
            $decoded = json_decode($this->input('schemes'), true);
            if (is_array($decoded)) {
                $this->merge(['schemes' => $decoded]);
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // Leads
            'default_status_after_submission' => [
                'nullable',
                'string',
                Rule::in([
                    LeadStatus::PendingValidation->value,
                    LeadStatus::PendingEvidence->value,
                    LeadStatus::Listed->value,
                ]),
            ],
            'require_internal_audit' => ['nullable', 'boolean'],
            'allow_evidence_later' => ['nullable', 'boolean'],
            'allow_seller_company_leads' => ['nullable', 'boolean'],
            'allow_seller_agent_leads' => ['nullable', 'boolean'],
            'rml_internal_creates_payouts' => ['nullable', 'boolean'],
            'min_property_area_m2' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'max_property_area_m2' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'reservation_hours' => ['nullable', 'integer', 'min:1', 'max:8760'],
            'sale_lock_hours' => ['nullable', 'integer', 'min:1', 'max:8760'],
            'allow_rejected_resubmit' => ['nullable', 'boolean'],
            'duplicate_detection' => ['nullable', 'string', Rule::in(['off', 'soft', 'strict'])],

            // Packages
            'default_status' => ['nullable', 'string', Rule::in(['draft', 'available'])],
            'allow_mixed_scheme' => ['nullable', 'boolean'],
            'allow_without_buyer' => ['nullable', 'boolean'],
            'reservation_lock' => ['nullable', 'boolean'],
            'expiry_days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'allow_manual_creation' => ['nullable', 'boolean'],
            'allow_installer_based_creation' => ['nullable', 'boolean'],
            'allow_without_installer' => ['nullable', 'boolean'],
            'allow_mixed_zone' => ['nullable', 'boolean'],
            'min_leads' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'max_leads' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'min_area_m2' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'max_area_m2' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'default_search_radius_km' => ['nullable', 'numeric', 'min:1', 'max:500'],
            'max_lead_distance_km' => ['nullable', 'numeric', 'min:1', 'max:1000'],
            'release_leads_on_expiry' => ['nullable', 'boolean'],
            'package_reservation_hours' => ['nullable', 'integer', 'min:1', 'max:8760'],

            // Pricing
            'calculation_method' => ['nullable', 'string', Rule::in(['fixed', 'per_m2'])],
            'fixed_selling_price' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'minimum_selling_price' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'maximum_discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'pricing_allow_manual_override' => ['nullable', 'boolean'],
            'package_discount_percent_max' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'tax_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],

            // Payouts
            'payout_method' => ['nullable', 'string', Rule::in(['fixed', 'per_m2', 'percentage'])],
            'payout_fixed_amount' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'payout_rate_per_m2' => ['nullable', 'numeric', 'min:0', 'max:10000'],
            'payout_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'rml_internal_payouts' => ['nullable', 'boolean'],
            'company_payouts' => ['nullable', 'boolean'],
            'agent_payouts' => ['nullable', 'boolean'],
            'staff_payout_to_company' => ['nullable', 'boolean'],
            'payout_allow_manual_override' => ['nullable', 'boolean'],

            // Reservations
            'lead_reservation_hours' => ['nullable', 'integer', 'min:1', 'max:8760'],
            'abandoned_expires_hours' => ['nullable', 'integer', 'min:1', 'max:8760'],
            'return_leads_to_listed' => ['nullable', 'boolean'],
            'return_packages_to_available' => ['nullable', 'boolean'],
            'allow_edit_reserved_package' => ['nullable', 'boolean'],
            'on_payment_fail' => ['nullable', 'string', Rule::in(['release', 'keep_locked'])],

            // Catastro
            'catastro_enabled' => ['nullable', 'boolean'],

            // Per-scheme requirement overrides
            'schemes' => ['nullable', 'array'],
            'schemes.*.id' => ['required_with:schemes', 'integer', 'exists:schemes,id'],
            'schemes.*.require_internal_audit' => ['nullable', 'boolean'],
            'schemes.*.require_homeowner_agreement' => ['nullable', 'boolean'],
            'schemes.*.require_epc' => ['nullable', 'boolean'],
            'schemes.*.require_photos' => ['nullable', 'boolean'],
            'schemes.*.min_measurement' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'schemes.*.max_measurement' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $minArea = $this->input('min_property_area_m2');
            $maxArea = $this->input('max_property_area_m2');
            if ($minArea !== null && $maxArea !== null && (float) $minArea > (float) $maxArea) {
                $validator->errors()->add(
                    'min_property_area_m2',
                    __('rml.admin.settings.leads_packages.min_max_area_invalid'),
                );
            }

            $minLeads = $this->input('min_leads');
            $maxLeads = $this->input('max_leads');
            if ($minLeads !== null && $maxLeads !== null && (int) $minLeads > (int) $maxLeads) {
                $validator->errors()->add(
                    'min_leads',
                    __('rml.admin.settings.leads_packages.min_max_leads_invalid'),
                );
            }

            $pkgMinArea = $this->input('min_area_m2');
            $pkgMaxArea = $this->input('max_area_m2');
            if ($pkgMinArea !== null && $pkgMaxArea !== null && (float) $pkgMinArea > (float) $pkgMaxArea) {
                $validator->errors()->add(
                    'min_area_m2',
                    __('rml.admin.settings.leads_packages.min_max_package_area_invalid'),
                );
            }

            $radius = $this->input('default_search_radius_km');
            $maxDistance = $this->input('max_lead_distance_km');
            if ($radius !== null && $maxDistance !== null && (float) $radius > (float) $maxDistance) {
                $validator->errors()->add(
                    'default_search_radius_km',
                    __('rml.admin.settings.leads_packages.radius_exceeds_max_distance'),
                );
            }

            foreach ($this->input('schemes', []) as $index => $scheme) {
                if (! is_array($scheme)) {
                    continue;
                }
                $min = $scheme['min_measurement'] ?? null;
                $max = $scheme['max_measurement'] ?? null;
                if ($min !== null && $max !== null && (float) $min > (float) $max) {
                    $validator->errors()->add(
                        "schemes.$index.min_measurement",
                        __('rml.admin.settings.leads_packages.min_max_scheme_measurement_invalid'),
                    );
                }
            }
        });
    }
}
