<?php

namespace App\Http\Controllers\Buyer;

use App\Enums\PackageStatus;
use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Http\Requests\Buyer\PreviewPackageRequest;
use App\Http\Requests\Buyer\StorePackagePurchaseRequest;
use App\Models\LeadPackage;
use App\Models\Scheme;
use App\Models\User;
use App\Services\Buyer\BuyerPurchaseService;
use App\Services\Buyer\LeadAvailabilityService;
use App\Services\Buyer\PackageBuilderService;
use App\Services\PackagePricingService;
use App\Services\Payments\PaymentService;
use App\Support\BuyerLeadPresenter;
use App\Support\CheckoutRedirect;
use App\Support\Permissions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class PackageController extends Controller
{
    public function __construct(
        private readonly PackageBuilderService $packageBuilderService,
        private readonly BuyerPurchaseService $purchaseService,
        private readonly PackagePricingService $packagePricingService,
        private readonly LeadAvailabilityService $availabilityService,
        private readonly PaymentService $paymentService,
    ) {}

    public function index(Request $request): Response|JsonResponse
    {
        abort_unless($request->user()?->can(Permissions::BUY_LEADS), 403);

        $payload = $this->pageProps($request->user());

        if ($request->wantsJson() && ! $request->header('X-Inertia')) {
            return response()->json($payload);
        }

        return Inertia::render('Buyer/Packages/Index', $payload);
    }

    public function preview(PreviewPackageRequest $request): Response
    {
        return Inertia::render('Buyer/Packages/Index', array_merge(
            $this->pageProps($request->user()),
            [
                'preview' => $this->packageBuilderService->preview($request->user(), $request->validated()),
                'builder_filters' => $request->validated(),
            ],
        ));
    }

    public function storePurchase(StorePackagePurchaseRequest $request): SymfonyResponse
    {
        $user = $request->user();
        $method = PaymentMethod::from($request->string('payment_method')->toString());
        $type = $request->string('type')->toString();

        $purchase = match ($type) {
            'prebuilt' => $this->purchaseService->createPackagePurchase(
                $user,
                LeadPackage::query()->findOrFail($request->integer('package_id')),
                $method,
            ),
            'mixed_zone' => $this->purchaseService->createMixedZonePurchase($user, $method),
            default => $this->purchaseService->createCustomPackagePurchase(
                $user,
                $request->only([
                    'lead_count',
                    'scheme_id',
                    'zone_codes',
                    'min_size',
                    'max_size',
                    'min_distance',
                    'max_distance',
                ]),
                $method,
            ),
        };

        $payment = $purchase->payment;
        if ($payment) {
            try {
                $result = $this->paymentService->initiate($payment, $user);
            } catch (ValidationException $e) {
                if ($method === PaymentMethod::Card) {
                    try {
                        $this->purchaseService->cancelPendingPurchase($user, $purchase);
                    } catch (\Throwable) {
                    }
                }

                throw $e;
            }

            if (! empty($result['checkout_url'])) {
                return CheckoutRedirect::to($result['checkout_url']);
            }

            if (! empty($result['error'])) {
                if ($method === PaymentMethod::Card) {
                    try {
                        $this->purchaseService->cancelPendingPurchase($user, $purchase);
                    } catch (\Throwable) {
                    }
                }

                throw ValidationException::withMessages([
                    'payment_method' => $result['error'],
                ]);
            }
        }

        return redirect()
            ->route('buyer.purchases.show', $purchase)
            ->with('success', __('rml.buyer.packages.purchase_created'));
    }

    /**
     * @return array<string, mixed>
     */
    private function pageProps(User $user): array
    {
        $companyId = $user->buyerProfile?->company_id;

        $packages = LeadPackage::query()
            ->where('status', PackageStatus::Available)
            ->where(function ($q) use ($companyId) {
                $q->whereNull('buyer_company_id');
                if ($companyId) {
                    $q->orWhere('buyer_company_id', $companyId);
                }
            })
            ->with(['scheme:id,name,slug', 'leads.zone', 'leads.scheme'])
            ->latest()
            ->limit(50)
            ->get();

        $availableIds = $this->availabilityService->availableIdSet(
            $packages->flatMap(fn (LeadPackage $package) => $package->leads->pluck('id'))
        );

        $prebuilt = $packages->map(function (LeadPackage $package) use ($user, $availableIds) {
            $pricing = $this->packagePricingService->calculate($package);
            // Assigned packages reserve leads — still show summary even when reserved to this buyer.
            $leadsForDisplay = $package->buyer_company_id
                ? $package->leads
                : $package->leads->filter(fn ($lead) => isset($availableIds[(int) $lead->id]));

            return [
                'id' => $package->id,
                'package_reference' => $package->package_reference,
                'name' => $package->name,
                'package_type' => $package->package_type?->value,
                'scheme' => $package->scheme?->name,
                'zone_mix' => $package->zone_mix,
                'lead_count' => $leadsForDisplay->count(),
                'total_size_m2' => round((float) $leadsForDisplay->sum('size_m2'), 2),
                'distance_range_min' => $package->distance_range_min !== null
                    ? (float) $package->distance_range_min
                    : null,
                'distance_range_max' => $package->distance_range_max !== null
                    ? (float) $package->distance_range_max
                    : null,
                'avg_size_m2' => $leadsForDisplay->count() > 0
                    ? round((float) $leadsForDisplay->avg('size_m2'), 2)
                    : null,
                'avg_price_per_m2' => $pricing['avg_price_per_m2'],
                'estimated_total' => $pricing['estimated_total'],
                'buyable' => $leadsForDisplay->count() > 0
                    && $leadsForDisplay->count() === $package->leads->count(),
                'assigned' => $package->buyer_company_id !== null,
                // Marketplace-safe only — no customer PII / coordinates.
                'leads' => BuyerLeadPresenter::marketplaceCollection($leadsForDisplay, $user),
            ];
        });

        return [
            'prebuilt' => $prebuilt,
            'mixed_zone' => $this->packageBuilderService->mixedZoneDefault($user),
            'schemes' => Scheme::query()->where('active', true)->orderBy('name')->get(['id', 'name', 'slug']),
            'zone_options' => ['D1', 'D2', 'E1', 'E2'],
        ];
    }
}
