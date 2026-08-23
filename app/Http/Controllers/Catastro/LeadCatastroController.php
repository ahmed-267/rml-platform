<?php

namespace App\Http\Controllers\Catastro;

use App\Enums\CatastroVerificationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Catastro\LookupCatastroByAddressRequest;
use App\Http\Requests\Catastro\LookupCatastroByReferenceRequest;
use App\Http\Requests\Catastro\ReviewCatastroSnapshotRequest;
use App\Http\Requests\Catastro\UpdateLeadCadastralReferenceRequest;
use App\Models\Lead;
use App\Models\LeadCatastroSnapshot;
use App\Services\AuditLogService;
use App\Services\Catastro\CatastroLookupService;
use App\Services\Catastro\CatastroReferenceNormalizer;
use App\Support\Permissions;
use App\Enums\CadastralLookupStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class LeadCatastroController extends Controller
{
    public function __construct(
        private readonly CatastroLookupService $catastroLookup,
        private readonly AuditLogService $auditLog,
    ) {}

    /**
     * Check Catastro using the lead's stored cadastral reference (admin/auditor).
     */
    public function check(Request $request, Lead $lead): RedirectResponse
    {
        $this->authorizeLookup($request, $lead);

        $reference = trim((string) ($lead->cadastral_reference ?? ''));
        if ($reference === '') {
            return back()->with('error', __('rml.catastro.errors.no_stored_reference'));
        }

        try {
            $result = $this->catastroLookup->lookupByReference(
                $lead,
                $reference,
                $request->user(),
            );
        } catch (InvalidArgumentException $e) {
            $key = 'rml.catastro.errors.'.$e->getMessage();
            $message = __($key);
            if ($message === $key) {
                $message = __('rml.catastro.errors.cadastral_reference_invalid');
            }

            return back()->with('error', $message);
        }

        $snapshot = $result['snapshot'];
        $status = $snapshot->verification_status;

        if ($this->isLookupErrorStatus($status)) {
            $message = filled($snapshot->match_summary)
                ? (string) $snapshot->match_summary
                : __('rml.catastro.flash.check_failed');

            return back()->with('error', $message);
        }

        return back()->with([
            'success' => __('rml.catastro.flash.check_complete'),
            'catastro_candidates' => $result['candidates'],
            'catastro_snapshot_id' => $snapshot->id,
        ]);
    }

    public function lookupByReference(LookupCatastroByReferenceRequest $request, Lead $lead): RedirectResponse
    {
        $this->authorizeLookup($request, $lead);

        try {
            $result = $this->catastroLookup->lookupByReference(
                $lead,
                $request->string('cadastral_reference')->toString(),
                $request->user(),
            );
        } catch (InvalidArgumentException $e) {
            $key = 'rml.catastro.errors.'.$e->getMessage();
            $message = __($key);
            if ($message === $key) {
                $message = __('rml.catastro.errors.cadastral_reference_invalid');
            }

            return back()->withErrors(['cadastral_reference' => $message]);
        }

        return back()->with([
            'success' => __('rml.catastro.flash.lookup_complete'),
            'catastro_candidates' => $result['candidates'],
            'catastro_snapshot_id' => $result['snapshot']->id,
        ]);
    }

    public function updateReference(
        UpdateLeadCadastralReferenceRequest $request,
        Lead $lead,
    ): RedirectResponse {
        $this->authorizeLookup($request, $lead);

        $rawReference = trim((string) $request->input('cadastral_reference', ''));

        try {
            $reference = $rawReference === ''
                ? null
                : CatastroReferenceNormalizer::normalize($rawReference);
        } catch (InvalidArgumentException $e) {
            $key = 'rml.catastro.errors.'.$e->getMessage();
            $message = __($key);

            return back()->withErrors([
                'cadastral_reference' => $message === $key
                    ? __('rml.catastro.errors.cadastral_reference_invalid')
                    : $message,
            ]);
        }

        $previousReference = $lead->cadastral_reference;

        if ($previousReference === $reference) {
            return back()->with('success', __('rml.catastro.flash.reference_saved'));
        }

        DB::transaction(function () use ($lead, $reference, $previousReference, $request): void {
            $lead->forceFill([
                'cadastral_reference' => $reference,
                'cadastral_lookup_status' => CadastralLookupStatus::NotLookedUp,
                'cadastral_verified_at' => null,
                'catastro_status' => 'not_checked',
                'catastro_provider' => null,
                'catastro_checked_at' => null,
                'catastro_matched_address' => null,
                'catastro_municipality' => null,
                'catastro_province' => null,
                'catastro_postcode' => null,
                'catastro_property_type' => null,
                'catastro_built_area' => null,
                'catastro_construction_year' => null,
                'catastro_raw_response_json' => null,
                'catastro_warnings_json' => null,
                'catastro_error_message' => null,
            ])->save();

            if (LeadCatastroSnapshot::supportsCurrentFlag()) {
                $lead->catastroSnapshots()->where('is_current', true)->update(['is_current' => false]);
            }

            $this->auditLog->log(
                'catastro_reference_updated',
                $lead,
                ['cadastral_reference' => $previousReference],
                ['cadastral_reference' => $reference],
                $request->user(),
            );
        });

        return back()->with('success', __('rml.catastro.flash.reference_saved'));
    }

    public function lookupByAddress(LookupCatastroByAddressRequest $request, Lead $lead): RedirectResponse
    {
        $this->authorizeLookup($request, $lead);

        $result = $this->catastroLookup->lookupByAddress(
            $lead,
            $request->validated(),
            $request->user(),
        );

        return back()->with([
            'success' => __('rml.catastro.flash.lookup_complete'),
            'catastro_candidates' => $result['candidates'],
            'catastro_snapshot_id' => $result['snapshot']->id,
        ]);
    }

    public function selectResult(Request $request, Lead $lead, LeadCatastroSnapshot $snapshot): RedirectResponse
    {
        $this->authorizeLookup($request, $lead);
        abort_unless($snapshot->lead_id === $lead->id, 404);

        $request->validate([
            'cadastral_reference' => ['required', 'string', 'max:40'],
        ]);

        try {
            $this->catastroLookup->selectResult(
                $lead,
                $snapshot,
                $request->string('cadastral_reference')->toString(),
                $request->user(),
            );
        } catch (InvalidArgumentException) {
            return back()->withErrors([
                'cadastral_reference' => __('rml.catastro.errors.candidate_not_found'),
            ]);
        }

        return back()->with('success', __('rml.catastro.flash.result_selected'));
    }

    public function review(ReviewCatastroSnapshotRequest $request, Lead $lead, LeadCatastroSnapshot $snapshot): RedirectResponse
    {
        abort_unless($request->user()?->can(Permissions::REVIEW_CATASTRO), 403);
        $this->authorize('view', $lead);
        abort_unless($snapshot->lead_id === $lead->id, 404);

        $this->catastroLookup->auditorReview(
            $lead,
            $snapshot,
            $request->validated(),
            $request->user(),
        );

        return back()->with('success', __('rml.catastro.flash.review_saved'));
    }

    private function authorizeLookup(Request $request, Lead $lead): void
    {
        $user = $request->user();
        abort_unless($user?->can(Permissions::LOOKUP_CATASTRO), 403);
        $this->authorize('view', $lead);
    }

    private function isLookupErrorStatus(mixed $status): bool
    {
        return in_array($status, [
            CatastroVerificationStatus::LookupFailed,
            CatastroVerificationStatus::ServiceUnavailable,
            CatastroVerificationStatus::PropertyNotFound,
        ], true);
    }
}
