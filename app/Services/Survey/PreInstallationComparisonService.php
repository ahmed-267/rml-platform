<?php

namespace App\Services\Survey;

use App\Enums\CatastroVerificationStatus;
use App\Models\Lead;
use App\Models\LeadCatastroSnapshot;
use App\Models\LeadSurvey;
use App\Services\Catastro\CatastroComparisonService;

/**
 * Builds Submitted vs Catastro vs Survey comparison rows and discrepancy warnings
 * for Pre-Installation Audit / Lead Details. Never mutates source data.
 */
final class PreInstallationComparisonService
{
    public function __construct(
        private readonly ?CatastroComparisonService $catastroComparison = null,
    ) {}

    private function comparison(): CatastroComparisonService
    {
        return $this->catastroComparison ?? app(CatastroComparisonService::class);
    }

    /**
     * @return array{
     *     rows: list<array{field: string, label: string, submitted: string|null, catastro: string|null, survey: string|null}>,
     *     warnings: list<array{code: string, message: string, severity: string}>
     * }
     */
    public function forLead(Lead $lead): array
    {
        $lead->loadMissing(['survey.measurementSections', 'survey.evidence', 'latestCatastroSnapshot', 'scheme']);

        $survey = $lead->survey;
        $catastro = $lead->latestCatastroSnapshot;

        $rows = [
            $this->row(
                'property_use',
                __('rml.pre_installation.comparison.property_use'),
                $this->display($lead->property_type),
                $this->display($catastro?->property_use),
                $this->display($survey?->property_type),
            ),
            $this->row(
                'area_m2',
                __('rml.pre_installation.comparison.area_m2'),
                $this->area($lead->submitted_property_area_m2 ?? $lead->size_m2),
                $this->area($catastro?->constructed_area_m2),
                $this->area($survey?->surveyed_installation_area_m2 ?? $survey?->surveyed_floor_area_m2),
            ),
            $this->row(
                'postcode',
                __('rml.pre_installation.comparison.postcode'),
                $this->display($lead->postcode),
                $this->display($catastro?->postcode),
                $this->display($this->surveyPostcode($survey)),
            ),
            $this->row(
                'municipality',
                __('rml.pre_installation.comparison.municipality'),
                $this->display($lead->city),
                $this->display($catastro?->municipality),
                $this->display($this->surveyCity($survey)),
            ),
            $this->row(
                'address',
                __('rml.pre_installation.comparison.address'),
                $this->display($lead->address_line_1),
                $this->display($catastro?->cadastral_address),
                $this->display($survey?->confirmed_address),
            ),
            $this->row(
                'cadastral_reference',
                __('rml.pre_installation.comparison.cadastral_reference'),
                $this->display($lead->cadastral_reference),
                $this->display($catastro?->cadastral_reference ?? $lead->cadastral_reference),
                null,
            ),
        ];

        return [
            'rows' => $rows,
            'warnings' => $this->warnings($lead, $survey, $catastro),
        ];
    }

