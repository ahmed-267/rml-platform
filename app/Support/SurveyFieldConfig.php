<?php

namespace App\Support;

/**
 * Shared survey field options and required-field definitions.
 * Frontend options and backend step validation must stay aligned with this config.
 */
final class SurveyFieldConfig
{
    /**
     * @return list<string>
     */
    public static function steps(): array
    {
        return [
            'property',
            'measurements',
            'scheme',
            'evidence',
            'homeowner',
            'risks',
            'review',
        ];
    }

    /**
     * @return array<string, list<string>>
     */
    public static function options(): array
    {
        return [
            'property_types' => [
                'detached',
                'semi_detached',
                'terrace',
                'flat',
                'bungalow',
                'maisonette',
                'other',
            ],
            'occupancy_types' => [
                'owner_occupied',
                'tenanted',
                'holiday',
                'vacant',
                'other',
            ],
            'yes_no' => ['yes', 'no'],
            'yes_no_unknown' => ['yes', 'no', 'unknown'],
            'general_conditions' => [
                'excellent',
                'good',
                'fair',
                'poor',
                'unsafe',
            ],
            'access_unavailable_reasons' => [
                'keys_unavailable',
                'permission_refused',
                'physically_impossible',
                'unsafe',
                'occupied_restricted',
                'other',
            ],
            'refused_or_impossible' => [
                'refused',
                'physically_impossible',
                'both',
            ],
            'section_types' => [
                'main_loft',
                'extension_roof',
                'front_external_wall',
                'rear_external_wall',
                'side_wall',
                'ground_floor_windows',
                'upper_floor_windows',
                'flat_roof',
                'room_in_roof',
                'other',
            ],
            'measurement_methods' => [
                'manual_tape',
                'laser',
                'property_plan',
                'epc_certificate',
                'cadastral_reference_only',
                'visual_estimate',
                'other',
            ],
            'measurement_confidence' => [
                'high',
                'medium',
                'low',
                'estimate_only',
            ],
            'insulation_types' => [
                'loft',
                'cavity_wall',
                'external_wall',
                'internal_wall',
                'room_in_roof',
                'flat_roof',
            ],
            'insulation_materials' => [
                'mineral_wool',
                'cellulose',
                'pir',
                'eps',
                'sheep_wool',
                'none',
                'unknown',
                'other',
            ],
            'hatch_conditions' => [
                'good',
                'fair',
                'poor',
                'damaged',
                'missing',
            ],
            'condition_ratings' => [
                'good',
                'fair',
                'poor',
                'damaged',
                'unknown',
            ],
            'suitability' => [
                'suitable',
                'suitable_with_remedial',
                'not_suitable',
                'needs_further_assessment',
            ],
            'recommendation_outcomes' => [
                'proceed',
                'proceed_with_conditions',
                'return_visit',
                'not_recommended',
            ],
            'glazing_types' => [
                'single',
                'double',
                'mixed',
                'unknown',
            ],
            'frame_materials' => [
                'uPVC',
                'aluminium',
                'timber',
                'composite',
                'steel',
                'mixed',
                'other',
            ],
            'opening_types' => [
                'casement',
                'sash',
                'tilt_turn',
                'fixed',
                'sliding',
                'mixed',
                'other',
            ],
            'window_locations' => [
                'front',
                'rear',
                'side',
                'upper',
                'ground',
                'mixed',
                'other',
            ],
            'heating_systems' => [
                'gas_boiler',
                'oil',
                'electric',
                'biomass',
                'existing_heat_pump',
                'other',
            ],
            'heat_pump_types' => [
                'air_source',
                'ground_source',
                'hybrid',
            ],
            'radiator_suitability' => [
                'suitable',
                'partial',
                'unsuitable',
                'unknown',
            ],
            'electrical_supply' => [
                'single_phase',
                'three_phase',
                'unknown',
                'needs_assessment',
            ],
            'homeowner_relationships' => [
                'owner',
                'tenant',
                'family_member',
                'authorised_representative',
                'agent',
                'other',
            ],
            'risk_severity' => [
                'low',
                'medium',
                'high',
                'critical',
            ],
            'installation_impact' => [
                'none',
                'delay',
                'additional_labour',
                'cannot_proceed',
                'specialist_required',
            ],
            'note_visibility' => [
                'seller',
                'buyer',
                'internal',
            ],
            'evidence_categories' => [
                'front_exterior',
                'rear_exterior',
                'property_access',
                'internal_access',
                'installation_area',
                'measurement_evidence',
                'existing_condition',
                'obstructions',
                'damage_risks',
                'equipment_heating',
                'meter_electrical',
                'loft_hatch',
                'property_document',
                'epc_certificate',
                'homeowner_agreement',
                'other',
            ],
            'risk_options' => [
                'no_access',
                'unsafe_access',
                'damp',
                'mould',
                'structural_damage',
                'electrical_risk',
                'asbestos_concern',
                'restricted_area',
                'damaged_roof',
                'damaged_wall',
                'water_ingress',
                'ventilation_issue',
                'occupant_vulnerability',
                'planning_restriction',
                'heritage_restriction',
                'measurement_uncertainty',
                'missing_documentation',
                'specialist_inspection_required',
                'return_visit_required',
                'additional_labour_required',
                'other',
            ],
        ];
    }

    /**
     * Required field keys per step (always required when step is visible).
     * Scheme-specific keys are merged when $schemeSlug is provided.
     *
     * @return array<string, list<string>>
     */
    public static function requiredFieldsByStep(?string $schemeSlug = null): array
    {
        $fields = [
            'property' => [
                'survey_date',
                'confirmed_address',
                'property_type',
                'occupancy_type',
                'occupied',
                'homeowner_present',
                'general_condition',
                'location_confirmed',
                'access.internal_access',
                'access.external_access',
                'access.access_safe',
            ],
            'measurements' => [
                'surveyed_installation_area_m2',
                'measurement_method',
                'measurement_confidence',
                'measurement_date',
                'measurement_sections',
            ],
            'scheme' => [],
            'evidence' => [],
            'homeowner' => [
                'homeowner_confirmation.name',
                'homeowner_confirmation.relationship',
                'homeowner_confirmation.permission_to_inspect',
                'homeowner_confirmation.permission_evidence',
                'homeowner_confirmation.confirmation_date',
            ],
            'risks' => [
                'surveyor_recommendation',
            ],
            'review' => [],
        ];

        if ($schemeSlug === 'insulation') {
            $fields['property'][] = 'loft_hatch.existing_hatch';
            $fields['scheme'] = [
                'scheme_inspection.proposed_insulation_type',
                'scheme_inspection.existing_insulation_present',
                'scheme_inspection.suitable_for_installation',
            ];
        } elseif ($schemeSlug === 'double-glazing') {
            $fields['scheme'] = [
                'scheme_inspection.current_glazing_type',
                'scheme_inspection.frame_material',
                'scheme_inspection.replacement_suitability',
            ];
        } elseif ($schemeSlug === 'heat-pumps') {
            $fields['scheme'] = [
                'scheme_inspection.current_heating_system',
                'scheme_inspection.proposed_heat_pump_type',
                'scheme_inspection.suitable_for_installation',
            ];
        }

        return $fields;
    }
}
