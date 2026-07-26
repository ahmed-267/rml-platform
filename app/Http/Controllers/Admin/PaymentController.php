<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CommissionStatus;
use App\Enums\InvoiceType;
use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Enums\PayoutStatus;
use App\Enums\SellerType;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Mail\BuyerPaymentConfirmedMail;
use App\Mail\BuyerPaymentCreatedMail;
use App\Mail\LeadDetailsReleasedMail;
use App\Models\Commission;
use App\Models\Invoice;
use App\Models\Lead;
use App\Models\Payment;
use App\Models\Payout;
use App\Services\Admin\PaymentConfirmationService;
use App\Services\Documents\InvoiceDocumentService;
use App\Services\LeadPricingService;
use App\Support\CaseInsensitiveSearch;
use App\Support\ListPagination;
use App\Support\ListSort;
use App\Support\Permissions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Inertia\Response;

class PaymentController extends Controller
{
    public function __construct(
        private readonly PaymentConfirmationService $paymentConfirmationService,
        private readonly InvoiceDocumentService $invoiceDocumentService,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Payment::class);

        $tab = $request->string('tab')->toString();
        if (! in_array($tab, ['buyer', 'seller_payouts'], true)) {
            $tab = 'buyer';
        }

        $perPage = ListPagination::perPage($request);
        $isSuperAdmin = (bool) $request->user()?->hasRole(UserRole::SuperAdmin->value);
        $canEdit = (bool) (
            $request->user()?->can(Permissions::EDIT_PAYMENTS)
            || $request->user()?->can(Permissions::MANAGE_PAYMENTS)
            || $isSuperAdmin
        );

        $paymentsSort = ['sort' => 'date', 'direction' => 'desc'];
        $payoutsSort = ['sort' => 'date', 'direction' => 'desc'];

        if ($tab === 'buyer') {
            [$buyerPayments, $paymentsSort] = $this->paginateBuyerPayments($request, $perPage, $canEdit, $isSuperAdmin);
            $sellerPayouts = $this->emptyPaginator($request, $perPage, 'payouts_page');
        } else {
            $buyerPayments = $this->emptyPaginator($request, $perPage, 'payments_page');
            [$sellerPayouts, $payoutsSort] = $this->paginateSellerPayoutRows($request, $perPage, $canEdit, $isSuperAdmin);
        }

