<?php

namespace App\Services\Catastro;

use App\Enums\CadastralLookupStatus;
use App\Enums\CatastroProvider;
use App\Enums\CatastroSearchMethod;
use App\Enums\CatastroVerificationStatus;
use App\Models\Lead;
use App\Models\LeadCatastroSnapshot;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\Catastro\Contracts\CatastroProviderInterface;
use App\Services\Catastro\DTO\CatastroLookupResult;
use App\Services\Catastro\DTO\CatastroProperty;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class CatastroLookupService
{
    public function __construct(
        private readonly CatastroProviderInterface $provider,
        private readonly CatastroComparisonService $comparison,
        private readonly AuditLogService $auditLog,
    ) {}

    /**
     * @return array{snapshot: LeadCatastroSnapshot, candidates: list<array<string, mixed>>}
     */
    public function lookupByReference(Lead $lead, string $reference, User $actor): array
    {
        $normalized = CatastroReferenceNormalizer::normalize($reference);
        $searchInput = ['cadastral_reference' => $normalized];

        $this->logLookupRequested($lead, $actor, CatastroSearchMethod::Reference, $searchInput);

        return $this->runLookup(
            lead: $lead,
            actor: $actor,
            method: CatastroSearchMethod::Reference,
            searchInput: $searchInput,
            result: $this->provider->lookupByReference($normalized),
        );
    }

    /**
     * @param  array<string, mixed>  $address
     * @return array{snapshot: LeadCatastroSnapshot, candidates: list<array<string, mixed>>}
     */
    public function lookupByAddress(Lead $lead, array $address, User $actor): array
    {
        $this->logLookupRequested($lead, $actor, CatastroSearchMethod::Address, $address);

        return $this->runLookup(
            lead: $lead,
            actor: $actor,
            method: CatastroSearchMethod::Address,
            searchInput: $address,
            result: $this->provider->lookupByAddress($address),
        );
    }

    /**
     * @return array{snapshot: LeadCatastroSnapshot, candidates: list<array<string, mixed>>}
     */
    public function selectResult(Lead $lead, LeadCatastroSnapshot $snapshot, string $cadastralReference, User $actor): array
    {
        $candidates = $snapshot->raw_payload['candidates'] ?? [];
        if (! is_array($candidates) || $candidates === []) {
            throw new InvalidArgumentException('no_candidates');
        }

        $chosen = collect($candidates)->first(
            fn ($row) => is_array($row) && ($row['cadastral_reference'] ?? null) === $cadastralReference
        );

        if (! is_array($chosen)) {
            throw new InvalidArgumentException('candidate_not_found');
        }

        $property = $this->propertyFromArray($chosen);
        $comparison = $this->comparison->compare($lead, $property, $lead->survey);

        return DB::transaction(function () use ($lead, $snapshot, $actor, $property, $comparison, $candidates) {
            $snapshot->fill([
                ...$this->propertyAttributes($property),
                'verification_status' => $comparison['status'],
                'match_summary' => $comparison['match_summary'],
                'warnings' => $comparison['warnings'],
                'coordinate_distance_m' => $comparison['coordinate_distance_m'],
                'is_selected' => true,
                'selected_at' => now(),
                'selected_by_user_id' => $actor->id,
                'raw_payload' => [
                    'candidates' => $candidates,
                    'selected_reference' => $property->cadastralReference,
                    'provider_raw' => $snapshot->raw_payload['provider_raw'] ?? null,
                ],
            ])->save();

            $snapshot->markAsCurrent();

            $fresh = $snapshot->fresh();
            $this->syncLeadLookupFields($lead, $fresh, successful: true);
            $this->maybeLogMismatchFound($lead, $comparison['status'], $fresh, $actor);

            $this->auditLog->log(
                'catastro_result_selected',
                $lead,
                null,
                [
                    'snapshot_id' => $snapshot->id,
                    'cadastral_reference' => $property->cadastralReference,
                    'status' => $comparison['status']->value,
                ],
                $actor,
            );

            return [
                'snapshot' => $snapshot->fresh(['lookedUpBy', 'selectedBy']),
                'candidates' => [],
            ];
        });
    }

    /**
     * @param  array{status?: string, notes?: string|null}  $payload
     */
    public function auditorReview(Lead $lead, LeadCatastroSnapshot $snapshot, array $payload, User $actor): LeadCatastroSnapshot
    {
        $previous = $snapshot->verification_status?->value;
        $status = CatastroVerificationStatus::from($payload['status']);

        $snapshot->fill([
            'verification_status' => $status,
            'auditor_notes' => $payload['notes'] ?? $snapshot->auditor_notes,
            'auditor_confirmed_at' => now(),
            'auditor_confirmed_by_user_id' => $actor->id,
        ])->save();

        $snapshot->markAsCurrent();

        $fresh = $snapshot->fresh();
        $this->syncLeadLookupFields(
            $lead,
            $fresh,
            successful: in_array($status, [
                CatastroVerificationStatus::Matched,
                CatastroVerificationStatus::PartiallyMatched,
                CatastroVerificationStatus::MismatchDetected,
                CatastroVerificationStatus::ManualReviewRequired,
            ], true),
        );
        $this->maybeLogMismatchFound($lead, $status, $fresh, $actor);

        $this->auditLog->log(
            'catastro_verification_status_changed',
            $lead,
            ['status' => $previous],
            [
                'status' => $status->value,
                'notes' => $payload['notes'] ?? null,
                'snapshot_id' => $snapshot->id,
            ],
            $actor,
        );

        return $snapshot->fresh();
    }

    /**
     * @return array<string, mixed>
     */
    public function presentForLead(Lead $lead, bool $includeProtected = true): array
    {
        try {
            $snapshot = $this->resolveCurrentSnapshot($lead);

            // Seller / non-protected: status + submitted reference only — no snapshot internals.
            if (! $includeProtected) {
                return $this->presentSellerFacing($lead, $snapshot);
            }

            $history = $lead->catastroSnapshots()
                ->orderByDesc('id')
                ->limit(10)
                ->get()
                ->map(fn (LeadCatastroSnapshot $row) => $this->presentSnapshot($row, true))
                ->values()
                ->all();

            $candidates = [];
            if ($snapshot && $snapshot->verification_status === CatastroVerificationStatus::MultiplePropertiesFound) {
                $rawCandidates = $snapshot->raw_payload['candidates'] ?? [];
                if (is_array($rawCandidates)) {
                    $candidates = array_values(array_map(
                        fn ($row) => is_array($row) ? $row : [],
                        $rawCandidates,
                    ));
                }
            }

            return [
                'enabled' => \App\Support\CatastroSettings::enabled(),
                'current' => $snapshot ? $this->presentSnapshot($snapshot, true) : null,
                'candidates' => $candidates,
                'history' => $history,
                'seller_status' => $this->sellerStatus($lead, $snapshot),
                'has_cadastral_reference' => filled($lead->cadastral_reference),
                'lead_submitted' => [
                    'address_line_1' => $lead->address_line_1,
                    'city' => $lead->city,
                    'postcode' => $lead->postcode,
                    'cadastral_reference' => $lead->cadastral_reference,
                    'submitted_property_area_m2' => $lead->submitted_property_area_m2 !== null
                        ? (float) $lead->submitted_property_area_m2
                        : ($lead->size_m2 !== null ? (float) $lead->size_m2 : null),
                ],
                'disclaimer' => 'Catastro describes cadastral characteristics. It is not proof of legal ownership.',
            ];
        } catch (\Throwable $e) {
            report($e);

            if (! $includeProtected) {
                return $this->presentSellerFacing($lead, null);
            }

            return [
                'enabled' => \App\Support\CatastroSettings::enabled(),
                'current' => null,
                'candidates' => [],
                'history' => [],
                'seller_status' => $this->sellerStatus($lead, null),
                'has_cadastral_reference' => filled($lead->cadastral_reference),
                'lead_submitted' => [
                    'address_line_1' => $lead->address_line_1,
                    'city' => $lead->city,
                    'postcode' => $lead->postcode,
                    'cadastral_reference' => $lead->cadastral_reference,
                    'submitted_property_area_m2' => $lead->submitted_property_area_m2 !== null
                        ? (float) $lead->submitted_property_area_m2
                        : ($lead->size_m2 !== null ? (float) $lead->size_m2 : null),
                ],
                'disclaimer' => 'Catastro describes cadastral characteristics. It is not proof of legal ownership.',
            ];
        }
    }

    /**
     * Simplified seller-facing Catastro panel (no snapshot / provider / technical fields).
     *
     * @return array<string, mixed>
     */
    public function presentSellerFacing(Lead $lead, ?LeadCatastroSnapshot $snapshot): array
    {
        return [
            'enabled' => \App\Support\CatastroSettings::enabled(),
            'current' => null,
            'candidates' => [],
            'history' => [],
            'seller_status' => $this->sellerStatus($lead, $snapshot),
            'has_cadastral_reference' => filled($lead->cadastral_reference),
            'lead_submitted' => [
                'address_line_1' => null,
                'city' => null,
                'postcode' => null,
                'cadastral_reference' => $lead->cadastral_reference,
                'submitted_property_area_m2' => null,
            ],
            'disclaimer' => 'Catastro describes cadastral characteristics. It is not proof of legal ownership.',
        ];
    }

    /**
     * Seller-facing simplified status (no internal technical detail).
     *
     * @return array{key: string, label_key: string}
     */
    public function sellerFacingStatus(Lead $lead, ?LeadCatastroSnapshot $snapshot = null): array
    {
        return $this->sellerStatus($lead, $snapshot ?? $this->resolveCurrentSnapshot($lead));
    }

    /**
     * Seller-facing simplified status (no internal technical detail).
     *
     * @return array{key: string, label_key: string}
     */
    private function sellerStatus(Lead $lead, ?LeadCatastroSnapshot $snapshot): array
    {
        if (! filled($lead->cadastral_reference) && ! $snapshot) {
            return ['key' => 'not_checked', 'label_key' => 'not_checked'];
        }

        $status = $snapshot?->verification_status;

        return match ($status) {
            CatastroVerificationStatus::Matched => ['key' => 'verified', 'label_key' => 'verified'],
            CatastroVerificationStatus::PartiallyMatched,
            CatastroVerificationStatus::MismatchDetected,
            CatastroVerificationStatus::MultiplePropertiesFound,
            CatastroVerificationStatus::ManualReviewRequired,
            CatastroVerificationStatus::RegionalProviderRequired => ['key' => 'needs_review', 'label_key' => 'needs_review'],
            CatastroVerificationStatus::LookupFailed,
            CatastroVerificationStatus::PropertyNotFound,
            CatastroVerificationStatus::ServiceUnavailable => ['key' => 'failed', 'label_key' => 'failed'],
            default => ['key' => 'not_checked', 'label_key' => 'not_checked'],
        };
    }

    /**
     * Prefer the marked current snapshot; fall back to latest and repair marker when needed.
     */
    public function resolveCurrentSnapshot(Lead $lead): ?LeadCatastroSnapshot
    {
        if (! LeadCatastroSnapshot::supportsCurrentFlag()) {
            $latest = $lead->latestCatastroSnapshot;

            return $latest && $this->snapshotMatchesLeadReference($lead, $latest)
                ? $latest
                : null;
        }

        try {
            $current = $lead->catastroSnapshots()
                ->current()
                ->orderByDesc('id')
                ->first();

            if ($current && $this->snapshotMatchesLeadReference($lead, $current)) {
                return $current;
            }

            $latest = $lead->catastroSnapshots()
                ->orderByDesc('id')
                ->get()
                ->first(fn (LeadCatastroSnapshot $snapshot) => $this->snapshotMatchesLeadReference($lead, $snapshot));

            if ($latest) {
                $latest->markAsCurrent();

                return $latest->fresh();
            }
        } catch (\Throwable $e) {
            report($e);

            return $lead->latestCatastroSnapshot;
        }

        return null;
    }

    private function snapshotMatchesLeadReference(
        Lead $lead,
        LeadCatastroSnapshot $snapshot,
    ): bool {
        $leadReference = CatastroReferenceNormalizer::tryNormalize($lead->cadastral_reference);
        if ($leadReference === null) {
            return false;
        }

        $snapshotReference = CatastroReferenceNormalizer::tryNormalize($snapshot->cadastral_reference)
            ?? CatastroReferenceNormalizer::tryNormalize(
                is_string($snapshot->search_input['cadastral_reference'] ?? null)
                    ? $snapshot->search_input['cadastral_reference']
                    : null,
            );

        return $snapshotReference === $leadReference;
    }

    /**
     * @param  array<string, mixed>  $searchInput
     * @return array{snapshot: LeadCatastroSnapshot, candidates: list<array<string, mixed>>}
     */
    private function runLookup(
        Lead $lead,
        User $actor,
        CatastroSearchMethod $method,
        array $searchInput,
        CatastroLookupResult $result,
    ): array {
        return DB::transaction(function () use ($lead, $actor, $method, $searchInput, $result) {
            $candidates = array_map(fn (CatastroProperty $p) => $p->toArray(), $result->properties);
            $single = $result->singleProperty();
            $status = $result->status;
            $comparison = null;

            if ($single && $status !== CatastroVerificationStatus::MultiplePropertiesFound) {
                $comparison = $this->comparison->compare($lead, $single, $lead->survey);
                $status = $comparison['status'];
            }

            $attrs = [
                'lead_id' => $lead->id,
                'provider' => $result->provider,
                'search_method' => $method,
                'search_input' => $searchInput,
                'verification_status' => $status,
                'lookup_at' => now(),
                'looked_up_by_user_id' => $actor->id,
                'is_current' => false,
                'is_selected' => $single !== null && $status !== CatastroVerificationStatus::MultiplePropertiesFound,
                'provider_request_id' => $result->providerRequestId,
                'raw_payload' => [
                    'candidates' => $candidates,
                    'provider_raw' => $this->sanitizeRaw($result->raw),
                    'message' => $result->message,
                ],
                'match_summary' => $comparison['match_summary'] ?? $result->message,
                'warnings' => $comparison['warnings'] ?? [],
                'coordinate_distance_m' => $comparison['coordinate_distance_m'] ?? null,
            ];

            if ($single && $status !== CatastroVerificationStatus::MultiplePropertiesFound) {
                $attrs = [...$attrs, ...$this->propertyAttributes($single)];
                $attrs['selected_at'] = now();
                $attrs['selected_by_user_id'] = $actor->id;
            } elseif ($method === CatastroSearchMethod::Reference) {
                $attrs['cadastral_reference'] = $searchInput['cadastral_reference'] ?? $lead->cadastral_reference;
            }

            $snapshot = LeadCatastroSnapshot::query()->create($attrs);
            $snapshot->markAsCurrent();

            $successful = in_array($status, [
                CatastroVerificationStatus::Matched,
                CatastroVerificationStatus::PartiallyMatched,
                CatastroVerificationStatus::MismatchDetected,
                CatastroVerificationStatus::MultiplePropertiesFound,
                CatastroVerificationStatus::ManualReviewRequired,
                CatastroVerificationStatus::RegionalProviderRequired,
            ], true);

            $this->syncLeadLookupFields($lead, $snapshot, successful: $successful);

            $this->auditLog->log(
                $successful ? 'catastro_lookup_succeeded' : 'catastro_lookup_failed',
                $lead,
                null,
                [
                    'snapshot_id' => $snapshot->id,
                    'status' => $status->value,
                    'provider' => $result->provider->value,
                    'candidate_count' => count($candidates),
                ],
                $actor,
            );

            $this->maybeLogMismatchFound($lead, $status, $snapshot, $actor);

            if ($status === CatastroVerificationStatus::MultiplePropertiesFound) {
                $this->auditLog->log(
                    'catastro_multiple_results_returned',
                    $lead,
                    null,
                    ['snapshot_id' => $snapshot->id, 'count' => count($candidates)],
                    $actor,
                );
            }

            return [
                'snapshot' => $snapshot->fresh(['lookedUpBy', 'selectedBy']),
                'candidates' => $status === CatastroVerificationStatus::MultiplePropertiesFound
                    ? $candidates
                    : [],
            ];
        });
    }

    /**
     * @param  array<string, mixed>  $searchInput
     */
    private function logLookupRequested(
        Lead $lead,
        User $actor,
        CatastroSearchMethod $method,
        array $searchInput,
    ): void {
        $this->auditLog->log(
            'catastro_lookup_requested',
            $lead,
            null,
            ['method' => $method->value, 'input' => $searchInput],
            $actor,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function propertyAttributes(CatastroProperty $property): array
    {
        return [
            'cadastral_reference' => $property->cadastralReference,
            'cadastral_address' => $property->cadastralAddress,
            'province' => $property->province,
            'municipality' => $property->municipality,
            'property_use' => $property->propertyUse,
            'constructed_area_m2' => $property->constructedAreaM2,
            'parcel_area_m2' => $property->parcelAreaM2,
            'construction_year' => $property->constructionYear,
            'street_type' => $property->streetType,
            'street_name' => $property->streetName,
            'street_number' => $property->streetNumber,
            'block' => $property->block,
            'staircase' => $property->staircase,
            'floor' => $property->floor,
            'door' => $property->door,
            'postcode' => $property->postcode,
            'unit_label' => $property->unitLabel,
            'geometry' => $property->geometry,
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function propertyFromArray(array $row): CatastroProperty
    {
        return new CatastroProperty(
            cadastralReference: (string) ($row['cadastral_reference'] ?? ''),
            cadastralAddress: $row['cadastral_address'] ?? null,
            province: $row['province'] ?? null,
            municipality: $row['municipality'] ?? null,
            propertyUse: $row['property_use'] ?? null,
            constructedAreaM2: isset($row['constructed_area_m2']) ? (float) $row['constructed_area_m2'] : null,
            parcelAreaM2: isset($row['parcel_area_m2']) ? (float) $row['parcel_area_m2'] : null,
            constructionYear: isset($row['construction_year']) ? (int) $row['construction_year'] : null,
            streetType: $row['street_type'] ?? null,
            streetName: $row['street_name'] ?? null,
            streetNumber: $row['street_number'] ?? null,
            block: $row['block'] ?? null,
            staircase: $row['staircase'] ?? null,
            floor: $row['floor'] ?? null,
            door: $row['door'] ?? null,
            postcode: $row['postcode'] ?? null,
            unitLabel: $row['unit_label'] ?? null,
            geometry: is_array($row['geometry'] ?? null) ? $row['geometry'] : null,
            raw: $row,
        );
    }

    private function syncLeadLookupFields(Lead $lead, LeadCatastroSnapshot $snapshot, bool $successful): void
    {
        $summaryStatus = $this->mapSummaryStatus($snapshot->verification_status);
        $errorMessage = in_array($summaryStatus, ['failed', 'unavailable'], true)
            ? ($snapshot->match_summary
                ?? (is_string($snapshot->raw_payload['message'] ?? null) ? $snapshot->raw_payload['message'] : null))
            : null;

        $lead->forceFill([
            'cadastral_reference' => $snapshot->cadastral_reference ?? $lead->cadastral_reference,
            'cadastral_lookup_status' => $successful
                ? CadastralLookupStatus::Successful
                : match ($snapshot->verification_status) {
                    CatastroVerificationStatus::ServiceUnavailable,
                    CatastroVerificationStatus::LookupFailed,
                    CatastroVerificationStatus::PropertyNotFound => CadastralLookupStatus::Failed,
                    default => CadastralLookupStatus::Pending,
                },
            'cadastral_verified_at' => $successful ? now() : $lead->cadastral_verified_at,
            'catastro_status' => $summaryStatus,
            'catastro_provider' => $snapshot->provider?->value,
            'catastro_checked_at' => $snapshot->lookup_at ?? now(),
            'catastro_matched_address' => $snapshot->cadastral_address,
            'catastro_municipality' => $snapshot->municipality,
            'catastro_province' => $snapshot->province,
            'catastro_postcode' => $snapshot->postcode,
            'catastro_property_type' => $snapshot->property_use,
            'catastro_built_area' => $snapshot->constructed_area_m2,
            'catastro_construction_year' => $snapshot->construction_year,
            'catastro_raw_response_json' => $this->sanitizedRawForLead($snapshot),
            'catastro_warnings_json' => is_array($snapshot->warnings) ? $snapshot->warnings : [],
            'catastro_error_message' => $errorMessage,
        ])->save();
    }

    /**
     * Map rich snapshot verification status onto MVP lead summary statuses.
     */
    private function mapSummaryStatus(?CatastroVerificationStatus $status): string
    {
        return match ($status) {
            CatastroVerificationStatus::Matched => 'matched',
            CatastroVerificationStatus::PartiallyMatched,
            CatastroVerificationStatus::MismatchDetected,
            CatastroVerificationStatus::ManualReviewRequired,
            CatastroVerificationStatus::MultiplePropertiesFound,
            CatastroVerificationStatus::RegionalProviderRequired => 'mismatch',
            CatastroVerificationStatus::LookupFailed,
            CatastroVerificationStatus::PropertyNotFound => 'failed',
            CatastroVerificationStatus::ServiceUnavailable => 'unavailable',
            default => 'not_checked',
        };
    }

    /**
     * Persist only sanitized non-protected provider metadata onto the lead.
     *
     * @return array<string, mixed>|null
     */
    private function sanitizedRawForLead(LeadCatastroSnapshot $snapshot): ?array
    {
        $providerRaw = $snapshot->raw_payload['provider_raw'] ?? null;

        return [
            'provider' => $snapshot->provider?->value,
            'control' => is_array($providerRaw) ? ($providerRaw['control'] ?? null) : null,
            'property' => array_filter([
                'cadastral_reference' => $snapshot->cadastral_reference,
                'cadastral_address' => $snapshot->cadastral_address,
                'municipality' => $snapshot->municipality,
                'province' => $snapshot->province,
                'postcode' => $snapshot->postcode,
                'property_use' => $snapshot->property_use,
                'constructed_area_m2' => $snapshot->constructed_area_m2 !== null
                    ? (float) $snapshot->constructed_area_m2
                    : null,
                'construction_year' => $snapshot->construction_year,
            ], fn (mixed $value) => $value !== null && $value !== ''),
        ];
    }

    private function maybeLogMismatchFound(
        Lead $lead,
        CatastroVerificationStatus $status,
        LeadCatastroSnapshot $snapshot,
        User $actor,
    ): void {
        if (! in_array($status, [
            CatastroVerificationStatus::PartiallyMatched,
            CatastroVerificationStatus::MismatchDetected,
            CatastroVerificationStatus::ManualReviewRequired,
            CatastroVerificationStatus::MultiplePropertiesFound,
            CatastroVerificationStatus::RegionalProviderRequired,
        ], true)) {
            return;
        }

        $this->auditLog->log(
            'catastro_mismatch_found',
            $lead,
            null,
            [
                'snapshot_id' => $snapshot->id,
                'status' => $status->value,
                'summary_status' => $this->mapSummaryStatus($status),
                'warning_count' => is_array($snapshot->warnings) ? count($snapshot->warnings) : 0,
            ],
            $actor,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function presentSnapshot(LeadCatastroSnapshot $snapshot, bool $includeProtected): array
    {
        // Defense in depth: never emit technical snapshot fields without protected access.
        if (! $includeProtected) {
            return [
                'read_only' => true,
                'is_official_ownership_proof' => false,
            ];
        }

        return [
            'id' => $snapshot->id,
            'provider' => $snapshot->provider?->value,
            'verification_status' => $snapshot->verification_status?->value,
            'search_method' => $snapshot->search_method?->value,
            'cadastral_reference' => $snapshot->cadastral_reference,
            'cadastral_address' => $snapshot->cadastral_address,
            'province' => $snapshot->province,
            'municipality' => $snapshot->municipality,
            'postcode' => $snapshot->postcode,
            'property_use' => $snapshot->property_use,
            'constructed_area_m2' => $snapshot->constructed_area_m2 !== null ? (float) $snapshot->constructed_area_m2 : null,
            'parcel_area_m2' => $snapshot->parcel_area_m2 !== null ? (float) $snapshot->parcel_area_m2 : null,
            'construction_year' => $snapshot->construction_year,
            'floor' => $snapshot->floor,
            'door' => $snapshot->door,
            'unit_label' => $snapshot->unit_label,
            'match_summary' => $snapshot->match_summary,
            'error_message' => in_array($snapshot->verification_status, [
                CatastroVerificationStatus::LookupFailed,
                CatastroVerificationStatus::ServiceUnavailable,
                CatastroVerificationStatus::PropertyNotFound,
            ], true) ? $snapshot->match_summary : null,
            'warnings' => $snapshot->warnings ?? [],
            'warning_count' => is_array($snapshot->warnings) ? count($snapshot->warnings) : 0,
            'lookup_at' => optional($snapshot->lookup_at)?->toIso8601String(),
            'is_current' => (bool) $snapshot->is_current,
            'is_selected' => (bool) $snapshot->is_selected,
            'auditor_notes' => $snapshot->auditor_notes,
            'auditor_confirmed_at' => optional($snapshot->auditor_confirmed_at)?->toIso8601String(),
            'coordinate_distance_m' => $snapshot->coordinate_distance_m !== null
                ? (float) $snapshot->coordinate_distance_m
                : null,
            'looked_up_by' => $snapshot->lookedUpBy?->only(['id', 'name']),
            'read_only' => true,
            'is_official_ownership_proof' => false,
        ];
    }

    /**
     * Strip provider payloads down to non-protected control metadata.
     * Never persist owner, titular, valor, or full property dumps on the lead.
     *
     * @param  array<string, mixed>|null  $raw
     * @return array<string, mixed>|null
     */
    private function sanitizeRaw(?array $raw): ?array
    {
        if ($raw === null) {
            return null;
        }

        // If already sanitized (keys/control only), keep as-is.
        if (array_key_exists('keys', $raw) && count($raw) <= 2) {
            return [
                'keys' => is_array($raw['keys'] ?? null) ? array_values($raw['keys']) : [],
                'control' => $raw['control'] ?? null,
            ];
        }

        // Keep structure for debugging without dumping protected or bulky fields.
        return [
            'keys' => array_keys($raw),
            'control' => $raw['consulta_dnprcResult']['control']
                ?? $raw['consulta_dnplocResult']['control']
                ?? $raw['consulta_dnp']['control']
                ?? $raw['consulta_dnprc']['control']
                ?? $raw['consulta_dnploc']['control']
                ?? null,
        ];
    }
}
