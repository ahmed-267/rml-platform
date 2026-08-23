<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PackageStatus;
use App\Enums\PaymentMethod;
use App\Enums\PurchaseStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreAdminSaleRequest;
use App\Models\Company;
use App\Models\Lead;
use App\Models\LeadPackage;
use App\Models\Purchase;
use App\Models\User;
use App\Services\Admin\AdminBuyerPurchaseService;
use App\Services\Buyer\BuyerPurchaseService;
use App\Services\Buyer\LeadAvailabilityService;
use App\Services\Payments\PaymentProviderManager;
use App\Services\Payments\PaymentService;
use App\Support\CheckoutRedirect;
use App\Support\Permissions;
use App\Support\ZoneDisplay;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Admin "Buy as buyer" — same purchase/payment pipeline as the Buyer Portal.
 */
class AdminSaleController extends Controller
{
    public function __construct(
        private readonly AdminBuyerPurchaseService $buyerPurchaseService,
        private readonly BuyerPurchaseService $purchaseService,
        private readonly PaymentService $paymentService,
        private readonly PaymentProviderManager $paymentProviders,
        private readonly LeadAvailabilityService $availability = new LeadAvailabilityService,
    ) {}

    public function create(Request $request): Response
    {
        $this->authorizeBuy($request);

        $type = $request->string('type')->toString();
        if (! in_array($type, ['lead', 'package'], true)) {
            $type = $request->filled('package_id') ? 'package' : 'lead';
        }

        $buyers = $this->buyerPurchaseService->approvedBuyerOptions();

        $eligibleLeads = Lead::query()
            ->whereIn('status', LeadAvailabilityService::marketplaceStatusValues())
            ->whereDoesntHave('packages', function ($q) {
                $q->whereIn('lead_packages.status', [
                    PackageStatus::Draft->value,
                    PackageStatus::Available->value,
                    PackageStatus::Locked->value,
                    PackageStatus::Sold->value,
                ]);
            })
            ->with([
                'scheme:id,name',
                'zone:id,code,name',
                'sellerCompany:id,name',
                'submittedBy:id,name',
                'evidenceFiles:id,lead_id,file_type,original_name',
                'audits' => fn ($q) => $q->latest('id')->limit(1),
                'survey:id,lead_id,status,version,last_saved_at',
                'latestCatastroSnapshot',
            ])
            ->latest('id')
            ->limit(200)
            ->get()
            ->filter(fn (Lead $lead) => $this->availability->isAvailable($lead))
            ->map(fn (Lead $lead) => $this->presentEligibleLead($lead))
            ->values()
            ->all();

        $packages = LeadPackage::query()
            ->whereIn('status', [PackageStatus::Available->value, PackageStatus::Draft->value])
            ->with([
                'leads' => fn ($q) => $q->with([
                    'scheme:id,name',
                    'zone:id,code,name',
                    'sellerCompany:id,name',
                ]),
            ])
            ->withCount('leads')
            ->latest('id')
            ->limit(100)
            ->get()
            ->map(fn (LeadPackage $package) => $this->presentEligiblePackage($package))
            ->values()
            ->all();

        $preselectedLeadId = $request->integer('lead_id') ?: null;
        $preselectedPackageId = $request->integer('package_id') ?: null;

        $leadStillEligible = $preselectedLeadId
            ? collect($eligibleLeads)->contains(fn (array $lead) => (int) $lead['id'] === $preselectedLeadId)
            : true;
        $packageStillEligible = $preselectedPackageId
            ? collect($packages)->contains(fn (array $package) => (int) $package['id'] === $preselectedPackageId)
            : true;

        return Inertia::render('Admin/Sales/Create', [
            'type' => $type,
            'preselected' => [
                'lead_id' => $leadStillEligible ? $preselectedLeadId : null,
                'package_id' => $packageStillEligible ? $preselectedPackageId : null,
            ],
            'preselect_invalid' => ($preselectedLeadId && ! $leadStillEligible)
                || ($preselectedPackageId && ! $packageStillEligible),
            'back_href' => $this->resolveBackHref($request),
            'buyers' => $buyers,
            'eligible_leads' => $eligibleLeads,
            'packages' => $packages,
            'payment_methods' => [
                PaymentMethod::ManualBankTransfer->value,
                PaymentMethod::Card->value,
            ],
            'card_configured' => $this->paymentProviders->cardConfigured(),
        ]);
    }

