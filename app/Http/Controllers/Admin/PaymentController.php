<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CommissionStatus;
use App\Enums\InvoiceType;
use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Enums\PayoutStatus;
use App\Http\Controllers\Controller;
use App\Mail\BuyerPaymentConfirmedMail;
use App\Mail\BuyerPaymentCreatedMail;
use App\Mail\LeadDetailsReleasedMail;
use App\Models\Commission;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Payout;
use App\Services\Admin\PaymentConfirmationService;
use App\Services\Documents\InvoiceDocumentService;
use App\Support\ListPagination;
use App\Support\ListSort;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

        $perPage = ListPagination::perPage($request);

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
            $buyerQuery->where(function ($q) use ($search) {
                $q->where('payment_reference', 'like', $search)
                    ->orWhereHas('payerCompany', fn ($cq) => $cq->where('name', 'like', $search));
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
            ->through(fn (Payment $payment) => $this->transformPayment($payment));

        $payoutQuery = Payout::query()
            ->with(['sellerCompany:id,name', 'sellerUser:id,name']);

        if ($request->filled('payout_status')) {
            $payoutQuery->where('status', $request->string('payout_status')->toString());
        }

        $payoutsSort = ListSort::apply(
            $payoutQuery,
            $request,
            [
                'reference' => 'payout_reference',
                'status' => 'status',
                'amount' => 'amount',
                'date' => 'due_date',
                'company' => fn (Builder $q, string $direction) => $q->orderBy(
                    DB::table('companies')
                        ->select('name')
                        ->whereColumn('companies.id', 'payouts.seller_company_id')
                        ->limit(1),
                    $direction,
                ),
                'name' => fn (Builder $q, string $direction) => $q->orderBy(
                    DB::table('users')
                        ->select('name')
                        ->whereColumn('users.id', 'payouts.seller_user_id')
                        ->limit(1),
                    $direction,
                ),
            ],
            'date',
            'desc',
            'payouts_sort',
            'payouts_direction',
        );

        $sellerPayouts = $payoutQuery
            ->paginate($perPage, ['*'], 'payouts_page')
            ->withQueryString()
            ->through(fn (Payout $payout) => $this->transformPayout($payout));

        $commissionQuery = Commission::query()
            ->with(['sellerUser:id,name', 'sellerCompany:id,name', 'lead:id,lead_reference'])
            ->whereIn('status', [CommissionStatus::Due->value, CommissionStatus::Paid->value, CommissionStatus::Pending->value]);

        $commissionsSort = ListSort::apply(
            $commissionQuery,
            $request,
            [
                'reference' => 'commission_reference',
                'status' => 'status',
                'amount' => 'commission_amount',
                'date' => 'due_at',
                'company' => fn (Builder $q, string $direction) => $q->orderBy(
                    DB::table('companies')
                        ->select('name')
                        ->whereColumn('companies.id', 'commissions.seller_company_id')
                        ->limit(1),
                    $direction,
                ),
                'name' => fn (Builder $q, string $direction) => $q->orderBy(
                    DB::table('users')
                        ->select('name')
                        ->whereColumn('users.id', 'commissions.seller_user_id')
                        ->limit(1),
                    $direction,
                ),
            ],
            'date',
            'desc',
            'commissions_sort',
            'commissions_direction',
        );

        $commissions = $commissionQuery
            ->paginate($perPage, ['*'], 'commissions_page')
            ->withQueryString()
            ->through(fn (Commission $commission) => [
                'id' => $commission->id,
                'commission_reference' => $commission->commission_reference,
                'status' => $commission->status?->value,
                'amount' => $commission->commission_amount !== null ? (float) $commission->commission_amount : null,
                'percentage' => $commission->percentage !== null ? (float) $commission->percentage : null,
                'seller_name' => $commission->sellerUser?->name,
                'seller_company' => $commission->sellerCompany?->name,
                'lead_reference' => $commission->lead?->lead_reference,
                'due_at' => $commission->due_at?->toIso8601String(),
                'paid_at' => $commission->paid_at?->toIso8601String(),
                'can_mark_paid' => in_array($commission->status, [CommissionStatus::Due, CommissionStatus::Pending], true),
                'statement_id' => Invoice::query()
                    ->where('type', InvoiceType::CommissionStatement->value)
                    ->where('user_id', $commission->seller_user_id)
                    ->where('total', $commission->commission_amount)
                    ->latest('id')
                    ->value('id'),
            ]);

        return Inertia::render('Admin/Payments/Index', [
            'buyerPayments' => $buyerPayments,
            'sellerPayouts' => $sellerPayouts,
            'commissions' => $commissions,
            'filters' => [
                'payment_status' => $request->input('payment_status'),
                'payment_method' => $request->input('payment_method'),
                'payout_status' => $request->input('payout_status'),
                'search' => $request->input('search'),
                'per_page' => $perPage,
                'payments_sort' => $paymentsSort['sort'],
                'payments_direction' => $paymentsSort['direction'],
                'payouts_sort' => $payoutsSort['sort'],
                'payouts_direction' => $payoutsSort['direction'],
                'commissions_sort' => $commissionsSort['sort'],
                'commissions_direction' => $commissionsSort['direction'],
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
     * @return array<string, mixed>
     */
    private function transformPayment(Payment $payment): array
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
            'confirmation_note' => $payment->metadata['confirmation_note'] ?? null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function transformPayout(Payout $payout): array
    {
        $statementId = Invoice::query()
            ->where('type', InvoiceType::SellerStatement->value)
            ->where('company_id', $payout->seller_company_id)
            ->where('user_id', $payout->seller_user_id)
            ->get()
            ->first(fn (Invoice $invoice) => str_contains((string) $invoice->pdf_path, $payout->payout_reference))
            ?->id;

        return [
            'id' => $payout->id,
            'payout_reference' => $payout->payout_reference,
            'status' => $payout->status?->value,
            'amount' => $payout->amount !== null ? (float) $payout->amount : null,
            'currency' => $payout->currency,
            'seller_name' => $payout->sellerUser?->name,
            'seller_company' => $payout->sellerCompany?->name,
            'due_date' => $payout->due_date?->toDateString(),
            'paid_at' => $payout->paid_at?->toIso8601String(),
            'notes' => $payout->notes,
            'statement_id' => $statementId,
            'can_mark_paid' => $payout->status === PayoutStatus::Pending,
        ];
    }
}
