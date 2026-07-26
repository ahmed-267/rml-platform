<?php

namespace App\Http\Controllers\Buyer;

use App\Enums\PaymentMethod;
use App\Enums\PurchaseStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Buyer\StoreLeadPurchaseRequest;
use App\Models\Purchase;
use App\Models\Scheme;
use App\Models\User;
use App\Models\Zone;
use App\Services\Buyer\BuyerPurchaseService;
use App\Services\Buyer\LeadAvailabilityService;
use App\Services\LeadPricingService;
use App\Services\Payments\PaymentProviderManager;
use App\Services\Payments\PaymentService;
use App\Support\BuyerLeadPresenter;
use App\Support\CheckoutRedirect;
use App\Support\ListPagination;
use App\Support\ListSort;
use App\Support\Permissions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class LeadController extends Controller
{
    public function __construct(
        private readonly LeadAvailabilityService $availabilityService,
        private readonly BuyerPurchaseService $purchaseService,
        private readonly PaymentService $paymentService,
    ) {}

    public function index(Request $request): Response|JsonResponse
    {
        abort_unless($request->user()?->can(Permissions::BUY_LEADS), 403);

        $payload = $this->indexPayload($request);

        if ($request->wantsJson() && ! $request->header('X-Inertia')) {
            return response()->json($payload);
        }

        return Inertia::render('Buyer/Leads/Index', $payload);
    }

    /**
     * @return array<string, mixed>
     */
    private function indexPayload(Request $request): array
    {
        $filters = [
            'scheme_id' => $request->input('scheme_id'),
            'zone_id' => $request->input('zone_id'),
            'min_size' => $request->input('min_size'),
            'max_size' => $request->input('max_size'),
            'min_distance' => $request->input('min_distance'),
            'max_distance' => $request->input('max_distance'),
            'min_price' => $request->input('min_price'),
            'max_price' => $request->input('max_price'),
            'search' => $request->input('search'),
        ];

        $query = $this->availabilityService
            ->findAvailableForBuyer($request->user(), $filters);

        // Marketplace-safe columns only — never customer name or other PII.
        $sortState = ListSort::apply(
            $query,
            $request,
            [
                'reference' => 'leads.lead_reference',
                'scheme' => function (Builder $q, string $direction): void {
                    $q->orderBy(
                        Scheme::query()
                            ->select('name')
                            ->whereColumn('schemes.id', 'leads.scheme_id')
                            ->limit(1),
                        $direction,
                    );
                },
                'zone' => function (Builder $q, string $direction): void {
                    $q->orderBy(
                        Zone::query()
                            ->select('code')
                            ->whereColumn('zones.id', 'leads.zone_id')
                            ->limit(1),
                        $direction,
                    );
                },
                'size_m2' => 'leads.size_m2',
                'distance_km' => 'leads.distance_km',
                'price' => 'leads.selling_price',
                'selling_price' => 'leads.selling_price',
                'date' => 'leads.created_at',
            ],
            'date',
            'desc',
        );

        $query->orderBy('leads.id', $sortState['direction'] === 'asc' ? 'asc' : 'desc');

        $perPage = ListPagination::perPage($request);
        $pricing = new LeadPricingService;
        $pricing->warmRulesCache();

        $leads = $query
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn ($lead) => BuyerLeadPresenter::presentForMarketplace(
                $lead,
                $request->user(),
                $pricing,
            ));

        $cardConfigured = app(PaymentProviderManager::class)->cardConfigured();

        return [
            'leads' => $leads,
            'filters' => [
                ...$filters,
                'sort' => $sortState['sort'],
                'direction' => $sortState['direction'],
                'per_page' => $perPage,
            ],
            'filterOptions' => [
                'schemes' => Scheme::query()->where('active', true)->orderBy('name')->get(['id', 'name', 'slug']),
                'zones' => Zone::query()->orderBy('code')->get(['id', 'code', 'name', 'scheme_id']),
            ],
            'card_configured' => $cardConfigured,
            'mollie_configured' => $cardConfigured,
        ];
    }

    public function storePurchase(StoreLeadPurchaseRequest $request): SymfonyResponse
    {
        $user = $request->user();
        $method = PaymentMethod::from($request->string('payment_method')->toString());

        $purchase = $this->purchaseService->createLeadPurchase(
            $user,
            $request->input('lead_ids', []),
            $method,
        );

        return $this->initiateCheckoutOrRedirect(
            $user,
            $purchase,
            $method,
            __('rml.buyer.leads.purchase_created'),
        );
    }

    private function initiateCheckoutOrRedirect(
        User $user,
        Purchase $purchase,
        PaymentMethod $method,
        string $successMessage,
    ): SymfonyResponse {
        $payment = $purchase->payment;
        if (! $payment) {
            return redirect()
                ->route('buyer.purchases.show', $purchase)
                ->with('success', $successMessage);
        }

        try {
            $result = $this->paymentService->initiate($payment, $user);
        } catch (ValidationException $e) {
            if ($method === PaymentMethod::Card) {
                $this->releaseCardPurchaseOnFailure($user, $purchase);
            }

            throw $e;
        }

        if (! empty($result['checkout_url'])) {
            // Critical: Inertia XHR cannot follow redirect()->away(); use location().
            return CheckoutRedirect::to($result['checkout_url']);
        }

        if (! empty($result['error'])) {
            if ($method === PaymentMethod::Card) {
                $this->releaseCardPurchaseOnFailure($user, $purchase);
            }

            throw ValidationException::withMessages([
                'payment_method' => $result['error'],
            ]);
        }

        return redirect()
            ->route('buyer.purchases.show', $purchase)
            ->with('success', $successMessage);
    }

    private function releaseCardPurchaseOnFailure(User $user, Purchase $purchase): void
    {
        $purchase = $purchase->fresh(['payment']) ?? $purchase;

        if ($purchase->status !== PurchaseStatus::Pending) {
            return;
        }

        try {
            $this->purchaseService->cancelPendingPurchase($user, $purchase);
        } catch (\Throwable) {
            // Best-effort unlock; original payment error is returned to the buyer.
        }
    }
}