        return Inertia::render('Admin/Payments/Index', [
            'tab' => $tab,
            'buyerPayments' => $buyerPayments,
            'sellerPayouts' => $sellerPayouts,
            'can_delete_payments' => $isSuperAdmin,
            'filters' => [
                'tab' => $tab,
                'payment_status' => $request->input('payment_status'),
                'payment_method' => $request->input('payment_method'),
                'payout_status' => $request->input('payout_status'),
                'search' => $request->input('search'),
                'per_page' => $perPage,
                'payments_sort' => $paymentsSort['sort'],
                'payments_direction' => $paymentsSort['direction'],
                'payouts_sort' => $payoutsSort['sort'],
                'payouts_direction' => $payoutsSort['direction'],
            ],
            'summaries' => [
                'buyer_pending_count' => Payment::query()
                    ->where('type', PaymentType::BuyerPayment->value)
                    ->where('status', PaymentStatus::Pending->value)
                    ->count(),
                'buyer_pending_sum' => (float) Payment::query()
                    ->where('type', PaymentType::BuyerPayment->value)
                    ->where('status', PaymentStatus::Pending->value)
                    ->sum('amount'),
                'buyer_paid_sum' => (float) Payment::query()
                    ->where('type', PaymentType::BuyerPayment->value)
                    ->where('status', PaymentStatus::Paid->value)
                    ->sum('amount'),
                'payout_pending_count' => Payout::query()
                    ->where('status', PayoutStatus::Pending->value)
                    ->count(),
                'payout_pending_sum' => (float) Payout::query()
                    ->where('status', PayoutStatus::Pending->value)
                    ->sum('amount'),
                'payout_paid_sum' => (float) Payout::query()
                    ->where('status', PayoutStatus::Paid->value)
                    ->sum('amount'),
                'commission_due_sum' => (float) Commission::query()
                    ->where('status', CommissionStatus::Due->value)
                    ->sum('commission_amount'),
            ],
        ]);
    }

    public function markPaid(Request $request, Payment $payment): RedirectResponse
    {
        $this->authorize('update', $payment);

        $validated = $request->validate([
            'confirmation_note' => ['nullable', 'string', 'max:1000'],
            'confirmation_reference' => ['nullable', 'string', 'max:100'],
        ]);

        $this->paymentConfirmationService->markBuyerPaymentPaid($request->user(), $payment, $validated);

        return back()->with('success', __('rml.admin.payments.marked_paid_flash'));
    }

    public function markPayoutPaid(Request $request, Payout $payout): RedirectResponse
    {
        $this->paymentConfirmationService->markSellerPayoutPaid($request->user(), $payout);

        return back()->with('success', __('rml.admin.payments.payout_marked_paid_flash'));
    }

    public function markCommissionPaid(Request $request, Commission $commission): RedirectResponse
    {
        $this->paymentConfirmationService->markCommissionPaid($request->user(), $commission);

        return back()->with('success', __('rml.admin.payments.commission_marked_paid_flash'));
    }

    public function cancel(Request $request, Payment $payment): RedirectResponse
    {
        $this->authorize('update', $payment);
        $this->paymentConfirmationService->cancelPendingPayment($request->user(), $payment);

        return back()->with('success', __('rml.admin.payments.cancelled_flash'));
    }

    public function markFailed(Request $request, Payment $payment): RedirectResponse
    {
        $this->authorize('update', $payment);
        $this->paymentConfirmationService->markFailed($request->user(), $payment);

        return back()->with('success', __('rml.admin.payments.failed_flash'));
    }

    public function updatePayment(Request $request, Payment $payment): RedirectResponse
    {
        $this->authorize('update', $payment);

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0'],
            'due_date' => ['nullable', 'date'],
            'status' => ['nullable', 'string', 'in:'.implode(',', PaymentStatus::values())],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $metadata = is_array($payment->metadata) ? $payment->metadata : [];
        if (array_key_exists('notes', $validated)) {
            $metadata['admin_notes'] = $validated['notes'];
        }

        $payment->update([
            'amount' => $validated['amount'],
            'due_date' => $validated['due_date'] ?? $payment->due_date,
            'status' => $validated['status'] ?? $payment->status,
            'metadata' => $metadata,
        ]);

        return back()->with('success', __('rml.admin.payments.updated_flash'));
    }

    public function destroyPayment(Request $request, Payment $payment): RedirectResponse
    {
        abort_unless($request->user()?->hasRole(UserRole::SuperAdmin->value), 403, __('rml.admin.payments.delete_forbidden'));
        $this->authorize('delete', $payment);

        if ($payment->status === PaymentStatus::Pending && $payment->purchases()->doesntExist()) {
            $payment->delete();
        } else {
            $payment->update([
                'status' => PaymentStatus::Cancelled,
                'cancelled_at' => now(),
            ]);
        }

        return back()->with('success', __('rml.admin.payments.deleted_flash'));
    }

    public function updatePayout(Request $request, Payout $payout): RedirectResponse
    {
        $this->authorize('viewAny', Payment::class);
        abort_unless(
            $request->user()?->can(Permissions::EDIT_PAYMENTS)
            || $request->user()?->can(Permissions::MANAGE_PAYMENTS)
            || $request->user()?->hasRole(UserRole::SuperAdmin->value),
            403,
        );

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0'],
            'due_date' => ['nullable', 'date'],
            'status' => ['nullable', 'string', 'in:'.implode(',', PayoutStatus::values())],
        ]);

        $payout->update([
            'amount' => $validated['amount'],
            'due_date' => $validated['due_date'] ?? $payout->due_date,
            'status' => $validated['status'] ?? $payout->status,
            'paid_at' => ($validated['status'] ?? null) === PayoutStatus::Paid->value
                ? ($payout->paid_at ?? now())
                : $payout->paid_at,
        ]);

        return back()->with('success', __('rml.admin.payments.updated_flash'));
    }

    public function destroyPayout(Request $request, Payout $payout): RedirectResponse
    {
        abort_unless($request->user()?->hasRole(UserRole::SuperAdmin->value), 403, __('rml.admin.payments.delete_forbidden'));
        $this->authorize('viewAny', Payment::class);

        if ($payout->status === PayoutStatus::Pending) {
            $payout->delete();
        } else {
            $payout->update(['status' => PayoutStatus::Cancelled]);
        }

        return back()->with('success', __('rml.admin.payments.deleted_flash'));
    }

    public function updateCommission(Request $request, Commission $commission): RedirectResponse
    {
        $this->authorize('viewAny', Payment::class);
        abort_unless(
            $request->user()?->can(Permissions::EDIT_PAYMENTS)
            || $request->user()?->can(Permissions::MANAGE_PAYMENTS)
            || $request->user()?->hasRole(UserRole::SuperAdmin->value),
            403,
        );

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0'],
            'due_date' => ['nullable', 'date'],
            'status' => ['nullable', 'string', 'in:'.implode(',', CommissionStatus::values())],
        ]);

        $commission->update([
            'commission_amount' => $validated['amount'],
            'due_at' => $validated['due_date'] ?? $commission->due_at,
            'status' => $validated['status'] ?? $commission->status,
            'paid_at' => ($validated['status'] ?? null) === CommissionStatus::Paid->value
                ? ($commission->paid_at ?? now())
                : $commission->paid_at,
        ]);

        return back()->with('success', __('rml.admin.payments.updated_flash'));
    }

    public function destroyCommission(Request $request, Commission $commission): RedirectResponse
    {
        abort_unless($request->user()?->hasRole(UserRole::SuperAdmin->value), 403, __('rml.admin.payments.delete_forbidden'));
        $this->authorize('viewAny', Payment::class);

        if (in_array($commission->status, [CommissionStatus::Due, CommissionStatus::Pending], true)) {
            $commission->delete();
        } else {
            $commission->update(['status' => CommissionStatus::Cancelled]);
        }

        return back()->with('success', __('rml.admin.payments.deleted_flash'));
    }

    public function regenerateInvoice(Request $request, Invoice $invoice): RedirectResponse
    {
        $this->authorize('viewAny', Payment::class);
        $this->invoiceDocumentService->regenerate($invoice);

        return back()->with('success', __('rml.admin.payments.invoice_regenerated_flash'));
    }

    public function resendEmail(Request $request, Payment $payment): RedirectResponse
    {
        $this->authorize('update', $payment);

        $payment->loadMissing(['payerUser:id,email', 'purchases.buyerUser:id,email']);

        $buyer = $payment->payerUser
            ?? $payment->purchases->first()?->buyerUser;

        abort_unless($buyer?->email, 404);

        if ($payment->status === PaymentStatus::Paid) {
            Mail::to($buyer->email)->send(new BuyerPaymentConfirmedMail($payment));
            Mail::to($buyer->email)->send(new LeadDetailsReleasedMail($payment));
        } else {
            Mail::to($buyer->email)->send(new BuyerPaymentCreatedMail($payment));
        }

        return back()->with('success', __('rml.admin.payments.email_resent_flash'));
    }

    /**
     * @deprecated Use resendEmail — kept for backwards-compatible route alias.
     */
    public function resendWhatsApp(Request $request, Payment $payment): RedirectResponse
    {
        return $this->resendEmail($request, $payment);
    }

    /**
     * @return array{0: LengthAwarePaginator, 1: array{sort: string, direction: string}}
     */
    private function paginateBuyerPayments(Request $request, int $perPage, bool $canEdit, bool $canDelete): array
    {
        $buyerQuery = Payment::query()
            ->where('type', PaymentType::BuyerPayment->value)
            ->with(['payerUser:id,name', 'payerCompany:id,name', 'purchases', 'invoices']);

        if ($request->filled('payment_status')) {
            $buyerQuery->where('status', $request->string('payment_status')->toString());
        }

        if ($request->filled('payment_method')) {
            $buyerQuery->where('method', $request->string('payment_method')->toString());
        }

        if ($request->filled('search')) {
            $search = '%'.$request->string('search')->toString().'%';
            $op = CaseInsensitiveSearch::operator();
            $buyerQuery->where(function ($q) use ($search, $op) {
                $q->where('payment_reference', $op, $search)
                    ->orWhereHas('payerCompany', fn ($cq) => $cq->where('name', $op, $search))
                    ->orWhereHas('payerUser', fn ($uq) => $uq->where('name', $op, $search));
            });
        }

        $paymentsSort = ListSort::apply(
            $buyerQuery,
            $request,
            [
                'reference' => 'payment_reference',
                'status' => 'status',
                'amount' => 'amount',
                'date' => 'due_date',
                'company' => fn (Builder $q, string $direction) => $q->orderBy(
                    DB::table('companies')
                        ->select('name')
                        ->whereColumn('companies.id', 'payments.payer_company_id')
                        ->limit(1),
                    $direction,
                ),
                'name' => fn (Builder $q, string $direction) => $q->orderBy(
                    DB::table('users')
                        ->select('name')
                        ->whereColumn('users.id', 'payments.payer_user_id')
                        ->limit(1),
                    $direction,
                ),
            ],
            'date',
            'desc',
            'payments_sort',
            'payments_direction',
        );

        $buyerPayments = $buyerQuery
            ->paginate($perPage, ['*'], 'payments_page')
            ->withQueryString()
            ->through(fn (Payment $payment) => $this->transformPayment($payment, $canEdit, $canDelete));

        return [$buyerPayments, $paymentsSort];
    }

    /**
     * @return array{0: LengthAwarePaginator, 1: array{sort: string, direction: string}}
     */
    private function paginateSellerPayoutRows(Request $request, int $perPage, bool $canEdit, bool $canDelete): array
    {
        $sort = $request->string('payouts_sort')->toString() ?: 'date';
        $direction = strtolower($request->string('payouts_direction')->toString()) === 'asc' ? 'asc' : 'desc';
        if (! in_array($sort, ['reference', 'status', 'amount', 'date', 'company', 'name'], true)) {
            $sort = 'date';
        }

        $payoutQuery = Payout::query()
            ->with(['sellerCompany:id,name', 'sellerUser:id,name']);

        if ($request->filled('payout_status')) {
            $payoutQuery->where('status', $request->string('payout_status')->toString());
        }

        if ($request->filled('search')) {
            $search = '%'.$request->string('search')->toString().'%';
            $op = CaseInsensitiveSearch::operator();
            $payoutQuery->where(function ($q) use ($search, $op) {
                $q->where('payout_reference', $op, $search)
                    ->orWhere('notes', $op, $search)
                    ->orWhereHas('sellerCompany', fn ($cq) => $cq->where('name', $op, $search))
                    ->orWhereHas('sellerUser', fn ($uq) => $uq->where('name', $op, $search));
            });
        }

        $payouts = $payoutQuery->get();
        $statementMap = $this->statementIdsForPayouts($payouts);
        $leadsByRef = $this->leadsForPayoutNotes($payouts);

        $rows = $payouts->map(
            fn (Payout $payout) => $this->transformUnifiedPayout($payout, $leadsByRef, $statementMap, $canEdit, $canDelete)
        );

        $commissionQuery = Commission::query()
            ->with([
                'sellerUser:id,name',
                'sellerUser.sellerProfile:id,user_id,seller_type',
                'sellerCompany:id,name',
                'lead:id,lead_reference,scheme_id,size_m2',
                'lead.scheme:id,name',
            ])
            ->whereIn('status', [
                CommissionStatus::Due->value,
                CommissionStatus::Paid->value,
                CommissionStatus::Pending->value,
            ])
            ->whereHas('sellerUser.sellerProfile', function (Builder $q) {
                $q->where('seller_type', SellerType::IndividualAgent->value);
            });

        if ($request->filled('payout_status')) {
            $status = $request->string('payout_status')->toString();
            if ($status === PayoutStatus::Pending->value) {
                $commissionQuery->whereIn('status', [
                    CommissionStatus::Due->value,
                    CommissionStatus::Pending->value,
                ]);
            } else {
                $commissionQuery->where('status', $status);
            }
        }

        if ($request->filled('search')) {
            $search = '%'.$request->string('search')->toString().'%';
            $op = CaseInsensitiveSearch::operator();
            $commissionQuery->where(function ($q) use ($search, $op) {
                $q->where('commission_reference', $op, $search)
                    ->orWhereHas('sellerCompany', fn ($cq) => $cq->where('name', $op, $search))
                    ->orWhereHas('sellerUser', fn ($uq) => $uq->where('name', $op, $search))
                    ->orWhereHas('lead', fn ($lq) => $lq->where('lead_reference', $op, $search));
            });
        }

        $commissionRows = $commissionQuery->get()->map(
            fn (Commission $commission) => $this->transformUnifiedCommission($commission, $canEdit, $canDelete)
        );

        $merged = $rows->concat($commissionRows)->values();

        $merged = $merged->sort(function (array $a, array $b) use ($sort, $direction) {
            $av = match ($sort) {
                'reference' => $a['reference'] ?? '',
                'status' => $a['status'] ?? '',
                'amount' => $a['total_payout'] ?? 0,
                'company', 'name' => $a['recipient'] ?? '',
                default => $a['due_date'] ?? '',
            };
            $bv = match ($sort) {
                'reference' => $b['reference'] ?? '',
                'status' => $b['status'] ?? '',
                'amount' => $b['total_payout'] ?? 0,
                'company', 'name' => $b['recipient'] ?? '',
                default => $b['due_date'] ?? '',
            };

            if ($av == $bv) {
                return 0;
            }

            $cmp = $av <=> $bv;

            return $direction === 'asc' ? $cmp : -$cmp;
        })->values();

        $page = max(1, (int) $request->input('payouts_page', 1));
        $total = $merged->count();
        $slice = $merged->forPage($page, $perPage)->values();

        $paginator = new LengthAwarePaginator(
            $slice,
            $total,
            $perPage,
            $page,
            [
                'path' => $request->url(),
                'query' => $request->query(),
                'pageName' => 'payouts_page',
            ],
        );

        return [$paginator, ['sort' => $sort, 'direction' => $direction]];
    }

    /**
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    private function emptyPaginator(Request $request, int $perPage, string $pageName): LengthAwarePaginator
    {
        return new LengthAwarePaginator(
            [],
            0,
            $perPage,
            1,
            [
                'path' => $request->url(),
                'query' => $request->query(),
                'pageName' => $pageName,
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function transformPayment(Payment $payment, bool $canEdit = false, bool $canDelete = false): array
    {
        $invoice = $payment->invoices->firstWhere('type', InvoiceType::BuyerInvoice);
        $receipt = $payment->invoices->firstWhere('type', InvoiceType::BuyerReceipt);
        $purchase = $payment->purchases->first();

        return [
            'id' => $payment->id,
            'payment_reference' => $payment->payment_reference,
            'type' => $payment->type?->value,
            'status' => $payment->status?->value,
            'method' => $payment->method?->value,
            'provider' => $payment->provider,
            'provider_status' => $payment->provider_status,
            'stripe_checkout_session_id' => $payment->stripe_checkout_session_id,
            'stripe_payment_intent_id' => $payment->stripe_payment_intent_id,
            'amount' => $payment->amount !== null ? (float) $payment->amount : null,
            'currency' => $payment->currency,
            'payer_name' => $payment->payerUser?->name,
            'payer_company' => $payment->payerCompany?->name,
            'due_date' => $payment->due_date?->toDateString(),
            'paid_at' => $payment->paid_at?->toIso8601String(),
            'created_at' => $payment->created_at?->toIso8601String(),
            'purchase_id' => $purchase?->id,
            'purchase_reference' => $purchase?->purchase_reference,
            'details_released' => $purchase?->status?->value === 'paid' && $payment->status === PaymentStatus::Paid,
            'invoice_id' => $invoice?->id,
            'receipt_id' => $receipt?->id,
            'can_mark_paid' => $payment->status === PaymentStatus::Pending,
            'is_stripe' => $payment->provider === 'stripe',
            'can_cancel' => $payment->status === PaymentStatus::Pending,
            'can_fail' => $payment->status === PaymentStatus::Pending,
            'can_resend_email' => $payment->status === PaymentStatus::Pending
                || $payment->status === PaymentStatus::Paid,
            'can_edit' => $canEdit,
            'can_delete' => $canDelete,
            'notes' => $payment->metadata['admin_notes'] ?? $payment->metadata['confirmation_note'] ?? null,
            'confirmation_note' => $payment->metadata['confirmation_note'] ?? null,
        ];
    }

    /**
     * @param  Collection<int, Payout>  $payouts
     * @return array<string, int|null> keyed by payout_reference
     */
    private function statementIdsForPayouts(Collection $payouts): array
    {
        if ($payouts->isEmpty()) {
            return [];
        }

        $companyIds = $payouts->pluck('seller_company_id')->filter()->unique()->values();
        $userIds = $payouts->pluck('seller_user_id')->filter()->unique()->values();

        $invoices = Invoice::query()
            ->where('type', InvoiceType::SellerStatement->value)
            ->when($companyIds->isNotEmpty(), fn ($q) => $q->whereIn('company_id', $companyIds))
            ->when($userIds->isNotEmpty(), fn ($q) => $q->whereIn('user_id', $userIds))
            ->get(['id', 'company_id', 'user_id', 'pdf_path']);

        $map = [];
        foreach ($payouts as $payout) {
            $reference = (string) $payout->payout_reference;
            $map[$reference] = $invoices
                ->first(function (Invoice $invoice) use ($payout, $reference) {
                    return (int) $invoice->company_id === (int) $payout->seller_company_id
                        && (int) $invoice->user_id === (int) $payout->seller_user_id
                        && str_contains((string) $invoice->pdf_path, $reference);
                })
                ?->id;
        }

        return $map;
    }

    /**
     * @param  Collection<int, Payout>  $payouts
     * @return Collection<string, Lead>
     */
    private function leadsForPayoutNotes(Collection $payouts): Collection
    {
        $refs = $payouts
            ->map(fn (Payout $payout) => $this->leadReferenceFromNotes((string) ($payout->notes ?? '')))
            ->filter()
            ->unique()
            ->values();

        if ($refs->isEmpty()) {
            return collect();
        }

        return Lead::query()
            ->with('scheme:id,name')
            ->whereIn('lead_reference', $refs)
            ->get(['id', 'lead_reference', 'scheme_id', 'size_m2'])
            ->keyBy('lead_reference');
    }

    private function leadReferenceFromNotes(string $notes): ?string
    {
        if (preg_match('/\b(LD-[A-Z0-9-]+)\b/i', $notes, $matches) === 1) {
            return strtoupper($matches[1]);
        }

        return null;
    }

    /**
     * @param  Collection<string, Lead>  $leadsByRef
     * @param  array<string, int|null>  $statementMap
     * @return array<string, mixed>
     */
    private function transformUnifiedPayout(
        Payout $payout,
        Collection $leadsByRef,
        array $statementMap,
        bool $canEdit,
        bool $canDelete,
    ): array {
        $leadRef = $this->leadReferenceFromNotes((string) ($payout->notes ?? ''));
        $lead = $leadRef ? $leadsByRef->get($leadRef) : null;
        $size = $lead?->size_m2 !== null ? (float) $lead->size_m2 : null;
        $isCompany = $payout->seller_company_id !== null;

        $metric = $size !== null && $size > 0
            ? rtrim(rtrim(number_format($size, 2, '.', ''), '0'), '.').' m²'
            : '—';

        $rate = $size !== null && $size > 0
            ? '€'.number_format(LeadPricingService::SELLER_PAYOUT_PER_M2, 2, '.', '').'/m²'
            : '—';

        return [
            'id' => $payout->id,
            'source' => 'payout',
            'reference' => $payout->payout_reference,
            'recipient' => $isCompany
                ? ($payout->sellerCompany?->name ?? $payout->sellerUser?->name ?? '—')
                : ($payout->sellerUser?->name ?? $payout->sellerCompany?->name ?? '—'),
            'recipient_type' => $isCompany ? 'company' : 'agent',
            'lead_reference' => $leadRef,
            'scheme' => $lead?->scheme?->name,
            'metric' => $metric,
            'rate' => $rate,
            'total_payout' => $payout->amount !== null ? (float) $payout->amount : null,
            'currency' => $payout->currency,
            'status' => $payout->status?->value,
            'due_date' => $payout->due_date?->toDateString(),
            'paid_date' => $payout->paid_at?->toDateString(),
            'notes' => $payout->notes,
            'statement_id' => $statementMap[(string) $payout->payout_reference] ?? null,
            'can_mark_paid' => $payout->status === PayoutStatus::Pending,
            'can_edit' => $canEdit,
            'can_delete' => $canDelete,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function transformUnifiedCommission(Commission $commission, bool $canEdit, bool $canDelete): array
    {
        $isCompany = $commission->seller_company_id !== null;
        $size = $commission->lead?->size_m2 !== null ? (float) $commission->lead->size_m2 : null;
        $metric = $size !== null && $size > 0
            ? rtrim(rtrim(number_format($size, 2, '.', ''), '0'), '.').' m²'
            : '—';

        $rate = $commission->percentage !== null
            ? rtrim(rtrim(number_format((float) $commission->percentage, 2, '.', ''), '0'), '.').'%'
            : '—';

        $status = $commission->status?->value;
        // Surface commission "due" as pending in the shared status filter UI.
        if ($status === CommissionStatus::Due->value) {
            $status = PayoutStatus::Pending->value;
        }

        return [
            'id' => $commission->id,
            'source' => 'commission',
            'reference' => $commission->commission_reference,
            'recipient' => $isCompany
                ? ($commission->sellerCompany?->name ?? $commission->sellerUser?->name ?? '—')
                : ($commission->sellerUser?->name ?? $commission->sellerCompany?->name ?? '—'),
            'recipient_type' => $isCompany ? 'company' : 'agent',
            'lead_reference' => $commission->lead?->lead_reference,
            'scheme' => $commission->lead?->scheme?->name,
            'metric' => $metric,
            'rate' => $rate,
            'total_payout' => $commission->commission_amount !== null ? (float) $commission->commission_amount : null,
            'currency' => 'EUR',
            'status' => $status,
            'due_date' => $commission->due_at?->toDateString(),
            'paid_date' => $commission->paid_at?->toDateString(),
            'notes' => null,
            'statement_id' => Invoice::query()
                ->where('type', InvoiceType::CommissionStatement->value)
                ->where('user_id', $commission->seller_user_id)
                ->where('total', $commission->commission_amount)
                ->latest('id')
                ->value('id'),
            'can_mark_paid' => in_array($commission->status, [CommissionStatus::Due, CommissionStatus::Pending], true),
            'can_edit' => $canEdit,
            'can_delete' => $canDelete,
        ];
    }
}
