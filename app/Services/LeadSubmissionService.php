<?php

namespace App\Services;

use App\Enums\EvidenceFileType;
use App\Enums\EvidenceStatus;
use App\Enums\EvidenceVisibility;
use App\Enums\LeadStatus;
use App\Models\Lead;
use App\Models\LeadEvidenceFile;
use App\Models\LeadMetricValue;
use App\Models\SchemeField;
use App\Models\User;
use App\Models\Zone;
use App\Support\LeadEvidenceAccess;
use App\Support\FilesystemDisk;
use App\Support\ReferenceGenerator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LeadSubmissionService
{
    /**
     * @var list<EvidenceFileType>
     */
    public const REQUIRED_EVIDENCE_TYPES = [
        EvidenceFileType::Photo,
        EvidenceFileType::SignedHomeownerAgreement,
    ];

    public function __construct(
        private readonly AuditLogService $auditLogService,
    ) {}

    /**
     * @return list<EvidenceFileType>
     */
    public function requiredEvidenceTypes(?string $schemeSlug = null): array
    {
        return LeadEvidenceAccess::requiredTypesForScheme($schemeSlug);
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, UploadedFile|list<UploadedFile>|null>  $uploadedFiles
     */
    public function submit(User $user, array $data, bool $asDraft, array $uploadedFiles = []): Lead
    {
        return DB::transaction(function () use ($user, $data, $asDraft, $uploadedFiles) {
            $profile = $user->sellerProfile;
            $isUpdate = ! empty($data['lead_id']);

            if ($isUpdate) {
                $lead = Lead::query()->lockForUpdate()->findOrFail($data['lead_id']);

                if ($lead->submitted_by_user_id !== $user->id && ! $user->can('update', $lead)) {
                    abort(403);
                }

                if (! in_array($lead->status, [LeadStatus::Draft, LeadStatus::PendingEvidence], true)) {
                    throw ValidationException::withMessages([
                        'lead_id' => 'Only draft or pending-evidence leads can be updated this way.',
                    ]);
                }
            } else {
                $lead = new Lead([
                    'lead_reference' => ReferenceGenerator::lead(),
                    'submitted_by_user_id' => $user->id,
                    'seller_company_id' => $profile?->company_id,
                ]);
            }

            $metrics = is_array($data['metrics'] ?? null) ? $data['metrics'] : [];
            $zoneId = $this->resolveZoneId($data, $metrics);

            $lead->fill([
                'scheme_id' => $data['scheme_id'],
                'zone_id' => $zoneId,
                'customer_first_name' => $data['customer_first_name'] ?? $data['first_name'] ?? '',
                'customer_last_name' => $data['customer_last_name'] ?? $data['last_name'] ?? '',
                'customer_phone' => $data['customer_phone'] ?? $data['phone'] ?? '',
                'customer_whatsapp' => $data['customer_whatsapp'] ?? $data['whatsapp'] ?? null,
                'customer_email' => $data['customer_email'] ?? $data['email'] ?? null,
                'address_line_1' => $data['address_line_1'] ?? '',
                'address_line_2' => $data['address_line_2'] ?? null,
                'city' => $data['city'] ?? '',
                'postcode' => $data['postcode'] ?? '',
                'country' => $data['country'] ?? 'ES',
                'latitude' => $data['latitude'] ?? null,
                'longitude' => $data['longitude'] ?? null,
                'size_m2' => $data['size_m2'] ?? $metrics['size_m2'] ?? $metrics['property_size_m2'] ?? null,
                'property_type' => $data['property_type'] ?? $metrics['property_type'] ?? null,
                'epc_rating' => $data['epc_rating'] ?? $metrics['epc_rating'] ?? null,
                'notes' => $data['notes'] ?? $metrics['notes'] ?? null,
            ]);

            if (! $isUpdate) {
                $lead->submitted_by_user_id = $user->id;
                $lead->seller_company_id = $profile?->company_id;
            }

            $lead->save();

            $this->syncMetricValues($lead, (int) $lead->scheme_id, $metrics);
            $this->storeEvidenceFiles($user, $lead, $uploadedFiles);

            $lead->refresh()->load('evidenceFiles');

            if ($asDraft) {
                $lead->status = LeadStatus::Draft;
                $lead->save();

                $this->auditLogService->log(
                    $isUpdate ? 'lead.draft_saved' : 'lead.created',
                    $lead,
                    null,
                    ['status' => LeadStatus::Draft->value, 'as_draft' => true],
                    $user,
                );
            } else {
                $lead->loadMissing('scheme:id,slug');
                if (! $this->hasAllRequiredEvidence($lead)) {
                    throw ValidationException::withMessages([
                        'evidence' => __('rml.seller.leads.evidence_incomplete'),
                    ]);
                }

                $status = LeadStatus::PendingValidation;
                $lead->status = $status;
                $lead->save();

                if (! $isUpdate) {
                    $this->auditLogService->log(
                        'lead.created',
                        $lead,
                        null,
                        ['status' => $status->value],
                        $user,
                    );
                }

                $this->auditLogService->log(
                    'lead.submitted',
                    $lead,
                    null,
                    ['status' => $status->value, 'as_draft' => false],
                    $user,
                );
            }

            return $lead->fresh([
                'scheme',
                'zone',
                'metricValues',
                'evidenceFiles',
                'audits',
            ]);
        });
    }

    /**
     * @param  array<string, UploadedFile|list<UploadedFile>|null>  $filesByType
     */
    public function addEvidence(User $user, Lead $lead, array $filesByType): Lead
    {
        return DB::transaction(function () use ($user, $lead, $filesByType) {
            $this->storeEvidenceFiles($user, $lead, $filesByType);

            $lead->refresh()->load('evidenceFiles');

            if (
                $this->hasAllRequiredEvidence($lead)
                && in_array($lead->status, [
                    LeadStatus::PendingEvidence,
                    LeadStatus::NeedsMoreInformation,
                ], true)
            ) {
                $oldStatus = $lead->status->value;
                $lead->status = LeadStatus::PendingValidation;
                $lead->save();

                $this->auditLogService->log(
                    'lead.evidence_added',
                    $lead,
                    ['status' => $oldStatus],
                    ['status' => LeadStatus::PendingValidation->value],
                    $user,
                );
            } else {
                $this->auditLogService->log(
                    'lead.evidence_added',
                    $lead,
                    null,
                    ['status' => $lead->status->value],
                    $user,
                );
            }

            return $lead->fresh([
                'scheme',
                'zone',
                'metricValues',
                'evidenceFiles',
                'audits',
            ]);
        });
    }

    public function hasAllRequiredEvidence(Lead $lead): bool
    {
        $lead->loadMissing(['evidenceFiles', 'scheme:id,slug']);

        $present = $lead->evidenceFiles
            ->pluck('file_type')
            ->map(fn ($type) => $type instanceof EvidenceFileType ? $type->value : (string) $type)
            ->unique()
            ->all();

        foreach ($this->requiredEvidenceTypes($lead->scheme?->slug) as $required) {
            if (! in_array($required->value, $present, true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $metrics
     */
    private function resolveZoneId(array $data, array $metrics): ?int
    {
        if (! empty($data['zone_id'])) {
            return (int) $data['zone_id'];
        }

        $zoneCode = $data['zone_code']
            ?? $metrics['zone_code']
            ?? $metrics['zone']
            ?? $metrics['zone_context']
            ?? null;

        if (! is_string($zoneCode) || $zoneCode === '' || empty($data['scheme_id'])) {
            return null;
        }

        return Zone::query()
            ->where('scheme_id', $data['scheme_id'])
            ->where('code', $zoneCode)
            ->value('id');
    }

    /**
     * @param  array<string, mixed>  $metrics
     */
    private function syncMetricValues(Lead $lead, int $schemeId, array $metrics): void
    {
        if ($metrics === []) {
            return;
        }

        $fields = SchemeField::query()
            ->where('scheme_id', $schemeId)
            ->where('active', true)
            ->get()
            ->keyBy('key');

        foreach ($metrics as $key => $value) {
            if (! is_string($key) || $key === '') {
                continue;
            }

            // Skip keys that map to lead columns / zone resolution.
            if (in_array($key, ['zone', 'zone_code', 'zone_context', 'size_m2', 'property_type', 'epc_rating', 'notes'], true)) {
                continue;
            }

            $field = $fields->get($key);

            LeadMetricValue::query()->updateOrCreate(
                [
                    'lead_id' => $lead->id,
                    'key' => $key,
                ],
                [
                    'scheme_field_id' => $field?->id,
                    'value' => is_scalar($value) || $value === null
                        ? (string) ($value ?? '')
                        : json_encode($value),
                ],
            );
        }
    }

    /**
     * @param  array<string, UploadedFile|list<UploadedFile>|null>  $uploadedFiles
     */
    private function storeEvidenceFiles(User $user, Lead $lead, array $uploadedFiles): void
    {
        $typeMap = [
            'photo' => EvidenceFileType::Photo,
            'evidence_photos' => EvidenceFileType::Photo,
            'video' => EvidenceFileType::Video,
            'evidence_video' => EvidenceFileType::Video,
            'signed_homeowner_agreement' => EvidenceFileType::SignedHomeownerAgreement,
            'evidence_agreement' => EvidenceFileType::SignedHomeownerAgreement,
            'eligibility_document' => EvidenceFileType::EligibilityDocument,
            'evidence_eligibility' => EvidenceFileType::EligibilityDocument,
        ];

        foreach ($uploadedFiles as $key => $files) {
            if ($files === null) {
                continue;
            }

            $type = $typeMap[$key] ?? (EvidenceFileType::tryFrom((string) $key));

            if (! $type instanceof EvidenceFileType) {
                continue;
            }

            $fileList = is_array($files) ? $files : [$files];

            foreach ($fileList as $file) {
                if (! $file instanceof UploadedFile) {
                    continue;
                }

                $disk = FilesystemDisk::uploads();
                $path = $file->store("lead-evidence/{$lead->id}", $disk);

                LeadEvidenceFile::query()->create([
                    'lead_id' => $lead->id,
                    'uploaded_by_user_id' => $user->id,
                    'file_type' => $type,
                    'original_name' => $file->getClientOriginalName(),
                    'path' => $path,
                    'disk' => $disk,
                    'mime_type' => $file->getClientMimeType(),
                    'size' => $file->getSize(),
                    'visibility' => EvidenceVisibility::Private,
                    'status' => EvidenceStatus::Uploaded,
                ]);
            }
        }
    }
}
