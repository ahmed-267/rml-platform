<?php

namespace App\Support;

/**
 * Canonical scheme configuration keys for Settings UI / metadata.
 */
final class SchemeConfig
{
    public const LEAD_TYPES = [
        'building_envelope',
        'windows_envelope',
        'heating_system',
        'other',
    ];

    public const PRICING_BASES = [
        'zone_m2',
        'window_area_count',
        'per_lead_kw',
        'fixed_price',
        'custom',
    ];

    public const INPUT_KEYS = [
        'zone',
        'area_m2',
        'insulation_type',
        'window_count',
        'glazing_area',
        'current_glazing_type',
        'frame_type',
        'current_heating_system',
        'proposed_heat_pump_type',
        'estimated_kw',
        'property_type',
        'property_size',
        'outdoor_unit_feasibility',
        'electrical_supply_notes',
        'photos',
        'homeowner_agreement',
        'energy_certificate_status',
        'cadastral_reference',
        'technical_memory_status',
        'distance',
    ];

    /**
     * Short EN labels used for compact table summaries (UI translates via keys).
     *
     * @return array<string, string>
     */
    public static function inputShortLabels(): array
    {
        return [
            'zone' => 'Zone',
            'area_m2' => 'Area',
            'insulation_type' => 'Insulation type',
            'window_count' => 'Window count',
            'glazing_area' => 'Glazing area',
            'current_glazing_type' => 'Glazing type',
            'frame_type' => 'Frame type',
            'current_heating_system' => 'Heating system',
            'proposed_heat_pump_type' => 'HP type',
            'estimated_kw' => 'kW',
            'property_type' => 'Property type',
            'property_size' => 'Property size',
            'outdoor_unit_feasibility' => 'Outdoor unit',
            'electrical_supply_notes' => 'Electrical',
            'photos' => 'Photos',
            'homeowner_agreement' => 'Agreement',
            'energy_certificate_status' => 'EPC',
            'cadastral_reference' => 'Cadastral',
            'technical_memory_status' => 'Tech. memory',
            'distance' => 'Distance',
        ];
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return list<string>
     */
    public static function requiredInputs(array $metadata): array
    {
        $inputs = $metadata['required_inputs'] ?? null;
        if (is_array($inputs) && $inputs !== []) {
            return array_values(array_intersect(self::INPUT_KEYS, $inputs));
        }

        // Backward-compatible fallback from legacy flags.
        $legacy = [];
        $requirements = $metadata['requirements'] ?? [];
        if (! empty($requirements['zone_required'])) {
            $legacy[] = 'zone';
        }
        if (! empty($requirements['area_required'])) {
            $legacy[] = 'area_m2';
        }
        if (! empty($requirements['distance_required'])) {
            $legacy[] = 'distance';
        }
        $docs = (string) ($metadata['eligibility']['required_documents'] ?? '');
        if (stripos($docs, 'photo') !== false) {
            $legacy[] = 'photos';
        }
        if (stripos($docs, 'agreement') !== false) {
            $legacy[] = 'homeowner_agreement';
        }

        return $legacy;
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    public static function leadType(array $metadata, ?string $slug = null): string
    {
        $type = $metadata['lead_type'] ?? null;
        if (is_string($type) && in_array($type, self::LEAD_TYPES, true)) {
            return $type;
        }

        return match ($slug) {
            'insulation' => 'building_envelope',
            'double-glazing' => 'windows_envelope',
            'heat-pumps' => 'heating_system',
            default => 'other',
        };
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    public static function pricingBasis(array $metadata, ?string $slug = null): string
    {
        $basis = $metadata['pricing_basis'] ?? null;
        if (is_string($basis) && in_array($basis, self::PRICING_BASES, true)) {
            return $basis;
        }

        return match ($slug) {
            'insulation' => 'zone_m2',
            'double-glazing' => 'window_area_count',
            'heat-pumps' => 'per_lead_kw',
            default => 'custom',
        };
    }

    /**
     * Compact table summary of required inputs (locale-agnostic short EN tokens).
     *
     * @param  array<string, mixed>  $metadata
     */
    public static function requiredInputsSummary(array $metadata): ?string
    {
        $inputs = self::requiredInputs($metadata);
        if ($inputs === []) {
            return null;
        }

        $labels = self::inputShortLabels();
        $parts = array_map(fn (string $key) => $labels[$key] ?? $key, array_slice($inputs, 0, 4));

        return implode(', ', $parts);
    }

    /**
     * Whether a scheme payload has enough data to be marked Active.
     *
     * @param  array<string, mixed>  $data
     */
    public static function isReadyForActivation(array $data): bool
    {
        $name = trim((string) ($data['name'] ?? ''));
        $description = trim((string) ($data['description'] ?? ''));
        $metadata = is_array($data['metadata'] ?? null) ? $data['metadata'] : [];
        $leadType = $metadata['lead_type'] ?? null;
        $pricingBasis = $metadata['pricing_basis'] ?? null;
        $requiredInputs = $metadata['required_inputs'] ?? [];

        if ($name === '' || $description === '') {
            return false;
        }

        if (! is_string($leadType) || ! in_array($leadType, self::LEAD_TYPES, true)) {
            return false;
        }

        if (! is_string($pricingBasis) || ! in_array($pricingBasis, self::PRICING_BASES, true)) {
            return false;
        }

        if (! is_array($requiredInputs) || $requiredInputs === []) {
            return false;
        }

        if (! self::hasPricingValue($data, $pricingBasis)) {
            return false;
        }

        $evidence = $metadata['evidence'] ?? [];
        $docs = trim((string) ($metadata['eligibility']['required_documents'] ?? ''));
        $hasEvidence = (is_array($evidence) && $evidence !== [])
            || $docs !== ''
            || in_array('photos', $requiredInputs, true)
            || in_array('homeowner_agreement', $requiredInputs, true);

        return $hasEvidence;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function hasPricingValue(array $data, string $pricingBasis): bool
    {
        $factors = is_array($data['metadata']['pricing_factors'] ?? null)
            ? $data['metadata']['pricing_factors']
            : [];

        return match ($pricingBasis) {
            'zone_m2' => self::hasNumeric($data['zone_prices']['D1'] ?? null)
                || self::hasNumeric($data['zone_prices']['D2'] ?? null)
                || self::hasNumeric($data['zone_prices']['E1'] ?? null)
                || self::hasNumeric($data['zone_prices']['E2'] ?? null)
                || self::hasNumeric($data['price_per_m2'] ?? null)
                || self::hasNumeric($data['base_price'] ?? null),
            'window_area_count' => self::hasNumeric($data['price_per_m2'] ?? null)
                || self::hasNumeric($factors['price_per_window'] ?? null)
                || self::hasNumeric($data['base_price'] ?? null),
            'per_lead_kw' => self::hasNumeric($data['base_price'] ?? null)
                || self::hasNumeric($data['price_per_m2'] ?? null)
                || self::hasNumeric($factors['price_per_kw'] ?? null)
                || self::hasNumeric($factors['kw_factor'] ?? null),
            'fixed_price' => self::hasNumeric($data['base_price'] ?? null),
            'custom' => self::hasNumeric($data['base_price'] ?? null)
                || self::hasNumeric($data['price_per_m2'] ?? null)
                || trim((string) ($data['metadata']['pricing_notes'] ?? '')) !== ''
                || trim((string) ($data['metadata']['algorithm']['formula'] ?? '')) !== '',
            default => false,
        };
    }

    private static function hasNumeric(mixed $value): bool
    {
        if ($value === null || $value === '') {
            return false;
        }

        return is_numeric($value);
    }
}