    private function resolveBackHref(Request $request): string
    {
        $returnTo = $request->string('return_to')->toString();
        $leadId = $request->integer('lead_id') ?: null;
        $packageId = $request->integer('package_id') ?: null;

        return match ($returnTo) {
            'lead' => $leadId
                ? route('admin.leads-bought.show', ['lead' => $leadId])
                : route('admin.leads.index', ['tab' => 'registered']),
            'package' => $packageId
                ? route('admin.packages.show', ['package' => $packageId])
                : route('admin.leads.index', ['tab' => 'packages']),
            'packages' => route('admin.leads.index', ['tab' => 'packages']),
            'sold' => route('admin.leads.index', ['tab' => 'sold']),
            'registered' => route('admin.leads.index', ['tab' => 'registered']),
            default => route('admin.leads.index', ['tab' => 'registered']),
        };
    }

    public function store(StoreAdminSaleRequest $request): SymfonyResponse
    {
        $this->authorizeBuy($request);

        $buyerCompany = Company::query()->findOrFail($request->integer('buyer_company_id'));
        // Buyer user is resolved automatically — not part of the admin UI.
        $method = PaymentMethod::from($request->string('payment_method')->toString());

        if ($request->string('type')->toString() === 'package') {
            $package = LeadPackage::query()->findOrFail($request->integer('package_id'));
            $purchase = $this->buyerPurchaseService->buyPackage(
                $request->user(),
                $buyerCompany,
                $package,
                $method,
                null,
            );
        } else {
            $purchase = $this->buyerPurchaseService->buyLeads(
                $request->user(),
                $buyerCompany,
                $request->input('lead_ids', []),
                $method,
                null,
            );
        }

        $purchase->loadMissing(['payment', 'buyerUser']);
        $payer = $purchase->buyerUser
            ?? $this->buyerPurchaseService->resolveBuyerUser($buyerCompany, null);

        return $this->initiateCheckoutOrRedirect(
            $payer,
            $purchase,
            $method,
            __('rml.admin.sales.created_flash'),
        );
    }

    private function initiateCheckoutOrRedirect(
        User $payer,
        Purchase $purchase,
        PaymentMethod $method,
        string $successMessage,
    ): SymfonyResponse {
        $payment = $purchase->payment;
        if (! $payment) {
            return redirect()
                ->route('admin.payments.index', [
                    'tab' => 'buyer',
                    'search' => $purchase->purchase_reference,
                ])
                ->with('success', $successMessage);
        }

        try {
            $result = $this->paymentService->initiate($payment, $payer);
        } catch (ValidationException $e) {
            if ($method === PaymentMethod::Card) {
                $this->releaseCardPurchaseOnFailure($payer, $purchase);
            }

            throw $e;
        }

        if (! empty($result['checkout_url'])) {
            return CheckoutRedirect::to($result['checkout_url']);
        }

        if (! empty($result['error'])) {
            if ($method === PaymentMethod::Card) {
                $this->releaseCardPurchaseOnFailure($payer, $purchase);
            }

            throw ValidationException::withMessages([
                'payment_method' => $result['error'],
            ]);
        }

        return redirect()
            ->route('admin.payments.index', [
                'tab' => 'buyer',
                'search' => $payment->payment_reference,
            ])
            ->with('success', $successMessage);
    }

    private function releaseCardPurchaseOnFailure(User $payer, Purchase $purchase): void
    {
        $purchase = $purchase->fresh(['payment']) ?? $purchase;

        if ($purchase->status !== PurchaseStatus::Pending) {
            return;
        }

        try {
            $this->purchaseService->cancelPendingPurchase($payer, $purchase);
        } catch (\Throwable) {
            // Best-effort unlock; original payment error is returned.
        }
    }

