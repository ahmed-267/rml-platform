<?php

namespace App\Http\Controllers\Auditor;

use App\Enums\AuditDecisionStatus;
use App\Enums\LeadStatus;
use App\Http\Controllers\Controller;
use App\Models\LeadAudit;
use App\Models\MessageThread;
use App\Services\Auditor\AuditWorkflowService;
use App\Support\AuditorLeadPresenter;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(
        private readonly AuditWorkflowService $workflow,
    ) {}

    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        $base = $this->workflow->assignedAuditsQuery($user);

        $assignedCount = (clone $base)->count();
        $pendingValidation = (clone $base)->whereHas('lead', fn ($q) => $q->where('status', LeadStatus::PendingValidation->value))->count();
        $inReview = (clone $base)->where('status', AuditDecisionStatus::InReview->value)->count();
        $needsInfo = (clone $base)->where('status', AuditDecisionStatus::NeedsMoreInformation->value)->count();
        $completedToday = (clone $base)
            ->whereNotNull('completed_at')
            ->whereDate('completed_at', today())
            ->count();
        $completedWeek = (clone $base)
            ->whereNotNull('completed_at')
            ->where('completed_at', '>=', now()->startOfWeek())
            ->count();

        $assigned = (clone $base)
            ->whereIn('status', [
                AuditDecisionStatus::Pending->value,
                AuditDecisionStatus::InReview->value,
                AuditDecisionStatus::NeedsMoreInformation->value,
            ])
            ->latest('updated_at')
            ->limit(8)
            ->get()
            ->map(fn (LeadAudit $audit) => AuditorLeadPresenter::listRow($audit->lead, $audit))
            ->values()
            ->all();

        $attention = (clone $base)
            ->whereIn('status', [
                AuditDecisionStatus::InReview->value,
                AuditDecisionStatus::NeedsMoreInformation->value,
                AuditDecisionStatus::Pending->value,
            ])
            ->latest('updated_at')
            ->limit(5)
            ->get()
            ->map(fn (LeadAudit $audit) => AuditorLeadPresenter::listRow($audit->lead, $audit))
            ->values()
            ->all();

        $recent = (clone $base)
            ->whereNotNull('completed_at')
            ->latest('completed_at')
            ->limit(6)
            ->get()
            ->map(fn (LeadAudit $audit) => AuditorLeadPresenter::listRow($audit->lead, $audit))
            ->values()
            ->all();

        $openMessages = MessageThread::query()
            ->where(function ($q) use ($user) {
                $q->where('assigned_to_user_id', $user->id)
                    ->orWhere('created_by_user_id', $user->id);
            })
            ->whereIn('status', ['open', 'pending'])
            ->count();

        return Inertia::render('Auditor/Dashboard', [
            'kpis' => [
                'assigned' => $assignedCount,
                'pending_validation' => $pendingValidation,
                'in_review' => $inReview,
                'needs_more_information' => $needsInfo,
                'completed_today' => $completedToday,
                'completed_week' => $completedWeek,
                'open_messages' => $openMessages,
            ],
            'assigned_audits' => $assigned,
            'needs_attention' => $attention,
            'recent_activity' => $recent,
        ]);
    }
}
