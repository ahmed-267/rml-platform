<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ApprovalStatus;
use App\Enums\LeadStatus;
use App\Enums\MessageThreadCategory;
use App\Enums\MessageThreadStatus;
use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Enums\PayoutStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\MessageThread;
use App\Models\Payment;
use App\Models\Payout;
use App\Models\User;
use App\Support\LeadStatusPresentation;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    private const KPI_CACHE_SECONDS = 45;

    public function __invoke(): Response
    {
        $awaitingValidationStatuses = [
            ...LeadStatusPresentation::expand(LeadStatusPresentation::PENDING_REVIEW),
            ...LeadStatusPresentation::expand(LeadStatusPresentation::NEEDS_INFORMATION),
        ];

        $metrics = Cache::remember('admin.dashboard.metrics.v2', self::KPI_CACHE_SECONDS, function () use ($awaitingValidationStatuses) {
            $leadAgg = Lead::query()
                ->selectRaw('count(*) filter (where status != ?) as total_submitted', [LeadStatus::Draft->value])
                ->selectRaw('count(*) filter (where status = ?) as leads_sold', [LeadStatus::Sold->value])
                ->selectRaw(
                    'count(*) filter (where status in ('.implode(',', array_fill(0, count($awaitingValidationStatuses), '?')).')) as awaiting_validation',
                    $awaitingValidationStatuses
                )
                ->selectRaw(
                    'COALESCE(SUM(COALESCE(expected_margin, selling_price - buying_price)) filter (where status = ?), 0) as profit_margin',
                    [LeadStatus::Sold->value]
                )
                ->first();

            $listedStatuses = [
                ...LeadStatusPresentation::expand(LeadStatusPresentation::LISTED),
                LeadStatus::Sold->value,
            ];
            $reviewedStatuses = [...$listedStatuses, LeadStatus::Rejected->value];

            $reviewAgg = Lead::query()
                ->selectRaw(
                    'count(*) filter (where status in ('.implode(',', array_fill(0, count($reviewedStatuses), '?')).')) as reviewed_count',
                    $reviewedStatuses
                )
                ->selectRaw(
                    'count(*) filter (where status in ('.implode(',', array_fill(0, count($listedStatuses), '?')).')) as accepted_count',
                    $listedStatuses
                )
                ->first();

            $paymentAgg = Payment::query()
                ->where('type', PaymentType::BuyerPayment->value)
                ->selectRaw('COALESCE(SUM(amount) filter (where status = ?), 0) as paid_sum', [PaymentStatus::Paid->value])
                ->selectRaw('count(*) filter (where status = ?) as pending_count', [PaymentStatus::Pending->value])
                ->selectRaw('COALESCE(SUM(amount) filter (where status = ?), 0) as pending_sum', [PaymentStatus::Pending->value])
                ->first();

            $payoutAgg = Payout::query()
                ->selectRaw('COALESCE(SUM(amount) filter (where status = ?), 0) as paid_sum', [PayoutStatus::Paid->value])
                ->selectRaw('count(*) filter (where status = ?) as pending_count', [PayoutStatus::Pending->value])
                ->selectRaw('COALESCE(SUM(amount) filter (where status = ?), 0) as pending_sum', [PayoutStatus::Pending->value])
                ->first();

            $openIssues = MessageThread::query()
                ->where('status', MessageThreadStatus::Open->value)
                ->count();

            $reviewedCount = (int) ($reviewAgg->reviewed_count ?? 0);
            $acceptedCount = (int) ($reviewAgg->accepted_count ?? 0);

            $totalSubmitted = (int) ($leadAgg->total_submitted ?? 0);
            $awaitingValidation = (int) ($leadAgg->awaiting_validation ?? 0);
            $leadsSold = (int) ($leadAgg->leads_sold ?? 0);
            $buyerPaid = (float) ($paymentAgg->paid_sum ?? 0);
            $profitMargin = (float) ($leadAgg->profit_margin ?? 0);

            return [
                'total_submitted' => $totalSubmitted,
                'awaiting_validation' => $awaitingValidation,
                'leads_sold' => $leadsSold,
                'buyer_payments_paid_sum' => $buyerPaid,
                'seller_payouts_paid_sum' => (float) ($payoutAgg->paid_sum ?? 0),
                'profit_margin' => $profitMargin,
                'open_issues' => $openIssues,
                'acceptance_rate' => $reviewedCount > 0
                    ? round(($acceptedCount / $reviewedCount) * 100, 1)
                    : 0.0,
                'sold_rate' => $totalSubmitted > 0
                    ? round(($leadsSold / $totalSubmitted) * 100, 1)
                    : 0.0,
                'awaiting_rate' => $totalSubmitted > 0
                    ? round(($awaitingValidation / $totalSubmitted) * 100, 1)
                    : 0.0,
                'margin_rate' => $buyerPaid > 0
                    ? round(($profitMargin / $buyerPaid) * 100, 1)
                    : 0.0,
                'pipeline_counts' => LeadStatusPresentation::groupCounts(
                    Lead::query()
                        ->where('status', '!=', LeadStatus::Draft->value)
                        ->select('status', DB::raw('count(*) as count'))
                        ->groupBy('status')
                        ->pluck('count', 'status')
                        ->all()
                ),
                'pending_seller_regs' => User::query()
                    ->where('approval_status', ApprovalStatus::Pending->value)
                    ->whereHas('roles', fn ($q) => $q->whereIn('name', [
                        UserRole::SellerCompanyAdmin->value,
                        UserRole::IndividualSellerAgent->value,
                        UserRole::SellerStaff->value,
                    ]))
                    ->count(),
                'pending_buyer_regs' => User::query()
                    ->where('approval_status', ApprovalStatus::Pending->value)
                    ->whereHas('roles', fn ($q) => $q->where('name', UserRole::BuyerAdmin->value))
                    ->count(),
                'pending_payments' => (int) ($paymentAgg->pending_count ?? 0),
                'pending_payouts' => (int) ($payoutAgg->pending_count ?? 0),
                'pending_payments_sum' => (float) ($paymentAgg->pending_sum ?? 0),
                'pending_payouts_sum' => (float) ($payoutAgg->pending_sum ?? 0),
            ];
        });

        $awaitingAuditLeads = Lead::query()
            ->whereIn('status', $awaitingValidationStatuses)
            ->with(['submittedBy:id,name'])
            ->latest()
            ->limit(6)
            ->get()
            ->map(fn (Lead $lead) => [
                'id' => $lead->id,
                'lead_reference' => $lead->lead_reference,
                'status' => $lead->status?->value,
                'seller_name' => $lead->submittedBy?->name,
            ])
            ->values()
            ->all();

        $actionQueue = $this->buildActionQueue();

        $openIssueItems = MessageThread::query()
            ->where('status', MessageThreadStatus::Open->value)
            ->latest()
            ->limit(3)
            ->get()
            ->map(fn (MessageThread $thread) => [
                'id' => 'msg-'.$thread->id,
                'title' => $thread->subject,
                'detail' => $thread->thread_reference,
                'badge' => __('rml.admin.dashboard.queue_message_badge'),
                'tone' => 'neutral',
                'href' => route('admin.messages.show', $thread),
                'action_label' => __('rml.admin.dashboard.action_open'),
            ])
            ->values()
            ->all();

        return Inertia::render('Admin/Dashboard', [
            'kpis' => [
                'total_submitted' => $metrics['total_submitted'],
                'awaiting_validation' => $metrics['awaiting_validation'],
                'leads_sold' => $metrics['leads_sold'],
                'buyer_payments_paid_sum' => round($metrics['buyer_payments_paid_sum'], 2),
                'seller_payouts_paid_sum' => round($metrics['seller_payouts_paid_sum'], 2),
                'profit_margin' => round($metrics['profit_margin'], 2),
                'open_issues' => $metrics['open_issues'],
                'acceptance_rate' => $metrics['acceptance_rate'],
                'sold_rate' => $metrics['sold_rate'],
                'awaiting_rate' => $metrics['awaiting_rate'],
                'margin_rate' => $metrics['margin_rate'],
                'pending_payments_count' => $metrics['pending_payments'],
                'pending_payouts_count' => $metrics['pending_payouts'],
                'pending_approvals' => $metrics['pending_seller_regs'] + $metrics['pending_buyer_regs'],
            ],
            'pipeline_counts' => $metrics['pipeline_counts'],
            'queue_counts' => [
                'awaiting_audit' => $metrics['awaiting_validation'],
                'pending_seller_regs' => $metrics['pending_seller_regs'],
                'pending_buyer_regs' => $metrics['pending_buyer_regs'],
                'pending_payments' => $metrics['pending_payments'],
                'pending_payouts' => $metrics['pending_payouts'],
                'open_messages' => $metrics['open_issues'],
            ],
            'awaiting_audit_leads' => $awaitingAuditLeads,
            'action_queue' => $actionQueue,
            'financial_snapshot' => [
                'buyer_payments_paid' => round($metrics['buyer_payments_paid_sum'], 2),
                'seller_payouts_paid' => round($metrics['seller_payouts_paid_sum'], 2),
                'profit_margin' => round($metrics['profit_margin'], 2),
                'pending_payments_sum' => round($metrics['pending_payments_sum'], 2),
                'pending_payouts_sum' => round($metrics['pending_payouts_sum'], 2),
                'pending_payments_count' => $metrics['pending_payments'],
                'pending_payouts_count' => $metrics['pending_payouts'],
            ],
            'open_issues_summary' => [
                'open_count' => $metrics['open_issues'],
                'items' => $openIssueItems,
            ],
        ]);
    }

    /**
     * Mixed action queue with categories for dashboard tab filtering.
     *
     * @return list<array{id: string, title: string, detail: string, badge: string, tone: string, href: string, action_label: string, category: string}>
     */
    private function buildActionQueue(): array
    {
        $awaitingValidationStatuses = [
            LeadStatus::PendingValidation->value,
            LeadStatus::Validating->value,
            LeadStatus::PendingEvidence->value,
            LeadStatus::NeedsMoreInformation->value,
        ];

        $audits = Lead::query()
            ->whereIn('status', $awaitingValidationStatuses)
            ->with(['submittedBy:id,name'])
            ->latest()
            ->limit(4)
            ->get()
            ->map(fn (Lead $lead) => [
                'id' => 'audit-'.$lead->id,
                'title' => __('rml.admin.dashboard.queue_audit_title', ['ref' => $lead->lead_reference]),
                'detail' => $lead->submittedBy?->name ?? __('rml.admin.common.unknown'),
                'badge' => __('rml.admin.dashboard.queue_audit_badge'),
                'tone' => 'warning',
                'href' => route('admin.leads-bought.show', $lead).'?audit=1',
                'action_label' => __('rml.admin.dashboard.action_audit'),
                'category' => 'audits',
                'priority' => 1,
                'created_at' => $lead->created_at?->timestamp ?? 0,
            ]);

        $pendingPayments = Payment::query()
            ->where('type', PaymentType::BuyerPayment->value)
            ->where('status', PaymentStatus::Pending->value)
            ->latest()
            ->limit(3)
            ->get()
            ->map(fn (Payment $payment) => [
                'id' => 'pay-'.$payment->id,
                'title' => __('rml.admin.dashboard.queue_payment_title', ['ref' => $payment->payment_reference]),
                'detail' => number_format((float) $payment->amount, 2).' '.$payment->currency,
                'badge' => __('rml.admin.dashboard.queue_payment_badge'),
                'tone' => 'danger',
                'href' => route('admin.payments.index'),
                'action_label' => __('rml.admin.dashboard.action_review'),
                'category' => 'payments',
                'priority' => 2,
                'created_at' => $payment->created_at?->timestamp ?? 0,
            ]);

        $pendingPayouts = Payout::query()
            ->where('status', PayoutStatus::Pending->value)
            ->latest()
            ->limit(2)
            ->get()
            ->map(fn (Payout $payout) => [
                'id' => 'payout-'.$payout->id,
                'title' => __('rml.admin.dashboard.queue_payout_title', [
                    'ref' => $payout->payout_reference ?? '#'.$payout->id,
                ]),
                'detail' => number_format((float) $payout->amount, 2).' '.($payout->currency ?? 'EUR'),
                'badge' => __('rml.admin.dashboard.queue_payment_badge'),
                'tone' => 'warning',
                'href' => route('admin.payments.index'),
                'action_label' => __('rml.admin.dashboard.action_review'),
                'category' => 'payments',
                'priority' => 3,
                'created_at' => $payout->created_at?->timestamp ?? 0,
            ]);

        $pendingRegs = User::query()
            ->where('approval_status', ApprovalStatus::Pending->value)
            ->whereHas('roles', fn ($q) => $q->whereIn('name', [
                UserRole::SellerCompanyAdmin->value,
                UserRole::IndividualSellerAgent->value,
                UserRole::BuyerAdmin->value,
            ]))
            ->with(['roles:id,name'])
            ->latest()
            ->limit(3)
            ->get()
            ->map(function (User $user) {
                $isBuyer = $user->roles->contains('name', UserRole::BuyerAdmin->value);

                return [
                    'id' => 'reg-'.$user->id,
                    'title' => $isBuyer
                        ? __('rml.admin.dashboard.queue_pending_buyer')
                        : __('rml.admin.dashboard.queue_pending_seller'),
                    'detail' => $user->email,
                    'badge' => __('rml.admin.dashboard.queue_approval_badge'),
                    'tone' => 'info',
                    'href' => $isBuyer
                        ? route('admin.users.index', ['tab' => 'buyers', 'approval_status' => 'pending'])
                        : route('admin.users.index', ['tab' => 'sellers', 'approval_status' => 'pending']),
                    'action_label' => __('rml.admin.dashboard.action_approve'),
                    'category' => 'approvals',
                    'priority' => 4,
                    'created_at' => $user->created_at?->timestamp ?? 0,
                ];
            });

        $messageCategories = [
            MessageThreadCategory::InformationRequest->value,
            MessageThreadCategory::EvidenceIssue->value,
            MessageThreadCategory::AuditQuestion->value,
            MessageThreadCategory::LeadReview->value,
        ];

        $issueCategories = [
            MessageThreadCategory::SellerIssue->value,
            MessageThreadCategory::BuyerIssue->value,
            MessageThreadCategory::Complaint->value,
            MessageThreadCategory::Dispute->value,
            MessageThreadCategory::PaymentQuery->value,
            MessageThreadCategory::Internal->value,
        ];

        $messages = MessageThread::query()
            ->where('status', MessageThreadStatus::Open->value)
            ->whereIn('category', $messageCategories)
            ->latest()
            ->limit(3)
            ->get()
            ->map(fn (MessageThread $thread) => [
                'id' => 'msg-'.$thread->id,
                'title' => $thread->subject,
                'detail' => $thread->thread_reference,
                'badge' => __('rml.admin.dashboard.queue_message_badge'),
                'tone' => 'neutral',
                'href' => route('admin.messages.show', $thread),
                'action_label' => __('rml.admin.dashboard.action_open'),
                'category' => 'messages',
                'priority' => 5,
                'created_at' => $thread->created_at?->timestamp ?? 0,
            ]);

        $issues = MessageThread::query()
            ->where('status', MessageThreadStatus::Open->value)
            ->where(function ($query) use ($issueCategories) {
                $query->whereIn('category', $issueCategories)
                    ->orWhereNull('category');
            })
            ->latest()
            ->limit(3)
            ->get()
            ->map(fn (MessageThread $thread) => [
                'id' => 'issue-'.$thread->id,
                'title' => filled($thread->subject)
                    ? $thread->subject
                    : __('rml.admin.dashboard.queue_issue_title'),
                'detail' => $thread->thread_reference,
                'badge' => __('rml.admin.dashboard.queue_issue_badge'),
                'tone' => 'danger',
                'href' => route('admin.messages.show', $thread),
                'action_label' => __('rml.admin.dashboard.action_review'),
                'category' => 'issues',
                'priority' => 6,
                'created_at' => $thread->created_at?->timestamp ?? 0,
            ]);

        return collect()
            ->concat($audits)
            ->concat($pendingPayments)
            ->concat($pendingPayouts)
            ->concat($pendingRegs)
            ->concat($messages)
            ->concat($issues)
            ->sortBy([
                ['priority', 'asc'],
                ['created_at', 'desc'],
            ])
            ->unique('id')
            ->take(12)
            ->map(fn (array $item) => collect($item)->except(['priority', 'created_at'])->all())
            ->values()
            ->all();
    }
}