    /**
     * Full admin-visible lead card for Buy Lead (PII OK — admin only).
     *
     * @return array<string, mixed>
     */
    private function presentEligibleLead(Lead $lead): array
    {
        $selling = $lead->selling_price !== null ? (float) $lead->selling_price : null;
        $buying = $lead->buying_price !== null ? (float) $lead->buying_price : null;
        $margin = $selling !== null && $buying !== null
            ? round($selling - $buying, 2)
            : ($lead->expected_margin !== null ? (float) $lead->expected_margin : null);

        $latestAudit = $lead->audits->first();
        $evidenceCount = $lead->evidenceFiles->count();
        $agreementCount = $lead->evidenceFiles
            ->filter(function ($file) {
                $type = $file->file_type?->value ?? (string) ($file->file_type ?? '');

                return str_contains(strtolower($type), 'agreement');
            })
            ->count();

        $address = trim(implode(', ', array_filter([
            $lead->address_line_1,
            $lead->address_line_2,
            $lead->city,
            $lead->postcode,
            $lead->country,
        ])));

        $customerName = trim(implode(' ', array_filter([
            $lead->customer_first_name,
            $lead->customer_last_name,
        ])));

        return [
            'id' => (int) $lead->id,
            'lead_reference' => $lead->lead_reference,
            'status' => $lead->status?->value,
            'scheme' => $lead->scheme?->name,
            'zone' => ZoneDisplay::code($lead->zone?->code),
            'size_m2' => $lead->size_m2 !== null ? (float) $lead->size_m2 : null,
            'seller_company' => $lead->sellerCompany?->name,
            'seller_agent' => $lead->submittedBy?->name,
            'buying_price' => $buying,
            'selling_price' => $selling,
            'expected_margin' => $margin,
            'customer_name' => $customerName !== '' ? $customerName : null,
            'customer_phone' => $lead->customer_phone,
            'customer_email' => $lead->customer_email,
            'customer_whatsapp' => $lead->customer_whatsapp,
            'address' => $address !== '' ? $address : ($lead->formatted_address ?: null),
            'city' => $lead->city,
            'postcode' => $lead->postcode,
            'country' => $lead->country,
            'survey_status' => $lead->survey?->status?->value
                ?? $lead->survey_eligibility_status
                ?? 'survey_not_started',
            'catastro_status' => $lead->latestCatastroSnapshot?->verification_status?->value
                ?? $lead->cadastral_lookup_status?->value
                ?? 'not_checked',
            'cadastral_reference' => $lead->cadastral_reference,
            'evidence_status' => [
                'photos' => $evidenceCount,
                'agreement' => $agreementCount > 0,
                'label' => $evidenceCount > 0
                    ? ($agreementCount > 0 ? 'complete' : 'partial')
                    : 'missing',
            ],
            'audit_status' => $latestAudit?->status?->value ?? $lead->status?->value,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function presentEligiblePackage(LeadPackage $package): array
    {
        /** @var Collection<int, Lead> $leads */
        $leads = $package->leads;

        $totalSize = round((float) $leads->sum(fn (Lead $lead) => (float) ($lead->size_m2 ?? 0)), 2);
        $totalSelling = round((float) $leads->sum(fn (Lead $lead) => (float) ($lead->selling_price ?? 0)), 2);
        $totalBuying = round((float) $leads->sum(fn (Lead $lead) => (float) ($lead->buying_price ?? 0)), 2);
        $margin = round($totalSelling - $totalBuying, 2);

        $schemes = $leads->map(fn (Lead $lead) => $lead->scheme?->name)->filter()->unique()->values()->all();
        $zones = $leads->map(fn (Lead $lead) => ZoneDisplay::code($lead->zone?->code))->filter()->unique()->values()->all();

        return [
            'id' => (int) $package->id,
            'package_reference' => $package->package_reference,
            'name' => $package->name,
            'status' => $package->status?->value,
            'lead_count' => (int) ($package->leads_count ?? $leads->count()),
            'estimated_total' => $package->estimated_total !== null
                ? (float) $package->estimated_total
                : $totalSelling,
            'total_size_m2' => $totalSize,
            'total_selling_price' => $totalSelling,
            'total_buying_price' => $totalBuying,
            'expected_margin' => $margin,
            'schemes' => $schemes,
            'zones' => $zones,
            'buyer_company_id' => $package->buyer_company_id
                ? (int) $package->buyer_company_id
                : null,
            'leads' => $leads->map(function (Lead $lead) {
                $selling = $lead->selling_price !== null ? (float) $lead->selling_price : null;
                $buying = $lead->buying_price !== null ? (float) $lead->buying_price : null;
                $customerName = trim(implode(' ', array_filter([
                    $lead->customer_first_name,
                    $lead->customer_last_name,
                ])));
                $address = trim(implode(', ', array_filter([
                    $lead->address_line_1,
                    $lead->city,
                    $lead->postcode,
                ])));

                return [
                    'id' => (int) $lead->id,
                    'lead_reference' => $lead->lead_reference,
                    'scheme' => $lead->scheme?->name,
                    'zone' => ZoneDisplay::code($lead->zone?->code),
                    'size_m2' => $lead->size_m2 !== null ? (float) $lead->size_m2 : null,
                    'seller_company' => $lead->sellerCompany?->name,
                    'buying_price' => $buying,
                    'selling_price' => $selling,
                    'expected_margin' => $selling !== null && $buying !== null
                        ? round($selling - $buying, 2)
                        : null,
                    'customer_name' => $customerName !== '' ? $customerName : null,
                    'address' => $address !== '' ? $address : null,
                    'city' => $lead->city,
                ];
            })->values()->all(),
        ];
    }

    private function authorizeBuy(Request $request): void
    {
        $user = $request->user();

        abort_unless(
            $user?->hasRole('super_admin')
                || $user?->can(Permissions::SELL_TO_BUYERS),
            403,
        );
    }
}
