<?php

namespace Database\Seeders;

use App\Enums\CommissionAppliesTo;
use App\Enums\SchemeFieldType;
use App\Enums\TemplateDocumentType;
use App\Models\AuditChecklistItem;
use App\Models\AuditLog;
use App\Models\CommissionRule;
use App\Models\PricingRule;
use App\Models\Scheme;
use App\Models\SchemeField;
use App\Models\TemplateDocument;
use App\Models\TemplateVersion;
use App\Models\User;
use App\Models\Zone;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class SchemeSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()->where('email', 'admin@rml.test')->first();

        Cache::forever('rml.platform_settings', [
            'support_email' => 'support@rml-energy.test',
            'support_phone' => '+34 910 000 221',
            'default_locale' => 'en',
            'default_currency' => 'EUR',
            'company_name' => 'RML Energy Saving',
            'bank_transfer_instructions' => "RML Energy Saving\nIBAN: ES91 2100 0418 4502 0005 1332\nBIC: CAIXESBBXXX\nUse the invoice payment reference as the transfer concept.",
        ]);

        $insulation = $this->seedScheme(
            'Insulation',
            'insulation',
            'Cavity wall, loft, and external insulation scheme leads for Spanish residential properties.',
            1,
            [
                'lead_type' => 'building_envelope',
                'pricing_basis' => 'zone_m2',
                'pricing_basis_explanation' => 'Insulation leads are priced primarily by climate/energy zone and insulated area (m²). D zones are lower value; E zones are generally higher.',
                'required_inputs' => [
                    'zone',
                    'area_m2',
                    'insulation_type',
                    'photos',
                    'homeowner_agreement',
                    'energy_certificate_status',
                    'distance',
                ],
                'evidence' => [
                    'photos',
                    'homeowner_agreement',
                    'energy_certificate_status',
                    'property_details',
                ],
                'eligibility' => [
                    'conditions' => 'Owner-occupied or landlord properties with incomplete insulation and valid EPC pathway.',
                    'income' => 'Means-tested where local grant rules apply; otherwise open to eligible homeowners.',
                    'property_notes' => 'Suitable for cavity wall, loft, and external wall insulation measures.',
                    'required_documents' => "Signed homeowner agreement\nProperty photos (external + loft/cavity access)\nEligibility / EPC evidence",
                ],
                'requirements' => [
                    'zone_required' => true,
                    'area_required' => true,
                    'distance_required' => true,
                    'constraints' => 'Access constraints, scaffolding needs, and occupied-property notes must be recorded.',
                    'other' => 'Homeowner agreement and eligibility evidence required before listing.',
                ],
                'pricing_notes' => 'Zone-based price per m². D1 is lower-value; E1/E2 are higher-value. Distance adjusts quality/value.',
                'algorithm' => [
                    'formula' => 'base_price + (price_per_m2 × size × zone_factor × size_factor) − distance_adjustment',
                    'notes' => 'Distance factor reduces effective price for far leads; zone factor uplifts E zones.',
                ],
                'technical_details' => "Cavity wall insulation: clear cavity, no damp history.\nLoft insulation: loft hatch access and depth verification.\nExternal insulation: facade condition and planning constraints.",
            ],
        );

        $glazing = $this->seedScheme(
            'Double Glazing',
            'double-glazing',
            'Window and glazing upgrade leads for residential properties seeking energy-efficient replacements.',
            2,
            [
                'lead_type' => 'windows_envelope',
                'pricing_basis' => 'window_area_count',
                'pricing_basis_explanation' => 'Double glazing is priced mainly by window count and/or glazing area. Climate zone is context only, not the primary price driver.',
                'required_inputs' => [
                    'window_count',
                    'glazing_area',
                    'current_glazing_type',
                    'photos',
                    'homeowner_agreement',
                    'property_type',
                    'distance',
                    'zone',
                ],
                'evidence' => [
                    'photos',
                    'homeowner_agreement',
                    'property_details',
                ],
                'pricing_factors' => [
                    'price_per_window' => 85.00,
                    'window_count_factor' => 1.0,
                    'glazing_area_factor' => 1.0,
                    'quality_factor' => 1.1,
                ],
                'eligibility' => [
                    'conditions' => 'Properties with single glazing or failing double units eligible for upgrade schemes.',
                    'income' => 'Optional local income criteria depending on grant programme.',
                    'property_notes' => 'Window count or glazed area required; frame type and orientation helpful.',
                    'required_documents' => "Property photos of existing windows\nSigned homeowner agreement\nProperty / address details",
                ],
                'requirements' => [
                    'zone_required' => false,
                    'area_required' => true,
                    'distance_required' => true,
                    'constraints' => 'Listed building or conservation area restrictions must be flagged.',
                    'other' => 'Window count or m² area required with homeowner agreement. Zone is contextual only.',
                ],
                'pricing_notes' => 'From €6.50/m² with window count / glazing area factors. Zone factor is optional context.',
                'algorithm' => [
                    'formula' => '(price_per_m2 × glazed_area_m2 × glazing_area_factor) + (price_per_window × window_count × window_count_factor)',
                    'notes' => 'Zone/context factor is optional and not the primary pricing basis.',
                ],
                'technical_details' => "Record frame material, opening type, and failed seals.\nPhotos of each elevation preferred.\nInclude approximate window count when area is estimated.",
            ],
        );

        $heatPumps = $this->seedScheme(
            'Heat Pumps',
            'heat-pumps',
            'Air-source and ground-source heat pump installation leads for homes replacing fossil heating systems.',
            3,
            [
                'lead_type' => 'heating_system',
                'pricing_basis' => 'per_lead_kw',
                'pricing_basis_explanation' => 'Heat pump leads are priced by lead quality, estimated kW, system type, and feasibility — not primarily by climate zone.',
                'required_inputs' => [
                    'current_heating_system',
                    'proposed_heat_pump_type',
                    'property_size',
                    'estimated_kw',
                    'outdoor_unit_feasibility',
                    'electrical_supply_notes',
                    'photos',
                    'homeowner_agreement',
                    'technical_memory_status',
                    'distance',
                    'zone',
                ],
                'evidence' => [
                    'photos',
                    'homeowner_agreement',
                    'technical_documents',
                    'property_details',
                ],
                'pricing_factors' => [
                    'kw_factor' => 1.0,
                    'feasibility_factor' => 1.0,
                    'system_type_factor' => 1.15,
                ],
                'eligibility' => [
                    'conditions' => 'Properties with suitable outdoor unit space and compatible heating distribution.',
                    'income' => 'Grant-linked income checks may apply for funded installations.',
                    'property_notes' => 'Property type, existing heating system, and heat-loss notes required.',
                    'required_documents' => "Heating system photos/documents\nProperty photos\nEligibility evidence\nSigned homeowner agreement",
                ],
                'requirements' => [
                    'zone_required' => false,
                    'area_required' => true,
                    'distance_required' => true,
                    'constraints' => 'Outdoor unit placement, noise, and electrical capacity constraints.',
                    'other' => 'Property type and heating system details required before audit. Zone is contextual only.',
                ],
                'pricing_notes' => 'From €8.00/m² or base lead price with kW / feasibility / system type factors. Zone is optional context.',
                'algorithm' => [
                    'formula' => 'base_lead_price × kw_factor × feasibility_factor × system_type_factor × distance_factor',
                    'notes' => 'Heat-loss complexity and outdoor feasibility adjust selling price after audit.',
                ],
                'technical_details' => "Capture existing boiler/fuel type and emitter type (radiators / UFH / fan coils).\nNote outdoor clearances for ASHP.\nGround-source requires garden access notes.\nProposed types: aerothermal, geothermal, hydrothermal.",
            ],
        );

        $this->seedInsulationFields($insulation);
        $this->seedGlazingFields($glazing);
        $this->seedHeatPumpFields($heatPumps);

        $zones = [
            ['code' => 'D1', 'name' => 'Zone D1', 'price' => 3.00, 'sort' => 1],
            ['code' => 'D2', 'name' => 'Zone D2', 'price' => 4.00, 'sort' => 2],
            ['code' => 'E1', 'name' => 'Zone E1', 'price' => 4.00, 'sort' => 3],
            ['code' => 'E2', 'name' => 'Zone E2', 'price' => 5.00, 'sort' => 4],
        ];

        // Context zones exist for every scheme; insulation also has zone pricing rules.
        foreach ([$insulation, $glazing, $heatPumps] as $scheme) {
            foreach ($zones as $zoneData) {
                Zone::query()->updateOrCreate(
                    ['scheme_id' => $scheme->id, 'code' => $zoneData['code']],
                    [
                        'name' => $zoneData['name'],
                        'description' => $scheme->name.' zone '.$zoneData['code'],
                        'active' => true,
                        'sort_order' => $zoneData['sort'],
                    ],
                );
            }
        }

        foreach ($zones as $zoneData) {
            $zone = Zone::query()
                ->where('scheme_id', $insulation->id)
                ->where('code', $zoneData['code'])
                ->firstOrFail();

            PricingRule::query()->updateOrCreate(
                ['scheme_id' => $insulation->id, 'zone_id' => $zone->id],
                [
                    'price_per_m2' => $zoneData['price'],
                    'basic_price' => 3.00,
                    'zone_factor' => match ($zoneData['code']) {
                        'D1' => 1.0,
                        'D2' => round(4.0 / 3.0, 4),
                        'E1' => round(4.0 / 3.0, 4),
                        'E2' => round(5.0 / 3.0, 4),
                        default => 1.0,
                    },
                    'size_factor' => 1.25,
                    'distance_factor' => 1.0,
                    'active' => true,
                    'effective_from' => now()->toDateString(),
                ],
            );
        }

        foreach ([
            [$glazing, 6.50],
            [$heatPumps, 8.00],
        ] as [$scheme, $pricePerM2]) {
            PricingRule::query()->updateOrCreate(
                ['scheme_id' => $scheme->id, 'zone_id' => null],
                [
                    'price_per_m2' => $pricePerM2,
                    'basic_price' => $pricePerM2,
                    'zone_factor' => 1.0,
                    'size_factor' => 1.0,
                    'distance_factor' => 1.0,
                    'active' => true,
                    'effective_from' => now()->toDateString(),
                ],
            );
        }

        $commissionRules = [
            [
                'name' => 'Seller Company Default',
                'applies_to' => CommissionAppliesTo::SellerCompany,
                'percentage' => 10.00,
                'notes' => 'Due after lead sold and buyer payment confirmed.',
                'active' => true,
            ],
            [
                'name' => 'Seller Staff Default',
                'applies_to' => CommissionAppliesTo::SellerStaff,
                'percentage' => 3.00,
                'notes' => 'Due after lead sold and buyer payment confirmed.',
                'active' => true,
            ],
            [
                'name' => 'Individual Agent Default',
                'applies_to' => CommissionAppliesTo::IndividualAgent,
                'percentage' => 7.00,
                'notes' => 'Due after lead sold and buyer payment confirmed.',
                'active' => true,
            ],
        ];

        foreach ($commissionRules as $rule) {
            CommissionRule::query()->updateOrCreate(
                ['name' => $rule['name']],
                [
                    'applies_to' => $rule['applies_to'],
                    'percentage' => $rule['percentage'],
                    'active' => $rule['active'],
                    'notes' => $rule['notes'],
                ],
            );
        }

        CommissionRule::query()
            ->whereIn('name', ['Premium Seller Company', 'Legacy Agent Rule'])
            ->delete();

        // Deactivate legacy global checklist — schemes now own their audit items.
        AuditChecklistItem::query()
            ->whereNull('scheme_id')
            ->update(['active' => false]);

        $this->seedSchemeChecklist($insulation, [
            ['key' => 'customer_name_valid', 'label' => 'Customer name valid'],
            ['key' => 'phone_contact_valid', 'label' => 'Phone / contact valid'],
            ['key' => 'address_valid', 'label' => 'Address valid'],
            ['key' => 'zone_confirmed', 'label' => 'Zone confirmed'],
            ['key' => 'area_m2_plausible', 'label' => 'Area / m² provided and plausible'],
            ['key' => 'insulation_type_provided', 'label' => 'Insulation type provided'],
            ['key' => 'property_photos_uploaded', 'label' => 'Property photos uploaded'],
            ['key' => 'homeowner_agreement_signed', 'label' => 'Homeowner agreement uploaded / signed'],
            ['key' => 'epc_status_checked', 'label' => 'Energy certificate / EPC status checked if available'],
            ['key' => 'pricing_zone_verified', 'label' => 'Pricing / zone price verified'],
        ]);

        $this->seedSchemeChecklist($glazing, [
            ['key' => 'customer_name_valid', 'label' => 'Customer name valid'],
            ['key' => 'phone_contact_valid', 'label' => 'Phone / contact valid'],
            ['key' => 'address_valid', 'label' => 'Address valid'],
            ['key' => 'window_count_provided', 'label' => 'Window count provided'],
            ['key' => 'glazing_area_provided', 'label' => 'Glazing area provided or estimated'],
            ['key' => 'current_glazing_type_provided', 'label' => 'Current glazing type provided'],
            ['key' => 'window_photos_uploaded', 'label' => 'Window / property photos uploaded'],
            ['key' => 'homeowner_agreement_signed', 'label' => 'Homeowner agreement uploaded / signed'],
            ['key' => 'zone_context_checked', 'label' => 'Zone / context checked'],
            ['key' => 'pricing_basis_verified', 'label' => 'Pricing basis verified'],
        ]);

        $this->seedSchemeChecklist($heatPumps, [
            ['key' => 'customer_name_valid', 'label' => 'Customer name valid'],
            ['key' => 'phone_contact_valid', 'label' => 'Phone / contact valid'],
            ['key' => 'address_valid', 'label' => 'Address valid'],
            ['key' => 'current_heating_system_provided', 'label' => 'Current heating system provided'],
            ['key' => 'proposed_heat_pump_type_provided', 'label' => 'Proposed heat pump type provided'],
            ['key' => 'property_size_provided', 'label' => 'Property size provided'],
            ['key' => 'estimated_kw_provided', 'label' => 'Estimated kW provided or flagged for review'],
            ['key' => 'outdoor_unit_feasibility_checked', 'label' => 'Outdoor unit feasibility checked'],
            ['key' => 'electrical_supply_notes_checked', 'label' => 'Electrical supply notes checked'],
            ['key' => 'photos_uploaded', 'label' => 'Photos uploaded'],
            ['key' => 'homeowner_agreement_signed', 'label' => 'Homeowner agreement uploaded / signed'],
            ['key' => 'technical_notes_checked', 'label' => 'Technical notes checked'],
            ['key' => 'pricing_feasibility_verified', 'label' => 'Pricing / feasibility basis verified'],
        ]);

        $templateMeta = [
            TemplateDocumentType::SellerAgreement->value => [
                'name' => 'Seller Agreement',
                'description' => 'Standard terms for seller companies and agents submitting leads.',
                'content' => "Seller Agreement — RML Energy Saving\n\n1. Sellers submit accurate lead data and evidence.\n2. Leads remain subject to RML audit before listing.\n3. Commissions become due only after buyer payment confirmation.",
            ],
            TemplateDocumentType::BuyerAgreement->value => [
                'name' => 'Buyer Agreement',
                'description' => 'Marketplace purchase terms for buyer companies.',
                'content' => "Buyer Agreement — RML Energy Saving\n\n1. Purchased leads unlock after payment confirmation.\n2. Buyer must protect homeowner personal data.\n3. Disputes must be raised within the stated window.",
            ],
            TemplateDocumentType::HomeownerAgreement->value => [
                'name' => 'Homeowner Agreement',
                'description' => 'Consent and scheme participation agreement signed by homeowners.',
                'content' => "Homeowner Agreement — RML Energy Saving\n\n1. Homeowner authorises scheme assessment and contact.\n2. Evidence may be shared with auditors and buyers after sale.\n3. Homeowner may withdraw consent before sale.",
            ],
            TemplateDocumentType::EligibilityRequirements->value => [
                'name' => 'Eligibility Requirements',
                'description' => 'Summary of scheme eligibility checks used during audit.',
                'content' => "Eligibility Requirements\n\n- Valid identity and property address\n- Scheme-specific technical fit\n- Required evidence uploaded\n- No unresolved ownership disputes",
            ],
            TemplateDocumentType::SellerTerms->value => [
                'name' => 'Seller Terms',
                'description' => 'Platform terms of use for sellers.',
                'content' => 'Seller Terms of Use for the RML Energy Saving marketplace.',
            ],
            TemplateDocumentType::BuyerTerms->value => [
                'name' => 'Buyer Terms',
                'description' => 'Platform terms of use for buyers.',
                'content' => 'Buyer Terms of Use for the RML Energy Saving marketplace.',
            ],
            TemplateDocumentType::SellerGdpr->value => [
                'name' => 'Seller GDPR Notice',
                'description' => 'Data processing notice for seller accounts.',
                'content' => 'Seller GDPR / privacy notice covering lead submission and commission records.',
            ],
            TemplateDocumentType::BuyerGdpr->value => [
                'name' => 'Buyer GDPR Notice',
                'description' => 'Data processing notice for buyer accounts.',
                'content' => 'Buyer GDPR / privacy notice covering purchased lead data and retention.',
            ],
            TemplateDocumentType::HomeownerConsent->value => [
                'name' => 'Homeowner Consent',
                'description' => 'Consent text for processing homeowner personal data.',
                'content' => 'Homeowner consent for RML to process contact and property details for scheme matching.',
            ],
        ];

        foreach (TemplateDocumentType::cases() as $type) {
            $meta = $templateMeta[$type->value] ?? [
                'name' => Str::headline($type->value),
                'description' => 'Default '.$type->value.' template',
                'content' => 'Placeholder content for '.Str::headline($type->value).'.',
            ];

            $document = TemplateDocument::query()->updateOrCreate(
                ['type' => $type->value],
                [
                    'name' => $meta['name'],
                    'description' => $meta['description'],
                ],
            );

            $version = TemplateVersion::query()->updateOrCreate(
                ['template_document_id' => $document->id, 'version' => 1],
                [
                    'content' => $meta['content'],
                    'effective_from' => now()->toDateString(),
                    'created_by_user_id' => $admin?->id,
                    'active' => true,
                ],
            );

            $document->update(['active_version_id' => $version->id]);
        }

        if ($admin) {
            $this->seedSettingsAuditLogs($admin, $insulation, $glazing);
        }
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function seedScheme(string $name, string $slug, string $description, int $sort, array $metadata): Scheme
    {
        return Scheme::query()->updateOrCreate(
            ['slug' => $slug],
            [
                'name' => $name,
                'description' => $description,
                'metadata' => $metadata,
                'active' => true,
                'sort_order' => $sort,
            ],
        );
    }

    private function seedSettingsAuditLogs(User $admin, Scheme $insulation, Scheme $glazing): void
    {
        $entries = [
            ['settings.scheme_created', Scheme::class, $insulation->id, null, ['name' => $insulation->name]],
            ['settings.scheme_updated', Scheme::class, $glazing->id, ['description' => 'Draft'], ['description' => $glazing->description]],
            ['settings.pricing_updated', PricingRule::class, PricingRule::query()->where('scheme_id', $insulation->id)->value('id'), ['price_per_m2' => 2.80], ['price_per_m2' => 3.00]],
            ['settings.commission_updated', CommissionRule::class, CommissionRule::query()->where('name', 'Seller Company Default')->value('id'), ['percentage' => 8], ['percentage' => 10]],
            ['settings.general_updated', null, null, ['company_name' => 'RML'], ['company_name' => 'RML Energy Saving']],
            ['settings.template_updated', TemplateDocument::class, TemplateDocument::query()->where('type', TemplateDocumentType::SellerAgreement->value)->value('id'), null, ['name' => 'Seller Agreement']],
            ['lead.sold', null, null, null, ['note' => 'Demo lead sold after buyer payment']],
            ['payment.confirmed', null, null, null, ['note' => 'Buyer payment marked paid']],
            ['payout.due', null, null, null, ['note' => 'Seller payout marked due']],
            ['admin.login', User::class, $admin->id, null, ['email' => $admin->email]],
        ];

        foreach ($entries as $index => [$action, $entityType, $entityId, $old, $new]) {
            AuditLog::query()->updateOrCreate(
                [
                    'user_id' => $admin->id,
                    'action' => $action,
                    'entity_type' => $entityType,
                    'entity_id' => $entityId,
                ],
                [
                    'old_values' => $old,
                    'new_values' => $new,
                    'ip_address' => '127.0.0.1',
                    'user_agent' => 'RML Demo Seeder',
                    'created_at' => now()->subHours(count($entries) - $index),
                    'updated_at' => now()->subHours(count($entries) - $index),
                ],
            );
        }
    }

    private function seedInsulationFields(Scheme $scheme): void
    {
        $fields = [
            ['key' => 'zone', 'label' => 'Zone', 'type' => SchemeFieldType::Select, 'options' => ['D1', 'D2', 'E1', 'E2'], 'required' => true],
            ['key' => 'size_m2', 'label' => 'Size (m²)', 'type' => SchemeFieldType::Number, 'options' => null, 'required' => true],
            ['key' => 'insulation_type', 'label' => 'Insulation type', 'type' => SchemeFieldType::Select, 'options' => ['cavity_wall', 'loft', 'external_wall'], 'required' => true],
            ['key' => 'property_type', 'label' => 'Property type', 'type' => SchemeFieldType::Select, 'options' => ['detached', 'semi_detached', 'terrace', 'flat'], 'required' => false],
            ['key' => 'access_constraints', 'label' => 'Access constraints', 'type' => SchemeFieldType::Textarea, 'options' => null, 'required' => false],
            ['key' => 'epc_rating', 'label' => 'EPC rating', 'type' => SchemeFieldType::Select, 'options' => ['A', 'B', 'C', 'D', 'E', 'F', 'G'], 'required' => false],
            ['key' => 'notes', 'label' => 'Notes', 'type' => SchemeFieldType::Textarea, 'options' => null, 'required' => false],
        ];

        foreach ($fields as $index => $field) {
            SchemeField::query()->updateOrCreate(
                ['scheme_id' => $scheme->id, 'key' => $field['key']],
                [
                    'label' => $field['label'],
                    'type' => $field['type'],
                    'options' => $field['options'],
                    'required' => $field['required'],
                    'active' => $field['key'] !== 'property_type',
                    'sort_order' => $index + 1,
                ],
            );
        }
    }

    private function seedGlazingFields(Scheme $scheme): void
    {
        $fields = [
            ['key' => 'window_count', 'label' => 'Window count', 'type' => SchemeFieldType::Number, 'options' => null, 'required' => true],
            ['key' => 'glazing_area_m2', 'label' => 'Glazing area (m²)', 'type' => SchemeFieldType::Number, 'options' => null, 'required' => true],
            ['key' => 'current_glazing_type', 'label' => 'Current glazing type', 'type' => SchemeFieldType::Select, 'options' => ['single', 'old_double', 'failed_units'], 'required' => true],
            ['key' => 'frame_type', 'label' => 'Frame type', 'type' => SchemeFieldType::Select, 'options' => ['uPVC', 'aluminium', 'timber', 'mixed'], 'required' => false],
            ['key' => 'property_type', 'label' => 'Property type', 'type' => SchemeFieldType::Select, 'options' => ['detached', 'semi_detached', 'terrace', 'flat'], 'required' => false],
            ['key' => 'zone', 'label' => 'Zone (context)', 'type' => SchemeFieldType::Select, 'options' => ['D1', 'D2', 'E1', 'E2'], 'required' => true],
            ['key' => 'notes', 'label' => 'Notes', 'type' => SchemeFieldType::Textarea, 'options' => null, 'required' => false],
        ];

        foreach ($fields as $index => $field) {
            SchemeField::query()->updateOrCreate(
                ['scheme_id' => $scheme->id, 'key' => $field['key']],
                [
                    'label' => $field['label'],
                    'type' => $field['type'],
                    'options' => $field['options'],
                    'required' => $field['required'],
                    'active' => $field['key'] !== 'property_type',
                    'sort_order' => $index + 1,
                ],
            );
        }

        SchemeField::query()
            ->where('scheme_id', $scheme->id)
            ->where('key', 'zone_context')
            ->update(['active' => false]);
    }

    private function seedHeatPumpFields(Scheme $scheme): void
    {
        $fields = [
            ['key' => 'current_heating_system', 'label' => 'Current heating system', 'type' => SchemeFieldType::Select, 'options' => ['gas_boiler', 'oil_boiler', 'electric', 'biomass', 'other'], 'required' => true],
            ['key' => 'proposed_heat_pump_type', 'label' => 'Proposed heat pump type', 'type' => SchemeFieldType::Select, 'options' => ['aerothermal', 'geothermal', 'hydrothermal'], 'required' => true],
            ['key' => 'property_size_m2', 'label' => 'Property size (m²)', 'type' => SchemeFieldType::Number, 'options' => null, 'required' => true],
            ['key' => 'estimated_kw', 'label' => 'Estimated kW', 'type' => SchemeFieldType::Number, 'options' => null, 'required' => true],
            ['key' => 'outdoor_unit_feasibility', 'label' => 'Outdoor unit feasibility', 'type' => SchemeFieldType::Select, 'options' => ['good', 'constrained', 'uncertain'], 'required' => true],
            ['key' => 'electrical_supply_notes', 'label' => 'Electrical supply notes', 'type' => SchemeFieldType::Textarea, 'options' => null, 'required' => true],
            ['key' => 'emitter_type', 'label' => 'Existing emitters', 'type' => SchemeFieldType::Select, 'options' => ['radiators', 'underfloor', 'fan_coils', 'mixed'], 'required' => false],
            ['key' => 'zone', 'label' => 'Zone (context)', 'type' => SchemeFieldType::Select, 'options' => ['D1', 'D2', 'E1', 'E2'], 'required' => true],
            ['key' => 'notes', 'label' => 'Technical notes', 'type' => SchemeFieldType::Textarea, 'options' => null, 'required' => true],
        ];

        foreach ($fields as $index => $field) {
            SchemeField::query()->updateOrCreate(
                ['scheme_id' => $scheme->id, 'key' => $field['key']],
                [
                    'label' => $field['label'],
                    'type' => $field['type'],
                    'options' => $field['options'],
                    'required' => $field['required'],
                    'active' => true,
                    'sort_order' => $index + 1,
                ],
            );
        }

        SchemeField::query()
            ->where('scheme_id', $scheme->id)
            ->where('key', 'zone_context')
            ->update(['active' => false]);
    }

    /**
     * @param  list<array{key: string, label: string}>  $items
     */
    private function seedSchemeChecklist(Scheme $scheme, array $items): void
    {
        foreach ($items as $index => $item) {
            AuditChecklistItem::query()->updateOrCreate(
                ['scheme_id' => $scheme->id, 'key' => $item['key']],
                [
                    'label' => $item['label'],
                    'required' => true,
                    'active' => true,
                    'sort_order' => $index + 1,
                ],
            );
        }
    }
}
