<?php

namespace App\Services\Catastro;

use App\Enums\CatastroVerificationStatus;
use App\Models\Lead;
use App\Models\LeadSurvey;
use App\Services\Catastro\DTO\CatastroProperty;
use App\Services\DistanceService;

final class CatastroComparisonService
{
    /**
     * Warning codes treated as hard mismatches (auditor must review).
     *
     * @var list<string>
     */
    private const HARD_MISMATCH_CODES = [
        'address_mismatch',
        'municipality_mismatch',
        'province_mismatch',
        'postcode_mismatch',
        'property_use_mismatch',
    ];

    public function __construct(
        private readonly DistanceService $distanceService,
    ) {}

    /**
     * @return array{
     *     status: CatastroVerificationStatus,
     *     warnings: list<array{code: string, message: string}>,
     *     match_summary: string,
     *     coordinate_distance_m: float|null
     * }
     */
    public function compare(Lead $lead, CatastroProperty $property, ?LeadSurvey $survey = null): array
    {
        $warnings = [];
        $areaTolerancePct = (float) config('rml.catastro.area_tolerance_percent', 15);
        $coordWarnMeters = (float) config('rml.catastro.coordinate_warn_meters', 150);

        $submittedAddress = $this->normalizeText(implode(' ', array_filter([
            $lead->address_line_1,
            $lead->city,
            $lead->postcode,
        ])));
        $cadastralAddress = $this->normalizeText((string) ($property->cadastralAddress ?? ''));

        if ($submittedAddress !== '' && $cadastralAddress !== '' && ! $this->addressesLikelyMatch($submittedAddress, $cadastralAddress, $lead, $property)) {
            $warnings[] = [
                'code' => 'address_mismatch',
                'message' => 'Submitted address differs from cadastral address.',
            ];
        }

        if ($lead->city && $property->municipality) {
            $city = $this->normalizeText($lead->city);
            $muni = $this->normalizeText($property->municipality);
            if ($city !== '' && $muni !== '' && ! str_contains($muni, $city) && ! str_contains($city, $muni)) {
                $warnings[] = [
                    'code' => 'municipality_mismatch',
                    'message' => 'Submitted city differs from cadastral municipality.',
                ];
            }
        }

        $submittedPostcode = $this->normalizePostcode((string) ($lead->postcode ?? ''));
        $cadastralPostcode = $this->normalizePostcode((string) ($property->postcode ?? ''));
        if ($submittedPostcode !== '' && $cadastralPostcode !== '' && $submittedPostcode !== $cadastralPostcode) {
            $warnings[] = [
                'code' => 'postcode_mismatch',
                'message' => 'Submitted postcode differs from cadastral postcode.',
            ];
        }

        $submittedProvince = $this->submittedProvince($lead);
        $cadastralProvince = $this->normalizeText((string) ($property->province ?? ''));
        if ($submittedProvince !== '' && $cadastralProvince !== '' && $submittedProvince !== $cadastralProvince
            && ! str_contains($cadastralProvince, $submittedProvince)
            && ! str_contains($submittedProvince, $cadastralProvince)
        ) {
            $warnings[] = [
                'code' => 'province_mismatch',
                'message' => 'Submitted province differs from cadastral province.',
            ];
        }

        $submittedArea = $lead->submitted_property_area_m2 !== null
            ? (float) $lead->submitted_property_area_m2
            : ($lead->size_m2 !== null ? (float) $lead->size_m2 : null);
        $cadastralArea = $property->constructedAreaM2;

        if ($submittedArea !== null && $cadastralArea !== null && $cadastralArea > 0) {
            $diffPct = abs($submittedArea - $cadastralArea) / $cadastralArea * 100;
            if ($diffPct > $areaTolerancePct) {
                $warnings[] = [
                    'code' => 'area_mismatch',
                    'message' => sprintf(
                        'Submitted area (%.2f m²) differs from cadastral constructed area (%.2f m²) by %.1f%%.',
                        $submittedArea,
                        $cadastralArea,
                        $diffPct,
                    ),
                ];
            }
        }

        if ($survey?->surveyed_floor_area_m2 !== null && $cadastralArea !== null && $cadastralArea > 0) {
            $surveyArea = (float) $survey->surveyed_floor_area_m2;
            $diffPct = abs($surveyArea - $cadastralArea) / $cadastralArea * 100;
            if ($diffPct > $areaTolerancePct) {
                $warnings[] = [
                    'code' => 'survey_area_mismatch',
                    'message' => sprintf(
                        'Surveyed floor area (%.2f m²) differs from cadastral constructed area (%.2f m²).',
                        $surveyArea,
                        $cadastralArea,
                    ),
                ];
            }
        }

        if ($property->propertyUse) {
            $use = mb_strtolower($property->propertyUse);
            $residential = str_contains($use, 'resid') || str_contains($use, 'vivienda');
            $commercial = str_contains($use, 'comercial')
                || str_contains($use, 'commercial')
                || str_contains($use, 'industrial')
                || str_contains($use, 'oficina')
                || str_contains($use, 'office');

            $submittedType = mb_strtolower(trim((string) (
                $lead->property_type
                ?: $survey?->property_type
                ?: 'residential'
            )));
            $submittedCommercial = str_contains($submittedType, 'commercial')
                || str_contains($submittedType, 'industrial')
                || str_contains($submittedType, 'office')
                || str_contains($submittedType, 'oficina');
            $submittedResidential = ! $submittedCommercial;

            if ($commercial && $submittedResidential) {
                $warnings[] = [
                    'code' => 'property_use_mismatch',
                    'message' => 'Submitted residential use does not match cadastral property use.',
                ];
            } elseif ($residential && $submittedCommercial) {
                $warnings[] = [
                    'code' => 'property_use_mismatch',
                    'message' => 'Submitted commercial use does not match cadastral residential use.',
                ];
            } elseif (! $residential && ! $commercial) {
                $warnings[] = [
                    'code' => 'property_use_unknown',
                    'message' => 'Cadastral property use needs manual review.',
                ];
            }
        }

        $distanceM = null;
        $lat = $property->geometry['lat'] ?? null;
        $lng = $property->geometry['lng'] ?? null;
        if (
            is_numeric($lat) && is_numeric($lng)
            && $lead->latitude !== null && $lead->longitude !== null
        ) {
            $distanceKm = $this->distanceService->haversineKm(
                (float) $lead->latitude,
                (float) $lead->longitude,
                (float) $lat,
                (float) $lng,
            );
            $distanceM = round($distanceKm * 1000, 2);
            if ($distanceM > $coordWarnMeters) {
                $warnings[] = [
                    'code' => 'coordinate_mismatch',
                    'message' => sprintf('Lead coordinates are approximately %.0f m from cadastral location.', $distanceM),
                ];
            }
        }

        $hasHard = collect($warnings)->contains(
            fn (array $w) => in_array($w['code'], self::HARD_MISMATCH_CODES, true),
        );
        $status = match (true) {
            $warnings === [] => CatastroVerificationStatus::Matched,
            $hasHard => CatastroVerificationStatus::MismatchDetected,
            default => CatastroVerificationStatus::PartiallyMatched,
        };

        $summary = match ($status) {
            CatastroVerificationStatus::Matched => 'Submitted lead data aligns with Catastro.',
            CatastroVerificationStatus::PartiallyMatched => 'Partial match — review area or address differences.',
            default => 'Mismatch detected — auditor review required.',
        };

        return [
            'status' => $status,
            'warnings' => $warnings,
            'match_summary' => $summary,
            'coordinate_distance_m' => $distanceM,
        ];
    }

