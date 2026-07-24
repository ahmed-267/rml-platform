<?php

namespace App\Http\Controllers\Buyer;

use App\Enums\InvoiceType;
use App\Enums\PaymentStatus;
use App\Enums\PurchaseStatus;
use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Purchase;
use App\Services\Buyer\BuyerPurchaseService;
use App\Services\Payments\PaymentProviderManager;
use App\Services\Payments\PaymentService;
use App\Support\CheckoutRedirect;
use App\Support\ListPagination;
use App\Support\ListSort;
use App\Support\Permissions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class PaymentController extends Controller
{
    public function __construct(
        private readonly BuyerPurchaseService $purchaseService,
        private readonly PaymentService $paymentService,
    ) {}

    public function index(Request $request): Response
    {
        abort_unless($request->user()?->can(Permissions::BUY_LEADS), 403);

        $companyId = $request->user()->buyerProfile?->company_id;
        $tab = $request->input('tab', 'pending');
        if (! in_array($tab, ['pending', 'history'], true)) {
            $tab = 'pending';
        }

        $baseQuery = Payment::query()
            ->where('payer_company_id', $companyId)
            ->with(['purchases.items.lead', 'purchases.items.leadPackage', 'invoices']);

        $pendingCount = (clone $baseQuery)->whereIn('status', [
            PaymentStatus::Pending->value,
            PaymentStatus::Failed->value,
            PaymentStatus::Cancelled->value,
        ])->count();
        $historyCount = (clone $baseQuery)->where('status', PaymentStatus::Paid->value)->count();

        $query = clone $baseQuery;
        if ($tab === 'pending') {
            $query->whereIn('status', [
                PaymentStatus::Pending->value,
                PaymentStatus::Failed->value,
                PaymentStatus::Cancelled->value,
            ]);
        } else {
            $query->where('status', PaymentStatus::Paid->value);
        }

        $sortState = ListSort::apply(
            $query,
            $request,
            [
                'reference' => 'payments.payment_reference',
                'status' => 'payments.status',
                'amount' => 'payments.amount',
                'method' => 'payments.method',
                'due_date' => 'payments.due_date',
                'date' => 'payments.created_at',
            ],
            'date',
            'desc',
        );

        $query->orderBy('payments.id', $sortState['direction'] === 'asc' ? 'asc' : 'desc');

        $perPage = ListPagination::perPage($request);

        $payments = $query
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (Payment $payment) => $this->transformPayment($payment));

        $pendingPurchases = Purchase::query()
            ->where('buyer_company_id', $companyId)
            ->where('status', PurchaseStatus::Pending)
            ->withCount('items')
            ->get();

        $cardConfigured = app(PaymentProviderManager::class)->cardConfigured();

        return Inertia::render('Buyer/Payments', [
            'kpis' => [
                'pending_to_buy' => (int) $pendingPurchases->sum('items_count'),
                'pending_payments' => $pendingCount,
                'leads_purchased' => Purchase::query()
                    ->where('buyer_company_id', $companyId)
                    ->where('status', PurchaseStatus::Paid)
                    ->withCount('items')
                    ->get()
                    ->sum('items_count'),
                'total_spent' => (float) Payment::query()
                    ->where('payer_company_id', $companyId)
                    ->where('status', PaymentStatus::Paid)
                    ->sum('amount'),
            ],
            'payments' => $payments,
            'tab' => $tab,
            'tabCounts' => [
                'pending' => $pendingCount,
                'history' => $historyCount,
            ],
            'filters' => [
                'tab' => $tab,
                'sort' => $sortState['sort'],
                'direction' => $sortState['direction'],
                'per_page' => $perPage,
            ],
            'card_configured' => $cardConfigured,
            'mollie_configured' => $cardConfigured,
            'bank_transfer' => config('payments.bank_transfer'),
        ]);
    }

    public function pay(Request $request, Payment $payment): SymfonyResponse
    {
        abort_unless($request->user()?->can('view', $payment), 403);

        $purchase = $payment->purchases()->first();
        abort_unless(
            in_array($payment->status, [
                PaymentStatus::Pending,
                PaymentStatus::Failed,
                PaymentStatus::Cancelled,
            ], true),
            422,
        );

        if ($purchase && $purchase->status !== PurchaseStatus::Pending) {
            abort(422);
        }

        $result = $this->paymentService->initiate($payment, $request->user());

        if (! empty($result['checkout_url'])) {
            return CheckoutRedirect::to($result['checkout_url']);
        }

        if (! empty($result['error'])) {
            return redirect()
                ->route($purchase ? 'buyer.purchases.show' : 'buyer.payments', $purchase ?? [])
                ->withErrors(['payment' => $result['error']]);
        }

        return redirect()
            ->route($purchase ? 'buyer.purchases.show' : 'buyer.payments', $purchase ?? [])
            ->with('success', __('rml.buyer.payments.bank_transfer_ready'));
    }

    public function returnFromProvider(Request $request, Payment $payment): RedirectResponse
    {
        return $this->success($request, $payment);
    }

    public function success(Request $request, Payment $payment): Response|RedirectResponse
    {
        abort_unless($request->user()?->can('view', $payment), 403);

        // Never mark paid on success URL alone — optional refresh only.
        $this->paymentService->refreshFromProvider($payment->fresh() ?? $payment);
        $payment = $payment->fresh(['purchases']) ?? $payment;
        $purchase = $payment->purchases->first();

        return Inertia::render('Buyer/Payments/Success', [
            'payment' => $this->transformPayment($payment),
            'purchase_id' => $purchase?->id,
            'confirmed' => $payment->status === PaymentStatus::Paid,
        ]);
    }

    public function cancelled(Request $request, Payment $payment): Response
    {
        abort_unless($request->user()?->can('view', $payment), 403);

        $purchase = $payment->purchases()->first();

        return Inertia::render('Buyer/Payments/Cancelled', [
            'payment' => $this->transformPayment($payment->fresh() ?? $payment),
            'purchase_id' => $purchase?->id,
        ]);
    }

    public function cancel(Request $request, Payment $payment): RedirectResponse
    {
        abort_unless($request->user()?->can('view', $payment), 403);

        $purchase = $payment->purchases()->first();
        abort_unless($purchase, 404);

        $this->purchaseService->cancelPendingPurchase($request->user(), $purchase);

        return back()->with('success', __('rml.buyer.payments.cancelled'));
    }

    /**
     * @return array<string, mixed>
     */
    private function transformPayment(Payment $payment): array
    {
        $payment->loadMissing(['purchases.items.lead', 'purchases.items.leadPackage', 'invoices']);
        $purchase = $payment->purchases->first();
        $item = $purchase?->items->first();
        $invoice = $payment->invoices->firstWhere('type', InvoiceType::BuyerInvoice);
        $receipt = $payment->invoices->firstWhere('type', InvoiceType::BuyerReceipt);
        $cardConfigured = app(PaymentProviderManager::class)->cardConfigured();
        $canRetry = in_array($payment->status, [
            PaymentStatus::Pending,
            PaymentStatus::Failed,
            PaymentStatus::Cancelled,
        ], true)
            && $purchase
            && $purchase->status === PurchaseStatus::Pending
            && (! ($payment->method?->value === 'card') || $cardConfigured || $payment->method?->value === 'manual_bank_transfer');

        $cardBlocked = $payment->method?->value === 'card'
            && in_array($payment->status, [PaymentStatus::Pending, PaymentStatus::Failed, PaymentStatus::Cancelled], true)
            && ! $cardConfigured;

        return [
            'id' => $payment->id,
            'payment_reference' => $payment->payment_reference,
            'amount' => $payment->amount !== null ? (float) $payment->amount : null,
            'currency' => $payment->currency,
            'method' => $payment->method?->value,
            'status' => $payment->status?->value,
            'provider' => $payment->provider,
            'provider_status' => $payment->provider_status,
            'stripe_checkout_session_id' => $payment->stripe_checkout_session_id,
            'stripe_payment_intent_id' => $payment->stripe_payment_intent_id,
            'due_date' => $payment->due_date?->toDateString(),
            'paid_at' => $payment->paid_at?->toIso8601String(),
            'created_at' => $payment->created_at?->toIso8601String(),
            'purchase_id' => $purchase?->id,
            'purchase_reference' => $purchase?->purchase_reference,
            'display_reference' => $item?->leadPackage?->package_reference
                ?? $item?->lead?->lead_reference
                ?? $purchase?->purchase_reference,
            'can_cancel' => $payment->status === PaymentStatus::Pending
                && $purchase?->status === PurchaseStatus::Pending,
            'can_pay' => $canRetry && ! $cardBlocked,
            'card_provider_missing' => $cardBlocked,
            'bank_instructions' => $this->paymentService->bankInstructions($payment),
            'invoice_id' => $invoice?->id,
            'receipt_id' => $receipt?->id,
            'can_download_invoice' => (bool) $invoice,
            'can_download_receipt' => (bool) $receipt && $payment->status === PaymentStatus::Paid,
        ];
    }
}
