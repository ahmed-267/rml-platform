<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AuditDecisionStatus;
use App\Enums\LeadStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AcceptLeadAuditRequest;
use App\Http\Requests\Admin\AssignLeadAuditorRequest;
use App\Http\Requests\Admin\RejectLeadAuditRequest;
use App\Http\Requests\Admin\RequestLeadAuditInfoRequest;
use App\Models\Lead;
use App\Models\User;
use App\Services\Admin\LeadAuditService;
use App\Services\Auditor\AuditWorkflowService;
use App\Support\AdminLeadPresenter;
use App\Support\Permissions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LeadAuditController extends Controller
{
    public function __construct(
        private readonly LeadAuditService $leadAuditService,
        private readonly AuditWorkflowService $auditWorkflowService,
    ) {}

    public function show(Request $request, Lead $lead): Response|JsonResponse
    {
        abort_if($lead->status === LeadStatus::Draft, 404);

        if ($this->wantsAuditJson($request)) {
            return $this->data($request, $lead);
        }

        return Inertia::render('Admin/Leads/Audit', [
            'lead' => AdminLeadPresenter::present($lead),
            'audit' => $this->auditPayload($lead),
            'auditOpen' => true,
        ]);
    }

    /**
     * Always-JSON payload for the audit modal (avoids Inertia HTML responses).
     */
    public function data(Request $request, Lead $lead): JsonResponse
    {
        abort_unless(
            $request->user()?->can(Permissions::VIEW_LEADS)
                || $request->user()?->can(Permissions::AUDIT_LEADS)
                || $request->user()?->hasRole('super_admin'),
            403,
        );

        abort_if($lead->status === LeadStatus::Draft, 404);

        return response()->json($this->auditPayload($lead));
    }

    public function accept(AcceptLeadAuditRequest $request, Lead $lead): RedirectResponse
    {
        $this->leadAuditService->accept($request->user(), $lead, $request->validated());

        return back()->with('success', __('rml.admin.audit.accepted_flash'));
    }

    public function reject(RejectLeadAuditRequest $request, Lead $lead): RedirectResponse
    {
        $this->leadAuditService->reject($request->user(), $lead, $request->validated());

        return back()->with('success', __('rml.admin.audit.rejected_flash'));
    }

    public function requestInfo(RequestLeadAuditInfoRequest $request, Lead $lead): RedirectResponse
    {
        $this->leadAuditService->requestMoreInformation($request->user(), $lead, $request->validated());

        return back()->with('success', __('rml.admin.audit.info_requested_flash'));
    }

    public function assign(AssignLeadAuditorRequest $request, Lead $lead): RedirectResponse
    {
        $auditor = User::query()->findOrFail($request->integer('auditor_user_id'));
        $this->auditWorkflowService->assignAuditor($request->user(), $lead, $auditor);

        return back()->with('success', __('rml.admin.audit.assigned_flash'));
    }

    /**
     * @return array<string, mixed>
     */
    private function auditPayload(Lead $lead): array
    {
        $lead->loadMissing(['audits' => fn ($q) => $q->latest('id')->limit(1)]);
        $latestAudit = $lead->audits->first();
        $actor = request()->user();

        return [
            'checklist' => $this->leadAuditService->checklistItems($lead->scheme_id),
            'pricing' => $this->leadAuditService->pricingPreview($lead),
            'lead' => AdminLeadPresenter::present($lead),
            'assigned_auditor_id' => $latestAudit?->auditor_user_id,
            'can_assign_auditor' => (bool) (
                $actor?->hasRole(UserRole::SuperAdmin->value)
                || $actor?->can(Permissions::ACCEPT_REJECT_LEADS)
            ),
            'auditors' => $this->auditorOptions(),
            'catastro' => app(\App\Services\Catastro\CatastroLookupService::class)
                ->presentForLead($lead, includeProtected: true),
            'can_lookup_catastro' => (bool) (
                $actor?->can(Permissions::LOOKUP_CATASTRO)
                || $actor?->hasRole('super_admin')
            ),
            'can_review_catastro' => (bool) (
                $actor?->can(Permissions::REVIEW_CATASTRO)
                || $actor?->hasRole('super_admin')
            ),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function auditorOptions(): array
    {
        $activeStatuses = [
            AuditDecisionStatus::Pending->value,
            AuditDecisionStatus::InReview->value,
            AuditDecisionStatus::NeedsMoreInformation->value,
        ];

        return User::query()
            ->role(UserRole::InternalAuditor->value)
            ->where('approval_status', 'approved')
            ->withCount([
                'assignedAudits as active_assigned_count' => fn ($q) => $q->whereIn('status', $activeStatuses),
            ])
            ->orderBy('name')
            ->get(['id', 'name', 'email'])
            ->map(fn (User $auditor) => [
                'id' => $auditor->id,
                'name' => $auditor->name,
                'email' => $auditor->email,
                'active_assigned_count' => (int) $auditor->active_assigned_count,
            ])
            ->values()
            ->all();
    }

    private function wantsAuditJson(Request $request): bool
    {
        $accept = (string) $request->header('Accept', '');

        return $request->wantsJson()
            || $request->ajax()
            || str_contains($accept, 'application/json');
    }
}
