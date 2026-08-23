<?php

namespace App\Http\Controllers\Auditor;

use App\Enums\AuditDecisionStatus;
use App\Enums\LeadStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auditor\RecommendAcceptRequest;
use App\Http\Requests\Auditor\RecommendRejectRequest;
use App\Http\Requests\Auditor\RequestMoreInfoRequest;
use App\Http\Requests\Auditor\SaveChecklistRequest;
use App\Models\Lead;
use App\Models\LeadAudit;
use App\Models\Scheme;
use App\Models\Zone;
use App\Services\Auditor\AuditWorkflowService;
use App\Support\AuditorLeadPresenter;
use App\Support\ListPagination;
use App\Support\ListSort;
use App\Support\SurveySummary;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AuditController extends Controller
{
    public const TAB_MY_AUDITS = 'my-audits';

    public const TAB_AUDIT_QUEUE = 'audit-queue';

    public const TAB_COMPLETED = 'completed';

    public function __construct(
        private readonly AuditWorkflowService $workflow,
    ) {}

    public function index(Request $request): Response
    {
        $tab = $this->resolveTab($request->input('tab'));

        $audits = match ($tab) {
            self::TAB_MY_AUDITS => $this->paginateMyAudits($request),
            self::TAB_COMPLETED => $this->paginateCompleted($request),
            default => $this->paginateAuditQueue($request),
        };

        $sortState = $audits['sort'];
        $perPage = $audits['per_page'];

        return Inertia::render('Auditor/Audits/Index', [
            'tab' => $tab,
            'tabCounts' => $this->tabCounts($request),
            'audits' => $audits['paginator'],
            'filters' => array_merge($audits['filters'], [
                'tab' => $tab,
                'sort' => $sortState['sort'],
                'direction' => $sortState['direction'],
                'per_page' => $perPage,
            ]),
            'filterOptions' => $audits['filterOptions'],
        ]);
    }

    public function show(Request $request, Lead $lead): Response
    {
        abort_if($lead->status === LeadStatus::Draft, 404);

        $audit = $this->workflow->ensureAssignedAudit($request->user(), $lead);
        $checklistDefs = $this->workflow->checklistItems($lead->scheme_id);
        $results = $audit->checklistResults->keyBy('audit_checklist_item_id');

        $checklist = collect($checklistDefs)->map(function (array $item) use ($results) {
            $result = $results->get($item['id']);

            return [
                'id' => (string) $item['id'],
                'key' => $item['key'],
                'label' => $item['label'],
                'required' => (bool) $item['required'],
                'checked' => (bool) ($result?->checked ?? false),
                'notes' => $result?->notes,
            ];
        })->values()->all();

        $checkedCount = collect($checklist)->where('checked', true)->count();
        $totalCount = count($checklist);

        $lead->loadMissing(['survey.measurementSections', 'survey.evidence', 'latestCatastroSnapshot']);

        return Inertia::render('Auditor/Audits/Show', [
            'lead' => AuditorLeadPresenter::present($lead),
            'checklist' => $checklist,
            'checklist_progress' => [
                'checked' => $checkedCount,
                'total' => $totalCount,
                'percent' => $totalCount > 0 ? round(($checkedCount / $totalCount) * 100) : 0,
            ],
            'can_recommend' => ! in_array($audit->status?->value, ['accepted', 'rejected'], true),
            'survey' => SurveySummary::forLead($lead, $request->user(), 'auditor'),
            'catastro' => app(\App\Services\Catastro\CatastroLookupService::class)
                ->presentForLead($lead, includeProtected: true),
            'pre_installation' => app(\App\Services\Survey\PreInstallationComparisonService::class)
                ->forLead($lead),
            'audit_outcome' => $audit->status?->preInstallationOutcomeKey(),
            'can_lookup_catastro' => (bool) $request->user()?->can(\App\Support\Permissions::LOOKUP_CATASTRO),
            'can_review_catastro' => (bool) $request->user()?->can(\App\Support\Permissions::REVIEW_CATASTRO),
        ]);
    }

    public function saveChecklist(SaveChecklistRequest $request, Lead $lead): RedirectResponse
    {
        $this->workflow->saveChecklist($request->user(), $lead, $request->validated());

        return back()->with('success', __('rml.auditor.audit.checklist_saved_flash'));
    }

    public function recommendAccept(RecommendAcceptRequest $request, Lead $lead): RedirectResponse
    {
        $this->workflow->recommendAccept($request->user(), $lead, $request->validated());

        return redirect()
            ->route('auditor.audits.index', ['tab' => self::TAB_COMPLETED])
            ->with('success', __('rml.auditor.audit.recommend_accept_flash'));
    }

    public function recommendReject(RecommendRejectRequest $request, Lead $lead): RedirectResponse
    {
        $this->workflow->recommendReject($request->user(), $lead, $request->validated());

        return redirect()
            ->route('auditor.audits.index', ['tab' => self::TAB_COMPLETED])
            ->with('success', __('rml.auditor.audit.recommend_reject_flash'));
    }

    public function requestInfo(RequestMoreInfoRequest $request, Lead $lead): RedirectResponse
    {
        $this->workflow->requestMoreInformation($request->user(), $lead, $request->validated());

        return redirect()
            ->route('auditor.audits.index', ['tab' => self::TAB_MY_AUDITS])
            ->with('success', __('rml.auditor.audit.request_info_flash'));
    }

    public function requestReSurvey(RequestMoreInfoRequest $request, Lead $lead): RedirectResponse
    {
        $this->workflow->requestReSurvey($request->user(), $lead, $request->validated());

        return redirect()
            ->route('auditor.audits.index', ['tab' => self::TAB_MY_AUDITS])
            ->with('success', __('rml.auditor.audit.re_survey_flash'));
    }

    public function requestManualVerification(RequestMoreInfoRequest $request, Lead $lead): RedirectResponse
    {
        $this->workflow->requestManualVerification($request->user(), $lead, $request->validated());

        return redirect()
            ->route('auditor.audits.index', ['tab' => self::TAB_MY_AUDITS])
            ->with('success', __('rml.auditor.audit.manual_verification_flash'));
    }

    private function resolveTab(mixed $tab): string
    {
        $value = is_string($tab) ? $tab : self::TAB_AUDIT_QUEUE;

        return in_array($value, [
            self::TAB_MY_AUDITS,
            self::TAB_AUDIT_QUEUE,
            self::TAB_COMPLETED,
        ], true) ? $value : self::TAB_AUDIT_QUEUE;
    }

    /**
     * @return array{paginator: mixed, filters: array<string, mixed>, filterOptions: array<string, mixed>, sort: array{sort: string, direction: string}, per_page: int}
     */
    private function paginateMyAudits(Request $request): array
    {
        $query = $this->workflow->assignedAuditsQuery($request->user())
            ->whereIn('status', [
                AuditDecisionStatus::Pending->value,
                AuditDecisionStatus::InReview->value,
                AuditDecisionStatus::NeedsMoreInformation->value,
            ]);

        $this->applyActiveFilters($query, $request);

        $sortState = ListSort::apply($query, $request, $this->sortColumns(), 'date', 'desc');
        $perPage = ListPagination::perPage($request);

        $paginator = $query
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (LeadAudit $audit) => AuditorLeadPresenter::listRow($audit->lead, $audit));

        return [
            'paginator' => $paginator,
            'filters' => [
                'status' => $request->input('status'),
                'scheme_id' => $request->input('scheme_id'),
                'zone_id' => $request->input('zone_id'),
                'evidence' => $request->input('evidence'),
                'search' => $request->input('search'),
            ],
            'filterOptions' => [
                'statuses' => [
                    AuditDecisionStatus::Pending->value,
                    AuditDecisionStatus::InReview->value,
                    AuditDecisionStatus::NeedsMoreInformation->value,
                ],
                'schemes' => $this->schemeOptions(),
                'zones' => $this->zoneOptions(),
            ],
            'sort' => $sortState,
            'per_page' => $perPage,
        ];
    }

    /**
     * @return array{paginator: mixed, filters: array<string, mixed>, filterOptions: array<string, mixed>, sort: array{sort: string, direction: string}, per_page: int}
     */
    private function paginateAuditQueue(Request $request): array
    {
        $query = $this->workflow->assignedAuditsQuery($request->user())
            ->whereIn('status', [
                AuditDecisionStatus::Pending->value,
                AuditDecisionStatus::InReview->value,
            ]);

        $this->applyActiveFilters($query, $request);

        $sortState = ListSort::apply($query, $request, $this->sortColumns(), 'date', 'desc');
        $perPage = ListPagination::perPage($request);

        $paginator = $query
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (LeadAudit $audit) => AuditorLeadPresenter::listRow($audit->lead, $audit));

        return [
            'paginator' => $paginator,
            'filters' => [
                'status' => $request->input('status'),
                'scheme_id' => $request->input('scheme_id'),
                'zone_id' => $request->input('zone_id'),
                'evidence' => $request->input('evidence'),
                'search' => $request->input('search'),
            ],
            'filterOptions' => [
                'statuses' => [
                    AuditDecisionStatus::Pending->value,
                    AuditDecisionStatus::InReview->value,
                ],
                'schemes' => $this->schemeOptions(),
                'zones' => $this->zoneOptions(),
            ],
            'sort' => $sortState,
            'per_page' => $perPage,
        ];
    }

    /**
     * @return array{paginator: mixed, filters: array<string, mixed>, filterOptions: array<string, mixed>, sort: array{sort: string, direction: string}, per_page: int}
     */
    private function paginateCompleted(Request $request): array
    {
        $query = $this->workflow->assignedAuditsQuery($request->user())
            ->whereIn('status', [
                AuditDecisionStatus::RecommendedAccept->value,
                AuditDecisionStatus::RecommendedReject->value,
                AuditDecisionStatus::Accepted->value,
                AuditDecisionStatus::Rejected->value,
                AuditDecisionStatus::NeedsMoreInformation->value,
            ])
            ->whereNotNull('completed_at');

        if ($request->filled('recommendation')) {
            $query->where('status', $request->string('recommendation')->toString());
        }

        if ($request->filled('scheme_id')) {
            $query->whereHas('lead', fn ($q) => $q->where('scheme_id', $request->integer('scheme_id')));
        }

        if ($request->filled('zone_id')) {
            $query->whereHas('lead', fn ($q) => $q->where('zone_id', $request->integer('zone_id')));
        }

        if ($request->filled('search')) {
            $search = '%'.$request->string('search')->toString().'%';
            $query->whereHas('lead', fn ($q) => $q->where('lead_reference', 'ilike', $search));
        }

        $sortState = ListSort::apply($query, $request, $this->completedSortColumns(), 'date', 'desc');
        $perPage = ListPagination::perPage($request);

        $paginator = $query
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (LeadAudit $audit) => [
                ...AuditorLeadPresenter::listRow($audit->lead, $audit),
                'recommendation' => $audit->status?->value,
                'final_decision' => in_array($audit->status?->value, [
                    AuditDecisionStatus::Accepted->value,
                    AuditDecisionStatus::Rejected->value,
                ], true) ? $audit->status?->value : null,
                'completed_at' => $audit->completed_at?->toIso8601String(),
            ]);

        return [
            'paginator' => $paginator,
            'filters' => [
                'recommendation' => $request->input('recommendation'),
                'scheme_id' => $request->input('scheme_id'),
                'zone_id' => $request->input('zone_id'),
                'search' => $request->input('search'),
            ],
            'filterOptions' => [
                'recommendations' => [
                    AuditDecisionStatus::RecommendedAccept->value,
                    AuditDecisionStatus::RecommendedReject->value,
                    AuditDecisionStatus::NeedsMoreInformation->value,
                    AuditDecisionStatus::Accepted->value,
                    AuditDecisionStatus::Rejected->value,
                ],
                'schemes' => $this->schemeOptions(),
                'zones' => $this->zoneOptions(),
            ],
            'sort' => $sortState,
            'per_page' => $perPage,
        ];
    }

    /**
     * @return array{my-audits: int, audit-queue: int, completed: int}
     */
    private function tabCounts(Request $request): array
    {
        $base = $this->workflow->assignedAuditsQuery($request->user());

        return [
            self::TAB_MY_AUDITS => (clone $base)->whereIn('status', [
                AuditDecisionStatus::Pending->value,
                AuditDecisionStatus::InReview->value,
                AuditDecisionStatus::NeedsMoreInformation->value,
            ])->count(),
            self::TAB_AUDIT_QUEUE => (clone $base)->whereIn('status', [
                AuditDecisionStatus::Pending->value,
                AuditDecisionStatus::InReview->value,
            ])->count(),
            self::TAB_COMPLETED => (clone $base)->whereIn('status', [
                AuditDecisionStatus::RecommendedAccept->value,
                AuditDecisionStatus::RecommendedReject->value,
                AuditDecisionStatus::Accepted->value,
                AuditDecisionStatus::Rejected->value,
                AuditDecisionStatus::NeedsMoreInformation->value,
            ])->whereNotNull('completed_at')->count(),
        ];
    }

    private function applyActiveFilters(Builder $query, Request $request): void
    {
        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }

        if ($request->filled('scheme_id')) {
            $query->whereHas('lead', fn ($q) => $q->where('scheme_id', $request->integer('scheme_id')));
        }

        if ($request->filled('zone_id')) {
            $query->whereHas('lead', fn ($q) => $q->where('zone_id', $request->integer('zone_id')));
        }

        if ($request->filled('evidence')) {
            if ($request->string('evidence')->toString() === 'missing') {
                $query->whereHas('lead', fn ($q) => $q->whereDoesntHave('evidenceFiles'));
            } elseif ($request->string('evidence')->toString() === 'available') {
                $query->whereHas('lead', fn ($q) => $q->whereHas('evidenceFiles'));
            }
        }

        if ($request->filled('search')) {
            $search = '%'.$request->string('search')->toString().'%';
            $query->whereHas('lead', fn ($q) => $q->where('lead_reference', 'ilike', $search));
        }
    }

    private function schemeOptions()
    {
        return Scheme::query()->where('active', true)->orderBy('sort_order')->get(['id', 'name']);
    }

    private function zoneOptions()
    {
        return Zone::query()->where('active', true)->orderBy('sort_order')->get(['id', 'code', 'name']);
    }

    /**
     * @return array<string, string|\Closure(Builder, string): void>
     */
    private function sortColumns(): array
    {
        return [
            'lead_reference' => fn (Builder $query, string $direction) => $query->orderBy(
                Lead::query()
                    ->select('lead_reference')
                    ->whereColumn('leads.id', 'lead_audits.lead_id')
                    ->limit(1),
                $direction,
            ),
            'scheme' => fn (Builder $query, string $direction) => $query->orderBy(
                Scheme::query()
                    ->select('schemes.name')
                    ->join('leads', 'leads.scheme_id', '=', 'schemes.id')
                    ->whereColumn('leads.id', 'lead_audits.lead_id')
                    ->limit(1),
                $direction,
            ),
            'zone' => fn (Builder $query, string $direction) => $query->orderBy(
                Zone::query()
                    ->select('zones.code')
                    ->join('leads', 'leads.zone_id', '=', 'zones.id')
                    ->whereColumn('leads.id', 'lead_audits.lead_id')
                    ->limit(1),
                $direction,
            ),
            'status' => 'status',
            'date' => fn (Builder $query, string $direction) => $query->orderBy(
                Lead::query()
                    ->select('created_at')
                    ->whereColumn('leads.id', 'lead_audits.lead_id')
                    ->limit(1),
                $direction,
            ),
        ];
    }

    /**
     * @return array<string, string|\Closure(Builder, string): void>
     */
    private function completedSortColumns(): array
    {
        return [
            ...$this->sortColumns(),
            'date' => 'completed_at',
        ];
    }
}
