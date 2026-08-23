<?php

namespace App\Services\Admin;

use App\Enums\GeocodingStatus;
use App\Enums\LeadStatus;
use App\Models\Company;
use App\Models\Lead;
use App\Models\LeadMetricValue;
use App\Models\SchemeField;
use App\Models\User;
use App\Models\Zone;
use App\Services\AuditLogService;
use App\Services\LeadSubmissionService;
use App\Services\LocationService;
use App\Support\LeadSettings;
use App\Support\ReferenceGenerator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AdminLeadCreationService
{
    public const SOURCE_SELLER_COMPANY = 'seller_company';

    public const SOURCE_SELLER_AGENT = 'seller_agent';

    public const SOURCE_RML_INTERNAL = 'rml_internal';

    public function __construct(
        private readonly AuditLogService $auditLogService,
        private readonly LocationService $locationService,
        private readonly LeadSubmissionService $leadSubmission,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, UploadedFile|list<UploadedFile>|null>  $uploadedFiles
     */
    public function create(User $admin, array $data, bool $asDraft, array $uploadedFiles = []): Lead
    {
        return DB::transaction(function () use ($admin, $data, $asDraft, $uploadedFiles) {
            $source = (string) ($data['lead_source'] ?? self::SOURCE_RML_INTERNAL);
            if (! in_array($source, [
                self::SOURCE_SELLER_COMPANY,
                self::SOURCE_SELLER_AGENT,
                self::SOURCE_RML_INTERNAL,
            ], true)) {
                throw ValidationException::withMessages([
                    'lead_source' => 'Invalid lead source.',
                ]);
            }
            if ($source === self::SOURCE_SELLER_COMPANY && ! LeadSettings::allowSellerCompanyLeads()) {
                throw ValidationException::withMessages([
                    'lead_source' => __('rml.admin.settings.leads_packages.seller_company_disabled'),
                ]);
            }
            if ($source === self::SOURCE_SELLER_AGENT && ! LeadSettings::allowSellerAgentLeads()) {
                throw ValidationException::withMessages([
                    'lead_source' => __('rml.admin.settings.leads_packages.seller_agent_disabled'),
                ]);
            }

            $size = isset($data['size_m2']) && $data['size_m2'] !== '' && $data['size_m2'] !== null
                ? (float) $data['size_m2']
                : null;
            if ($size !== null && ! LeadSettings::isAreaWithinLimits($size)) {
                throw ValidationException::withMessages([
                    'size_m2' => __('rml.admin.settings.leads_packages.property_area_out_of_range', [
                        'min' => LeadSettings::minPropertyAreaM2(),
                        'max' => LeadSettings::maxPropertyAreaM2(),
                    ]),
                ]);
            }

            [$sellerCompanyId, $submittedByUserId] = $this->resolveSellerContext($source, $data, $admin);

            $metrics = is_array($data['metrics'] ?? null) ? $data['metrics'] : [];
            if (! empty($data['occupancy_type'])) {
                $metrics['occupancy_type'] = $data['occupancy_type'];
            }
            if (! empty($data['epc_rating']) && empty($metrics['epc_rating'])) {
                $metrics['epc_rating'] = $data['epc_rating'];
            }
            if (
                (! array_key_exists('size_m2', $data) || $data['size_m2'] === null || $data['size_m2'] === '')
                && isset($metrics['size_m2'])
            ) {
                $data['size_m2'] = $metrics['size_m2'];
            }
            if (
                (! array_key_exists('size_m2', $data) || $data['size_m2'] === null || $data['size_m2'] === '')
                && isset($metrics['area_m2'])
            ) {
                $data['size_m2'] = $metrics['area_m2'];
            }

            $zoneId = ! empty($data['zone_id']) ? (int) $data['zone_id'] : null;
            if (! $zoneId && ! empty($data['zone_code']) && ! empty($data['scheme_id'])) {
                $zoneId = Zone::query()
                    ->where('scheme_id', $data['scheme_id'])
                    ->where('code', $data['zone_code'])
                    ->value('id');
            }

            $lead = new Lead([
                'lead_reference' => ReferenceGenerator::lead(),
                'submitted_by_user_id' => $submittedByUserId,
                'seller_company_id' => $sellerCompanyId,
                'lead_source' => $source,
                'scheme_id' => $data['scheme_id'],
                'zone_id' => $zoneId,
                'customer_first_name' => $data['customer_first_name'] ?? '',
                'customer_last_name' => $data['customer_last_name'] ?? '',
                'customer_phone' => $data['customer_phone'] ?? '',
                'customer_whatsapp' => $data['customer_whatsapp'] ?? null,
                'customer_email' => $data['customer_email'] ?? null,
                'address_line_1' => $data['address_line_1'] ?? '',
                'address_line_2' => $data['address_line_2'] ?? null,
                'city' => $data['city'] ?? '',
                'postcode' => $data['postcode'] ?? '',
                'country' => $data['country'] ?? 'ES',
                'cadastral_reference' => $data['cadastral_reference'] ?? null,
                'size_m2' => $data['size_m2'] ?? $metrics['size_m2'] ?? null,
                'property_type' => $data['property_type'] ?? null,
                'epc_rating' => $data['epc_rating'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            if ($source === self::SOURCE_RML_INTERNAL) {
                $lead->buying_price = array_key_exists('buying_price', $data) && $data['buying_price'] !== null && $data['buying_price'] !== ''
                    ? (float) $data['buying_price']
                    : 0.0;
            }

            $manualLat = isset($data['latitude']) && is_numeric($data['latitude'])
                ? (float) $data['latitude']
                : null;
            $manualLng = isset($data['longitude']) && is_numeric($data['longitude'])
                ? (float) $data['longitude']
                : null;

            if ($manualLat !== null && $manualLng !== null) {
                $lead->fill([
                    'latitude' => $manualLat,
                    'longitude' => $manualLng,
                    'geocoding_status' => GeocodingStatus::ManuallyCorrected,
                    'geocoded_at' => now(),
                ]);
            }

            $lead->status = $asDraft ? LeadStatus::Draft : LeadStatus::PendingValidation;
            $lead->save();

            if ($manualLat === null || $manualLng === null) {
                $address = trim(($lead->address_line_1 ?? '').($lead->city ?? '').($lead->postcode ?? ''));
                if ($address !== '') {
                    $this->locationService->geocodeLead($lead);
                }
            }

            $this->syncMetricValues($lead, (int) $lead->scheme_id, $metrics);

            $hasUploads = collect($uploadedFiles)->filter()->isNotEmpty();
            if ($hasUploads) {
                $this->leadSubmission->addEvidence($admin, $lead, $uploadedFiles);
                $lead->refresh();
            }

            if (! $asDraft && ! $this->leadSubmission->hasAllRequiredEvidence($lead)) {
                $lead->status = LeadStatus::PendingEvidence;
                $lead->save();
            }

            $this->auditLogService->log(
                'lead.created_by_admin',
                $lead,
                null,
                [
                    'status' => $lead->status?->value,
                    'lead_source' => $source,
                    'as_draft' => $asDraft,
                    'seller_company_id' => $sellerCompanyId,
                    'created_by_admin_id' => $admin->id,
                ],
                $admin,
            );

            return $lead->fresh(['scheme', 'zone', 'sellerCompany', 'submittedBy']);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{0: int|null, 1: int}
     */
    private function resolveSellerContext(string $source, array $data, User $admin): array
    {
        if ($source === self::SOURCE_RML_INTERNAL) {
            return [null, $admin->id];
        }

        $companyId = isset($data['seller_company_id']) ? (int) $data['seller_company_id'] : null;
        $agentId = isset($data['seller_user_id']) ? (int) $data['seller_user_id'] : null;

        if ($source === self::SOURCE_SELLER_COMPANY) {
            if (! $companyId) {
                throw ValidationException::withMessages([
                    'seller_company_id' => 'Select a seller company.',
                ]);
            }

            abort_unless(Company::query()->whereKey($companyId)->exists(), 422);

            $submittedBy = $agentId
                ?: User::query()
                    ->whereHas('sellerProfile', fn ($q) => $q->where('company_id', $companyId))
                    ->value('id');

            return [$companyId, $submittedBy ? (int) $submittedBy : $admin->id];
        }

        if (! $agentId) {
            throw ValidationException::withMessages([
                'seller_user_id' => 'Select a seller agent.',
            ]);
        }

        $agent = User::query()->with('sellerProfile')->find($agentId);
        if (! $agent?->sellerProfile) {
            throw ValidationException::withMessages([
                'seller_user_id' => 'Seller agent not found.',
            ]);
        }

        return [$agent->sellerProfile->company_id, $agent->id];
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
}
