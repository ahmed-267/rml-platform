<?php

namespace App\Http\Controllers\Survey;

use App\Enums\SurveyStatus;
use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\LeadSurvey;
use App\Models\LeadSurveyEvidence;
use App\Services\Survey\LeadSurveyService;
use App\Support\Permissions;
use App\Support\SurveyFieldConfig;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LeadSurveyController extends Controller
{
    public function __construct(
        private readonly LeadSurveyService $surveys,
    ) {}

    public function start(Request $request, Lead $lead): RedirectResponse
    {
        $this->authorize('conduct', [LeadSurvey::class, $lead]);

        $this->surveys->ensureForLead($lead, $request->user());

        return redirect()->route($this->routeName($request, 'show'), $lead);
    }

    public function show(Request $request, Lead $lead): Response
    {
        $survey = LeadSurvey::query()->where('lead_id', $lead->id)->first();

        if (! $survey) {
            $this->authorize('conduct', [LeadSurvey::class, $lead]);
            $survey = $this->surveys->ensureForLead($lead, $request->user());
        } else {
            $this->authorize('view', $survey);
        }

        $includeInternal = $request->user()->can(Permissions::REVIEW_SURVEYS)
            || $request->user()->hasRole('super_admin');
        $canEdit = $request->user()->can('update', $survey);

        return Inertia::render('Surveys/Wizard', [
            'survey' => $this->surveys->present($survey, $includeInternal),
            'options' => $this->options($lead),
            'portal' => $this->portal($request),
            'can_review' => $includeInternal,
            'can_edit' => $canEdit,
            'routes' => $this->routeMap($request, $lead),
            'return_href' => $this->returnHref($request, $lead),
            'breadcrumbs' => $this->breadcrumbTrail($request, $lead),
        ]);
    }

    public function storeDraft(Request $request, Lead $lead): RedirectResponse
    {
        $survey = $this->surveys->ensureForLead($lead, $request->user());
        $this->authorize('update', $survey);

        $data = $this->validatedPayload($request);
        $data['intent'] = $request->input('intent', 'draft');
        $data['advance_to'] = $request->input('advance_to');

        $this->surveys->saveDraft($survey, $request->user(), $data, false);

        $message = $data['intent'] === 'continue'
            ? __('rml.survey.step_saved')
            : __('rml.survey.draft_saved');

        return back()->with('success', $message);
    }

    public function submit(Request $request, Lead $lead): RedirectResponse
    {
        $survey = LeadSurvey::query()->where('lead_id', $lead->id)->firstOrFail();
        $this->authorize('update', $survey);

        $data = $this->validatedPayload($request);
        $this->surveys->submit($survey, $request->user(), $data);

        return redirect()
            ->to($this->returnHref($request, $lead))
            ->with('success', __('rml.survey.submitted_flash'));
    }

    public function uploadEvidence(Request $request, Lead $lead): RedirectResponse
    {
        $survey = LeadSurvey::query()->where('lead_id', $lead->id)->firstOrFail();
        $this->authorize('update', $survey);

        $validated = $request->validate([
            'file' => ['required', 'file', 'max:20480', 'mimes:jpg,jpeg,png,webp,pdf,heic'],
            'category' => ['required', 'string', 'max:80'],
            'survey_section' => ['nullable', 'string', 'max:80'],
            'measurement_section_id' => ['nullable', 'integer'],
            'caption' => ['nullable', 'string', 'max:500'],
            'for_ai_measurement' => ['nullable', 'boolean'],
        ]);

        $this->surveys->storeEvidence(
            $survey,
            $request->user(),
            $validated['file'],
            $validated,
        );

        return back()->with('success', __('rml.survey.evidence_uploaded'));
    }

    public function destroyEvidence(Request $request, Lead $lead, LeadSurveyEvidence $evidence): RedirectResponse
    {
        $survey = LeadSurvey::query()->where('lead_id', $lead->id)->firstOrFail();
        $this->authorize('update', $survey);
        abort_unless((int) $evidence->lead_survey_id === (int) $survey->id, 404);

        $evidence->delete();

        return back()->with('success', __('rml.survey.evidence_removed'));
    }

    public function requestCorrection(Request $request, Lead $lead): RedirectResponse
    {
        $survey = LeadSurvey::query()->where('lead_id', $lead->id)->firstOrFail();
        $this->authorize('review', $survey);

        $data = $request->validate([
            'correction_request' => ['required', 'string', 'max:5000'],
            'correction_sections' => ['nullable', 'array'],
            'correction_sections.*' => ['string', Rule::in(LeadSurveyService::STEPS)],
        ]);

        $this->surveys->requestCorrection($survey, $request->user(), $data);

        return back()->with('success', __('rml.survey.correction_requested_flash'));
    }

    public function approve(Request $request, Lead $lead): RedirectResponse
    {
        $survey = LeadSurvey::query()->where('lead_id', $lead->id)->firstOrFail();
        $this->authorize('review', $survey);

        $data = $request->validate([
            'auditor_approved_area_m2' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'auditor_approved_installation_area_m2' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'auditor_decision_notes' => ['nullable', 'string', 'max:5000'],
            'internal_audit_notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $this->surveys->approve($survey, $request->user(), $data);

        return back()->with('success', __('rml.survey.approved_flash'));
    }

    public function reject(Request $request, Lead $lead): RedirectResponse
    {
        $survey = LeadSurvey::query()->where('lead_id', $lead->id)->firstOrFail();
        $this->authorize('review', $survey);

        $data = $request->validate([
            'auditor_decision_notes' => ['required', 'string', 'max:5000'],
        ]);

        $this->surveys->reject($survey, $request->user(), $data['auditor_decision_notes']);

        return back()->with('success', __('rml.survey.rejected_flash'));
    }

    public function downloadEvidence(Request $request, Lead $lead, LeadSurveyEvidence $evidence): StreamedResponse
    {
        $survey = LeadSurvey::query()->where('lead_id', $lead->id)->firstOrFail();
        $this->authorize('view', $survey);
        abort_unless((int) $evidence->lead_survey_id === (int) $survey->id, 404);

        return Storage::disk($evidence->disk)
            ->download($evidence->path, $evidence->original_name ?: 'evidence');
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedPayload(Request $request): array
    {
        return $request->validate([
            'intent' => ['nullable', 'string', Rule::in(['draft', 'continue'])],
            'advance_to' => ['nullable', 'string', Rule::in(LeadSurveyService::STEPS)],
            'current_step' => ['nullable', 'string', Rule::in(LeadSurveyService::STEPS)],
            'survey_date' => ['nullable', 'date'],
            'confirmed_address' => ['nullable', 'string', 'max:500'],
            'property_type' => ['nullable', 'string', Rule::in(SurveyFieldConfig::options()['property_types'])],
            'occupancy_type' => ['nullable', 'string', Rule::in(SurveyFieldConfig::options()['occupancy_types'])],
            'number_of_floors' => ['nullable', 'integer', 'min:0', 'max:100'],
            'approx_construction_year' => ['nullable', 'integer', 'min:1800', 'max:2100'],
            'occupied' => ['nullable', 'boolean'],
            'homeowner_present' => ['nullable', 'boolean'],
            'general_condition' => ['nullable', 'string', Rule::in(SurveyFieldConfig::options()['general_conditions'])],
            'location_confirmed' => ['nullable', 'boolean'],
            'discrepancy_notes' => ['nullable', 'string', 'max:5000'],
            'access' => ['nullable', 'array'],
            'no_access' => ['nullable', 'array'],
            'loft_hatch' => ['nullable', 'array'],
            'surveyed_floor_area_m2' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'surveyed_installation_area_m2' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'measurement_method' => ['nullable', 'string', Rule::in(SurveyFieldConfig::options()['measurement_methods'])],
            'measurement_confidence' => ['nullable', 'string', Rule::in(SurveyFieldConfig::options()['measurement_confidence'])],
            'measurement_date' => ['nullable', 'date'],
            'measurement_notes' => ['nullable', 'string', 'max:5000'],
            'measurement_sections' => ['nullable', 'array'],
            'measurement_sections.*.id' => ['nullable', 'integer'],
            'measurement_sections.*.name' => ['nullable', 'string', 'max:120'],
            'measurement_sections.*.section_type' => ['nullable', 'string', Rule::in(SurveyFieldConfig::options()['section_types'])],
            'measurement_sections.*.length_m' => ['nullable', 'numeric', 'min:0'],
            'measurement_sections.*.width_m' => ['nullable', 'numeric', 'min:0'],
            'measurement_sections.*.height_m' => ['nullable', 'numeric', 'min:0'],
            'measurement_sections.*.calculated_area_m2' => ['nullable', 'numeric', 'min:0'],
            'measurement_sections.*.manual_area_m2' => ['nullable', 'numeric', 'min:0'],
            'measurement_sections.*.measurement_method' => ['nullable', 'string', Rule::in(SurveyFieldConfig::options()['measurement_methods'])],
            'measurement_sections.*.confidence' => ['nullable', 'string', Rule::in(SurveyFieldConfig::options()['measurement_confidence'])],
            'measurement_sections.*.is_estimate' => ['nullable', 'boolean'],
            'measurement_sections.*.area_not_accessed' => ['nullable', 'boolean'],
            'measurement_sections.*.notes' => ['nullable', 'string', 'max:2000'],
            'scheme_inspection' => ['nullable', 'array'],
            'homeowner_confirmation' => ['nullable', 'array'],
            'risks' => ['nullable', 'array'],
            'surveyor_recommendation' => ['nullable', 'string', 'max:5000'],
            'seller_visible_notes' => ['nullable', 'string', 'max:5000'],
            'buyer_visible_notes' => ['nullable', 'string', 'max:5000'],
            'internal_audit_notes' => ['nullable', 'string', 'max:5000'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function options(Lead $lead): array
    {
        $fieldOptions = SurveyFieldConfig::options();

        return [
            'steps' => LeadSurveyService::STEPS,
            'statuses' => SurveyStatus::values(),
            'scheme_slug' => $lead->scheme?->slug,
            'field_options' => $fieldOptions,
            'required_fields' => SurveyFieldConfig::requiredFieldsByStep($lead->scheme?->slug),
            'measurement_methods' => $fieldOptions['measurement_methods'],
            'evidence_categories' => $fieldOptions['evidence_categories'],
            'risk_options' => $fieldOptions['risk_options'],
        ];
    }

    private function portal(Request $request): string
    {
        $path = $request->path();
        if (str_starts_with($path, 'seller/')) {
            return 'seller';
        }
        if (str_starts_with($path, 'auditor/')) {
            return 'auditor';
        }

        return 'admin';
    }

    private function routeName(Request $request, string $action): string
    {
        return $this->portal($request).'.surveys.'.$action;
    }

    /**
     * @return array<string, string>
     */
    private function routeMap(Request $request, Lead $lead): array
    {
        $portal = $this->portal($request);

        return [
            'draft' => route("{$portal}.surveys.draft", $lead),
            'submit' => route("{$portal}.surveys.submit", $lead),
            'evidence' => route("{$portal}.surveys.evidence", $lead),
            'correction' => route("{$portal}.surveys.correction", $lead),
            'approve' => route("{$portal}.surveys.approve", $lead),
            'reject' => route("{$portal}.surveys.reject", $lead),
        ];
    }

    private function returnHref(Request $request, Lead $lead): string
    {
        return match ($this->portal($request)) {
            'seller' => route('seller.leads.show', $lead),
            'auditor' => route('auditor.audits.show', $lead),
            default => route('admin.leads-bought.show', $lead),
        };
    }

    /**
     * @return list<array{label: string, href?: string}>
     */
    private function breadcrumbTrail(Request $request, Lead $lead): array
    {
        $portal = $this->portal($request);
        $reference = $lead->lead_reference ?: ('#'.$lead->id);

        return match ($portal) {
            'seller' => [
                ['label' => __('rml.seller.nav.my_leads'), 'href' => route('seller.leads.index')],
                ['label' => $reference, 'href' => route('seller.leads.show', $lead)],
                ['label' => __('rml.survey.title')],
            ],
            'auditor' => [
                ['label' => __('rml.auditor.nav.audits'), 'href' => route('auditor.audits.index')],
                ['label' => $reference, 'href' => route('auditor.audits.show', $lead)],
                ['label' => __('rml.survey.title')],
            ],
            default => [
                ['label' => __('rml.admin.nav.leads'), 'href' => route('admin.leads.index', ['tab' => 'registered'])],
                ['label' => $reference, 'href' => route('admin.leads-bought.show', $lead)],
                ['label' => __('rml.survey.title')],
            ],
        };
    }
}
