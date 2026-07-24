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
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AuditController extends Controller
{
    public function __construct(
        private readonly AuditWorkflowService $workflow,
    ) {}

    public function index(Request $request): Response
    {
        $query = $this->workflow->assignedAuditsQuery($request->user())
            ->whereIn('status', [
                AuditDecisionStatus::Pending->value,
                AuditDecisionStatus::InReview->value,
            ]);

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
            $query->whereHas('lead', fn ($q) => $q->where('lead_reference', 'like', $search));
        }

        $sortState = ListSort::apply(
            $query,
            $request,
            $this->sortColumns(),
            'date',
            'desc',
        );
        $perPage = ListPagination::perPage($request);

        $audits = $query
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (LeadAudit $audit) => AuditorLeadPresenter::listRow($audit->lead, $audit));

        return Inertia::render('Auditor/Audits/Index', [
            'audits' => $audits,
            'filters' => [
                'status' => $request->input('status'),
                'scheme_id' => $request->input('scheme_id'),
                'zone_id' => $request->input('zone_id'),
                'evidence' => $request->input('evidence'),
                'search' => $request->input('search'),
                'sort' => $sortState['sort'],
                'direction' => $sortState['direction'],
                'per_page' => $perPage,
            ],
            'filterOptions' => [
                'statuses' => [
                    AuditDecisionStatus::Pending->value,
                    AuditDecisionStatus::InReview->value,
                ],
                'schemes' => Scheme::query()->where('active', true)->orderBy('sort_order')->get(['id', 'name']),
                'zones' => Zone::query()->where('active', true)->orderBy('sort_order')->get(['id', 'code', 'name']),
            ],
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

        return Inertia::render('Auditor/Audits/Show', [
            'lead' => AuditorLeadPresenter::present($lead),
            'checklist' => $checklist,
            'checklist_progress' => [
                'checked' => $checkedCount,
                'total' => $totalCount,
                'percent' => $totalCount > 0 ? round(($checkedCount / $totalCount) * 100) : 0,
            ],
            'can_recommend' => ! in_array($audit->status?->value, ['accepted', 'rejected'], true),
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
            ->route('auditor.completed-audits.index')
            ->with('success', __('rml.auditor.audit.recommend_accept_flash'));
    }

    public function recommendReject(RecommendRejectRequest $request, Lead $lead): RedirectResponse
    {
        $this->workflow->recommendReject($request->user(), $lead, $request->validated());

        return redirect()
            ->route('auditor.completed-audits.index')
            ->with('success', __('rml.auditor.audit.recommend_reject_flash'));
    }

    public function requestInfo(RequestMoreInfoRequest $request, Lead $lead): RedirectResponse
    {
        $this->workflow->requestMoreInformation($request->user(), $lead, $request->validated());

        return redirect()
            ->route('auditor.assigned-audits.index')
            ->with('success', __('rml.auditor.audit.request_info_flash'));
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
}
