<?php

namespace App\Http\Controllers\Buyer;

use App\Enums\InvoiceType;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\PurchaseStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Purchase;
use App\Services\Buyer\LeadReleaseService;
use App\Services\Payments\PaymentProviderManager;
use App\Services\Payments\PaymentService;
use App\Services\WhatsApp\WhatsAppMessageService;
use App\Support\BuyerLeadPresenter;
use App\Support\ListPagination;
use App\Support\ListSort;
use App\Support\Permissions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PurchaseController extends Controller
{
    public function __construct(
        private readonly LeadReleaseService $releaseService,
        private readonly PaymentService $paymentService,
        private readonly WhatsAppMessageService $whatsAppMessageService,
    ) {}

    public function index(Request $request): Response
    {
        abort_unless(
            $request->user()?->can(Permissions::VIEW_PURCHASED_LEADS)
                || $request->user()?->can(Permissions::BUY_LEADS),
            403,
        );

        $companyId = $request->user()->buyerProfile?->company_id;

        $query = Purchase::query()
            ->where('buyer_company_id', $companyId)
            ->with(['payment.invoices', 'items.lead.scheme', 'items.lead.zone', 'items.leadPackage']);

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }

        if ($request->filled('search')) {
            $search = '%'.$request->string('search')->toString().'%';
            $query->where(function (Builder $q) use ($search) {
                $q->where('purchase_reference', 'like', $search)
                    ->orWhereHas('items.lead', fn (Builder $lq) => $lq->where('lead_reference', 'like', $search))
                    ->orWhereHas('items.leadPackage', fn (Builder $pq) => $pq->where('package_reference', 'like', $search));
            });
        }

        $sortState = ListSort::apply(
            $query,
            $request,
            [
                'reference' => 'purchases.purchase_reference',
                'status' => 'purchases.status',
                'amount' => 'purchases.total_amount',
                'date' => 'purchases.purchased_at',
                'payment_status' => function (Builder $q, string $direction): void {
                    $q->orderBy(
                        Payment::query()
                            ->select('status')
                            ->whereColumn('payments.id', 'purchases.payment_id')
                            ->limit(1),
                        $direction,
                    );
                },
            ],
            'date',
            'desc',
        );

        $query->orderBy('purchases.id', $sortState['direction'] === 'asc' ? 'asc' : 'desc');

        $perPage = ListPagination::perPage($request);

        $purchases = $query
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (Purchase $purchase) => $this->transformPurchase($purchase, $request->user(), summary: true));

        return Inertia::render('Buyer/Purchases/Index', [
            'purchases' => $purchases,
            'filters' => [
                'status' => $request->input('status'),
                'search' => $request->input('search'),
                'sort' => $sortState['sort'],
                'direction' => $sortState['direction'],
                'per_page' => $perPage,
            ],
            'filterOptions' => [
                'statuses' => PurchaseStatus::values(),
            ],
        ]);
    }

    public function show(Request $request, Purchase $purchase): Response
    {
        abort_unless($request->user()?->can('view', $purchase), 403);

        $purchase->load([
            'payment.invoices',
            'items.lead.scheme',
            'items.lead.zone',
            'items.lead.evidenceFiles',
            'items.leadPackage',
        ]);

        $cardConfigured = app(PaymentProviderManager::class)->cardConfigured();
        $isAdminStaff = $request->user()?->hasAnyRole([
            UserRole::SuperAdmin->value,
            UserRole::AdminStaff->value,
        ]) ?? false;

        return Inertia::render('Buyer/Purchases/Show', [
            'purchase' => $this->transformPurchase($purchase, $request->user(), summary: false, cardConfigured: $cardConfigured),
            'whatsapp_configured' => $this->whatsAppMessageService->isConfigured(),
            'card_configured' => $cardConfigured,
            'mollie_configured' => $cardConfigured,
            'card_provider_admin_hint' => $isAdminStaff && ! $cardConfigured
                ? __('rml.payments.card_key_missing_hint')
                : null,
        ]);
    }

    public function sendWhatsApp(Request $request, Purchase $purchase): RedirectResponse
    {
        abort_unless($request->user()?->can('view', $purchase), 403);

        $this->whatsAppMessageService->sendPurchasedLeadDetails(
            $request->user(),
            $purchase,
            null,
            $request->input('to_phone'),
        );

        return back()->with('success', __('rml.whatsapp.sent'));
    }

    /**
     * @return array<string, mixed>
     */
    private function transformPurchase($purchase, $user, bool $summary, ?bool $cardConfigured = null): array
    {
        $firstItem = $purchase->items->first();
        $package = $firstItem?->leadPackage;
        $released = $this->releaseService->purchaseIsReleased($purchase);
        $payment = $purchase->payment;
        $invoice = $payment?->invoices?->firstWhere('type', InvoiceType::BuyerInvoice);
        $receipt = $payment?->invoices?->firstWhere('type', InvoiceType::BuyerReceipt);
        $cardConfigured ??= app(PaymentProviderManager::class)->cardConfigured();
        $isCard = $payment?->method === PaymentMethod::Card;
        $isPayableStatus = in_array($payment?->status, [
            PaymentStatus::Pending,
            PaymentStatus::Failed,
            PaymentStatus::Cancelled,
        ], true);
        $cardBlocked = $isCard && $isPayableStatus && ! $cardConfigured;

        $data = [
            'id' => $purchase->id,
            'purchase_reference' => $purchase->purchase_reference,
            'status' => $purchase->status?->value,
            'total_amount' => $purchase->total_amount !== null ? (float) $purchase->total_amount : null,
            'total_size_m2' => $purchase->total_size_m2 !== null ? (float) $purchase->total_size_m2 : null,
            'purchased_at' => $purchase->purchased_at?->toIso8601String(),
            'created_at' => $purchase->created_at?->toIso8601String(),
            'payment' => $payment ? [
                'id' => $payment->id,
                'payment_reference' => $payment->payment_reference,
                'status' => $payment->status?->value,
                'method' => $payment->method?->value,
                'provider' => $payment->provider,
                'provider_status' => $payment->provider_status,
                'stripe_checkout_session_id' => $payment->stripe_checkout_session_id,
                'stripe_payment_intent_id' => $payment->stripe_payment_intent_id,
                'amount' => $payment->amount !== null ? (float) $payment->amount : null,
                'due_date' => $payment->due_date?->toDateString(),
                'paid_at' => $payment->paid_at?->toIso8601String(),
                'can_pay' => $isPayableStatus
                    && $purchase->status === PurchaseStatus::Pending
                    && ! $cardBlocked,
                'card_provider_missing' => $cardBlocked
                    || (bool) ($payment->metadata['provider_not_configured'] ?? false),
                'bank_instructions' => $this->paymentService->bankInstructions($payment),
            ] : null,
            'details_released' => $released,
            'invoice_id' => $invoice?->id,
            'receipt_id' => $receipt?->id,
            'can_download_invoice' => (bool) $invoice,
            'can_download_receipt' => (bool) $receipt && $released,
            'can_send_whatsapp' => $released,
            'package' => $package ? [
                'id' => $package->id,
                'package_reference' => $package->package_reference,
                'name' => $package->name,
                'zone_mix' => $package->zone_mix,
            ] : null,
            'item_count' => $purchase->items->count(),
            'scheme' => $firstItem?->lead?->scheme?->name,
            'zone' => $package
                ? collect($package->zone_mix ?? [])->keys()->implode(', ')
                : $firstItem?->lead?->zone?->code,
            'display_reference' => $package?->package_reference
                ?? $firstItem?->lead?->lead_reference
                ?? $purchase->purchase_reference,
        ];

        if (! $summary) {
            $data['leads'] = $purchase->items
                ->map(function ($item) use ($user) {
                    if (! $item->lead) {
                        return null;
                    }

                    return BuyerLeadPresenter::presentForPurchase($item->lead, $user, $this->releaseService);
                })
                ->filter()
                ->values()
                ->all();
        }

        return $data;
    }
}