    /**
     * Lead has no province column. Only use an explicitly reliable attribute if present
     * (e.g. future/search payload mirrored onto the model). Do not parse formatted_address.
     */
    private function submittedProvince(Lead $lead): string
    {
        $attributes = $lead->getAttributes();
        if (! array_key_exists('province', $attributes)) {
            return '';
        }

        $province = $attributes['province'];

        return is_string($province) ? $this->normalizeText($province) : '';
    }

    private function addressesLikelyMatch(string $submitted, string $cadastral, Lead $lead, CatastroProperty $property): bool
    {
        if ($submitted === $cadastral) {
            return true;
        }

        $parts = array_filter([
            $this->normalizeText((string) $property->streetName),
            $this->normalizeText((string) $property->streetNumber),
            $this->normalizeText((string) $property->municipality),
            $this->normalizeText((string) $property->postcode),
            $this->normalizeText((string) $lead->postcode),
        ]);

        $hits = 0;
        foreach ($parts as $part) {
            if ($part !== '' && (str_contains($submitted, $part) || str_contains($cadastral, $part))) {
                $hits++;
            }
        }

        return $hits >= 2;
    }

    private function normalizePostcode(string $value): string
    {
        return preg_replace('/\s+/', '', mb_strtolower(trim($value))) ?? '';
    }

    private function normalizeText(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = strtr($value, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u',
            'ü' => 'u', 'ñ' => 'n',
        ]);
        $value = preg_replace('/[^a-z0-9]+/', ' ', $value) ?? $value;

        return trim(preg_replace('/\s+/', ' ', $value) ?? $value);
    }
}
