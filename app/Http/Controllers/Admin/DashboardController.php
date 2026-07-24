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
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(): Response
    {
        $awaitingValidationStatuses = [
            ...LeadStatusPresentation::expand(LeadStatusPresentation::PENDING_REVIEW),
            ...LeadStatusPresentation::expand(LeadStatusPresentation::NEEDS_INFORMATION),
        ];

        $totalSubmitted = Lead::query()
            ->where('status', '!=', LeadStatus::Draft->value)
            ->count();

        $awaitingValidation = Lead::query()
            ->whereIn('status', $awaitingValidationStatuses)
            ->count();

        $leadsSold = Lead::query()->where('status', LeadStatus::Sold->value)->count();

        $buyerPaymentsPaidSum = (float) Payment::query()
            ->where('type', PaymentType::BuyerPayment->value)
            ->where('status', PaymentStatus::Paid->value)
            ->sum('amount');

        $sellerPayoutsPaidSum = (float) Payout::query()
            ->where('status', PayoutStatus::Paid->value)
            ->sum('amount');

        $profitMargin = (float) Lead::query()
            ->where('status', LeadStatus::Sold->value)
            ->selectRaw('COALESCE(SUM(COALESCE(expected_margin, selling_price - buying_price)), 0) as total')
            ->value('total');

        $openIssues = MessageThread::query()
            ->where('status', MessageThreadStatus::Open->value)
            ->count();

        $reviewedCount = Lead::query()
            ->whereIn('status', [
                ...LeadStatusPresentation::expand(LeadStatusPresentation::LISTED),
                LeadStatus::Sold->value,
                LeadStatus::Rejected->value,
            ])
            ->count();

        $acceptedCount = Lead::query()
            ->whereIn('status', [
                ...LeadStatusPresentation::expand(LeadStatusPresentation::LISTED),
                LeadStatus::Sold->value,
            ])
            ->count();

        $acceptanceRate = $reviewedCount > 0
            ? round(($acceptedCount / $reviewedCount) * 100, 1)
            : 0.0;

        $pipelineCounts = LeadStatusPresentation::groupCounts(
            Lead::query()
                ->where('status', '!=', LeadStatus::Draft->value)
                ->select('status', DB::raw('count(*) as count'))
                ->groupBy('status')
                ->pluck('count', 'status')
                ->all()
        );

        $pendingSellerRegs = User::query()
            ->where('approval_status', ApprovalStatus::Pending->value)
            ->whereHas('roles', fn ($q) => $q->whereIn('name', [
                UserRole::SellerCompanyAdmin->value,
                UserRole::IndividualSellerAgent->value,
                UserRole::SellerStaff->value,
            ]))
            ->count();

        $pendingBuyerRegs = User::query()
            ->where('approval_status', ApprovalStatus::Pending->value)
            ->whereHas('roles', fn ($q) => $q->where('name', UserRole::BuyerAdmin->value))
            ->count();

        $pendingPayments = Payment::query()
            ->where('type', PaymentType::BuyerPayment->value)
            ->where('status', PaymentStatus::Pending->value)
            ->count();

        $pendingPayouts = Payout::query()
            ->where('status', PayoutStatus::Pending->value)
            ->count();

        $pendingPaymentsSum = (float) Payment::query()
            ->where('type', PaymentType::BuyerPayment->value)
            ->where('status', PaymentStatus::Pending->value)
            ->sum('amount');

        $pendingPayoutsSum = (float) Payout::query()
            ->where('status', PayoutStatus::Pending->value)
            ->sum('amount');

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
                'total_submitted' => $totalSubmitted,
                'awaiting_validation' => $awaitingValidation,
                'leads_sold' => $leadsSold,
                'buyer_payments_paid_sum' => round($buyerPaymentsPaidSum, 2),
                'seller_payouts_paid_sum' => round($sellerPayoutsPaidSum, 2),
                'profit_margin' => round($profitMargin, 2),
                'open_issues' => $openIssues,
                'acceptance_rate' => $acceptanceRate,
            ],
            'pipeline_counts' => $pipelineCounts,
            'queue_counts' => [
                'awaiting_audit' => $awaitingValidation,
                'pending_seller_regs' => $pendingSellerRegs,
                'pending_buyer_regs' => $pendingBuyerRegs,
                'pending_payments' => $pendingPayments,
                'pending_payouts' => $pendingPayouts,
                'open_messages' => $openIssues,
            ],
            'awaiting_audit_leads' => $awaitingAuditLeads,
            'action_queue' => $actionQueue,
            'financial_snapshot' => [
                'buyer_payments_paid' => round($buyerPaymentsPaidSum, 2),
                'seller_payouts_paid' => round($sellerPayoutsPaidSum, 2),
                'profit_margin' => round($profitMargin, 2),
                'pending_payments_sum' => round($pendingPaymentsSum, 2),
                'pending_payouts_sum' => round($pendingPayoutsSum, 2),
                'pending_payments_count' => $pendingPayments,
                'pending_payouts_count' => $pendingPayouts,
            ],
            'open_issues_summary' => [
                'open_count' => $openIssues,
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
                        ? route('admin.buyers.index', ['approval_status' => 'pending'])
                        : route('admin.sellers.index', ['approval_status' => 'pending']),
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