    /**
     * @return list<array{code: string, message: string, severity: string}>
     */
    private function warnings(Lead $lead, ?LeadSurvey $survey, ?LeadCatastroSnapshot $catastro): array
    {
        $warnings = [];

        if (! filled($lead->cadastral_reference)) {
            $warnings[] = $this->warning(
                'cadastral_reference_missing',
                __('rml.pre_installation.warnings.cadastral_reference_missing'),
                'warning',
            );
        }

        $status = $catastro?->verification_status;
        if ($status === CatastroVerificationStatus::LookupFailed
            || $status === CatastroVerificationStatus::ServiceUnavailable
            || $status === CatastroVerificationStatus::PropertyNotFound) {
            $warnings[] = $this->warning(
                'catastro_lookup_failed',
                __('rml.pre_installation.warnings.catastro_lookup_failed'),
                'danger',
            );
        }

        if ($status === CatastroVerificationStatus::ManualReviewRequired
            || $status === CatastroVerificationStatus::MultiplePropertiesFound
            || $status === CatastroVerificationStatus::RegionalProviderRequired) {
            $warnings[] = $this->warning(
                'catastro_manual_verification',
                __('rml.pre_installation.warnings.catastro_manual_verification'),
                'warning',
            );
        }

        if ($catastro && $status && ! in_array($status, [
            CatastroVerificationStatus::NotChecked,
            CatastroVerificationStatus::LookupInProgress,
        ], true)) {
            $property = new \App\Services\Catastro\DTO\CatastroProperty(
                cadastralReference: (string) ($catastro->cadastral_reference ?? ''),
                cadastralAddress: $catastro->cadastral_address,
                province: $catastro->province,
                municipality: $catastro->municipality,
                propertyUse: $catastro->property_use,
                constructedAreaM2: $catastro->constructed_area_m2 !== null ? (float) $catastro->constructed_area_m2 : null,
                parcelAreaM2: $catastro->parcel_area_m2 !== null ? (float) $catastro->parcel_area_m2 : null,
                constructionYear: $catastro->construction_year,
                floor: $catastro->floor,
                door: $catastro->door,
                postcode: $catastro->postcode,
                geometry: is_array($catastro->geometry) ? $catastro->geometry : null,
            );

            $compare = $this->comparison()->compare($lead, $property, $survey);
            foreach ($compare['warnings'] as $item) {
                $code = (string) ($item['code'] ?? 'discrepancy');
                $warnings[] = $this->warning(
                    $code,
                    (string) ($item['message'] ?? $code),
                    in_array($code, [
                        'address_mismatch',
                        'municipality_mismatch',
                        'province_mismatch',
                        'postcode_mismatch',
                        'property_use_mismatch',
                    ], true) ? 'danger' : 'warning',
                );
            }
        }

        if ($survey) {
            $submittedArea = $lead->submitted_property_area_m2 !== null
                ? (float) $lead->submitted_property_area_m2
                : ($lead->size_m2 !== null ? (float) $lead->size_m2 : null);
            $surveyArea = $survey->surveyed_installation_area_m2 !== null
                ? (float) $survey->surveyed_installation_area_m2
                : ($survey->surveyed_floor_area_m2 !== null ? (float) $survey->surveyed_floor_area_m2 : null);

            if ($submittedArea && $surveyArea && $submittedArea > 0) {
                $diffPct = abs($submittedArea - $surveyArea) / $submittedArea * 100;
                if ($diffPct > 15) {
                    $warnings[] = $this->warning(
                        'survey_area_vs_submitted',
                        __('rml.pre_installation.warnings.survey_area_vs_submitted', [
                            'submitted' => number_format($submittedArea, 2),
                            'survey' => number_format($surveyArea, 2),
                        ]),
                        'warning',
                    );
                }
            }

            $access = is_array($survey->access) ? $survey->access : [];
            if (($access['access_safe'] ?? null) === false) {
                $warnings[] = $this->warning(
                    'access_not_confirmed',
                    __('rml.pre_installation.warnings.access_not_confirmed'),
                    'warning',
                );
            }

            $categories = $survey->evidence->pluck('category')->unique()->all();
            foreach (['front_exterior', 'installation_area'] as $required) {
                if (! in_array($required, $categories, true)) {
                    $warnings[] = $this->warning(
                        'survey_evidence_missing',
                        __('rml.pre_installation.warnings.survey_evidence_missing', [
                            'category' => $required,
                        ]),
                        'warning',
                    );
                }
            }
        }

        // De-duplicate by code+message while preserving order.
        $seen = [];
        $unique = [];
        foreach ($warnings as $warning) {
            $key = $warning['code'].'|'.$warning['message'];
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $unique[] = $warning;
        }

        return $unique;
    }

    /**
     * @return array{field: string, label: string, submitted: string|null, catastro: string|null, survey: string|null}
     */
    private function row(string $field, string $label, ?string $submitted, ?string $catastro, ?string $survey): array
    {
        return [
            'field' => $field,
            'label' => $label,
            'submitted' => $submitted,
            'catastro' => $catastro,
            'survey' => $survey,
        ];
    }

    /**
     * @return array{code: string, message: string, severity: string}
     */
    private function warning(string $code, string $message, string $severity): array
    {
        return [
            'code' => $code,
            'message' => $message,
            'severity' => $severity,
        ];
    }

    private function display(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $string = trim((string) $value);

        return $string === '' ? null : $string;
    }

    private function area(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return number_format((float) $value, 2).' m²';
    }

    private function surveyPostcode(?LeadSurvey $survey): ?string
    {
        if (! $survey?->confirmed_address) {
            return null;
        }

        if (preg_match('/\b(\d{5})\b/', $survey->confirmed_address, $matches)) {
            return $matches[1];
        }

        return null;
    }

    private function surveyCity(?LeadSurvey $survey): ?string
    {
        // Confirmed address is free text; city is not a separate survey column.
        return null;
    }
}
