<?php

namespace App\Http\Controllers\Auditor;

use App\Enums\AuditDecisionStatus;
use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\LeadAudit;
use App\Models\Scheme;
use App\Models\Zone;
use App\Services\Auditor\AuditWorkflowService;
use App\Support\AuditorLeadPresenter;
use App\Support\ListPagination;
use App\Support\ListSort;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AssignedAuditController extends Controller
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
                AuditDecisionStatus::NeedsMoreInformation->value,
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

        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->date('from'));
        }

        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', $request->date('to'));
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

        return Inertia::render('Auditor/AssignedAudits/Index', [
            'audits' => $audits,
            'filters' => [
                'status' => $request->input('status'),
                'scheme_id' => $request->input('scheme_id'),
                'zone_id' => $request->input('zone_id'),
                'evidence' => $request->input('evidence'),
                'search' => $request->input('search'),
                'from' => $request->input('from'),
                'to' => $request->input('to'),
                'sort' => $sortState['sort'],
                'direction' => $sortState['direction'],
                'per_page' => $perPage,
            ],
            'filterOptions' => [
                'statuses' => [
                    AuditDecisionStatus::Pending->value,
                    AuditDecisionStatus::InReview->value,
                    AuditDecisionStatus::NeedsMoreInformation->value,
                ],
                'schemes' => Scheme::query()->where('active', true)->orderBy('sort_order')->get(['id', 'name']),
                'zones' => Zone::query()->where('active', true)->orderBy('sort_order')->get(['id', 'code', 'name']),
            ],
        ]);
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
