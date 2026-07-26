<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ApprovalStatus;
use App\Enums\PaymentStatus;
use App\Enums\PurchaseStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RejectRegistrationRequest;
use App\Http\Requests\Admin\UpdateBuyerRequest;
use App\Models\Lead;
use App\Models\Payment;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\User;
use App\Services\ApprovalService;
use App\Support\AccountActivity;
use App\Support\AccountListSorter;
use App\Support\ListPagination;
use App\Support\Permissions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BuyerController extends Controller
{
    public function __construct(
        private readonly ApprovalService $approvalService,
    ) {}

    public function index(Request $request): Response
    {
        abort_unless(
            $request->user()?->can(Permissions::MANAGE_BUYERS)
                || $request->user()?->hasRole(UserRole::SuperAdmin->value),
            403,
        );

        return Inertia::render('Admin/Buyers/Index', $this->indexProps($request));
    }

    /**
     * @return array<string, mixed>
     */
    public function indexProps(Request $request): array
    {
        $query = User::query()
            ->with(['buyerProfile.company', 'roles'])
            ->whereHas('roles', fn ($q) => $q->where('name', UserRole::BuyerAdmin->value));

        if ($request->filled('approval_status')) {
            $query->where('approval_status', $request->string('approval_status')->toString());
        }

        if ($request->filled('search')) {
            $search = '%'.$request->string('search')->toString().'%';
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', $search)
                    ->orWhere('email', 'ilike', $search)
                    ->orWhereHas('buyerProfile.company', fn ($cq) => $cq->where('name', 'ilike', $search));
            });
        }

        $query->addSelect([
            'purchases_count' => Purchase::query()
                ->selectRaw('count(*)')
                ->whereIn('buyer_company_id', function ($sub) {
                    $sub->select('company_id')
                        ->from('buyer_profiles')
                        ->whereColumn('buyer_profiles.user_id', 'users.id');
                }),
        ]);

        $sortState = AccountListSorter::apply(
            $query,
            $request,
            ['name', 'company', 'email', 'status', 'purchases', 'date'],
            'buyers',
        );

        $perPage = ListPagination::perPage($request);

        $buyers = $query
            ->paginate($perPage)
            ->withQueryString()
            ->through(function (User $user) {
                $companyId = $user->buyerProfile?->company_id;
                $leadsBought = $companyId
                    ? Lead::query()->where('status', 'sold')->whereHas(
                        'purchaseItems.purchase',
                        fn ($q) => $q->where('buyer_company_id', $companyId),
                    )->count()
                    : 0;

                return [
                    ...$this->transformListRow($user),
                    'purchases_count' => (int) ($user->purchases_count ?? 0),
                    'leads_bought_count' => $leadsBought,
                ];
            });

        return [
            'buyers' => $buyers,
            'filters' => [
                'approval_status' => $request->input('approval_status'),
                'search' => $request->input('search'),
                'sort' => $sortState['sort'],
                'direction' => $sortState['direction'],
                'per_page' => $perPage,
            ],
            'filterOptions' => [
                'approval_statuses' => ApprovalStatus::values(),
            ],
        ];
    }

    public function show(Request $request, User $user): Response
    {
        $this->assertBuyer($user);
        abort_unless(
            $request->user()?->can(Permissions::MANAGE_BUYERS)
                || $request->user()?->hasRole(UserRole::SuperAdmin->value),
            403,
        );

        $user->load(['buyerProfile.company', 'roles']);

        $companyId = $user->buyerProfile?->company_id;

        $paidPurchases = $companyId
            ? Purchase::query()
                ->where('buyer_company_id', $companyId)
                ->where('status', PurchaseStatus::Paid->value)
                ->count()
            : 0;

        $pendingPayments = $companyId
            ? Payment::query()
                ->where('payer_company_id', $companyId)
                ->where('status', PaymentStatus::Pending->value)
                ->count()
            : 0;

        $releasedLeads = $companyId
            ? PurchaseItem::query()
                ->whereHas('purchase', function ($query) use ($companyId) {
                    $query->where('buyer_company_id', $companyId)
                        ->where('status', PurchaseStatus::Paid->value)
                        ->where(function ($paidQuery) {
                            $paidQuery
                                ->whereNull('payment_id')
                                ->orWhereHas(
                                    'payment',
                                    fn ($paymentQuery) => $paymentQuery->where(
                                        'status',
                                        PaymentStatus::Paid->value,
                                    ),
                                );
                        });
                })
                ->count()
            : 0;

        $pendingRelease = $companyId
            ? PurchaseItem::query()
                ->whereHas('purchase', function ($query) use ($companyId) {
                    $query->where('buyer_company_id', $companyId)
                        ->where(function ($accessQuery) {
                            $accessQuery
                                ->where('status', PurchaseStatus::Pending->value)
                                ->orWhere(function ($waitingQuery) {
                                    $waitingQuery
                                        ->where('status', PurchaseStatus::Paid->value)
                                        ->whereHas(
                                            'payment',
                                            fn ($paymentQuery) => $paymentQuery->where(
                                                'status',
                                                '!=',
                                                PaymentStatus::Paid->value,
                                            ),
                                        );
                                });
                        });
                })
                ->count()
            : 0;

        $purchaseSummary = [
            'purchases' => $companyId
                ? Purchase::query()->where('buyer_company_id', $companyId)->count()
                : 0,
            'paid_purchases' => $paidPurchases,
            'pending_payments' => $pendingPayments,
            'total_spend' => $companyId
                ? (float) Purchase::query()
                    ->where('buyer_company_id', $companyId)
                    ->where('status', PurchaseStatus::Paid->value)
                    ->sum('total_amount')
                : 0.0,
        ];

        $leadAccessSummary = [
            'paid_purchases' => $paidPurchases,
            'pending_release' => $pendingRelease,
            'released_leads' => $releasedLeads,
            'pending_payments' => $pendingPayments,
        ];

        $purchases = $companyId
            ? Purchase::query()
                ->where('buyer_company_id', $companyId)
                ->latest()
                ->limit(5)
                ->get()
                ->map(fn (Purchase $p) => [
                    'id' => $p->id,
                    'purchase_reference' => $p->purchase_reference,
                    'status' => $p->status?->value,
                    'total_amount' => $p->total_amount !== null ? (float) $p->total_amount : null,
                    'purchased_at' => $p->purchased_at?->toIso8601String(),
                ])
            : collect();

        $payments = $companyId
            ? Payment::query()
                ->where('payer_company_id', $companyId)
                ->latest()
                ->limit(5)
                ->get()
                ->map(fn (Payment $p) => [
                    'id' => $p->id,
                    'payment_reference' => $p->payment_reference,
                    'status' => $p->status?->value,
                    'amount' => $p->amount !== null ? (float) $p->amount : null,
                    'paid_at' => $p->paid_at?->toIso8601String(),
                ])
            : collect();

        return Inertia::render('Admin/Buyers/Show', [
            'buyer' => $this->transformDetail($user),
            'purchase_summary' => $purchaseSummary,
            'purchases' => $purchases->values()->all(),
            'payments' => $payments->values()->all(),
            'lead_access_summary' => $leadAccessSummary,
            'activity' => AccountActivity::recentForUser($user),
        ]);
    }

    public function update(UpdateBuyerRequest $request, User $user): RedirectResponse
    {
        $this->assertBuyer($user);

        $user->fill($request->only(['name', 'email', 'phone', 'locale']));
        $user->save();

        if ($user->buyerProfile) {
            $user->buyerProfile->fill($request->only([
                'services_offered',
                'preferred_zones',
                'max_distance_km',
                'billing_status',
            ]));
            $user->buyerProfile->save();
        }

        $company = $user->buyerProfile?->company;
        if ($company) {
            $company->fill([
                'name' => $request->input('company_name', $company->name),
                'email' => $request->input('company_email', $company->email),
                'phone' => $request->input('company_phone', $company->phone),
                'address' => $request->input('company_address', $company->address),
                'city' => $request->input('company_city', $company->city),
                'postcode' => $request->input('company_postcode', $company->postcode),
                'country' => $request->input('company_country', $company->country),
                'notes' => $request->input('company_notes', $company->notes),
            ]);
            $company->save();
        }

        return back()->with('success', __('rml.admin.buyers.updated_flash'));
    }

    public function approve(Request $request, User $user): RedirectResponse
    {
        $this->authorizeBuyerAction($request, $user);
        $this->approvalService->approve($user, $request->user());

        return back()->with('success', __('rml.admin.buyers.approved_flash'));
    }

    public function reject(RejectRegistrationRequest $request, User $user): RedirectResponse
    {
        $this->authorizeBuyerAction($request, $user);
        $this->approvalService->reject($user, $request->user(), $request->string('reason')->toString());

        return back()->with('success', __('rml.admin.buyers.rejected_flash'));
    }

    public function suspend(Request $request, User $user): RedirectResponse
    {
        $this->authorizeBuyerAction($request, $user);
        $this->approvalService->suspend($user, $request->user(), $request->input('reason'));

        return back()->with('success', __('rml.admin.buyers.suspended_flash'));
    }

    public function reinstate(Request $request, User $user): RedirectResponse
    {
        $this->authorizeBuyerAction($request, $user);
        $this->approvalService->reinstate($user, $request->user());

        return back()->with('success', __('rml.admin.buyers.reinstated_flash'));
    }

    public function deactivate(Request $request, User $user): RedirectResponse
    {
        return $this->suspend($request, $user);
    }

    private function assertBuyer(User $user): void
    {
        abort_unless($user->hasRole(UserRole::BuyerAdmin->value), 404);
    }

    private function authorizeBuyerAction(Request $request, User $user): void
    {
        $this->assertBuyer($user);
        abort_unless(
            $request->user()?->can(Permissions::APPROVE_BUYERS)
                || $request->user()?->can(Permissions::MANAGE_BUYERS)
                || $request->user()?->hasRole(UserRole::SuperAdmin->value),
            403,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function transformListRow(User $user): array
    {
        $profile = $user->buyerProfile;

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'company_name' => $profile?->company?->name,
            'approval_status' => $user->approval_status?->value,
            'created_at' => $user->created_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function transformDetail(User $user): array
    {
        $profile = $user->buyerProfile;
        $company = $profile?->company;

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'locale' => $user->locale,
            'approval_status' => $user->approval_status?->value,
            'services_offered' => $profile?->services_offered ?? [],
            'preferred_zones' => $profile?->preferred_zones ?? [],
            'max_distance_km' => $profile?->max_distance_km,
            'billing_status' => $profile?->billing_status,
            'company' => $company ? [
                'id' => $company->id,
                'name' => $company->name,
                'email' => $company->email,
                'phone' => $company->phone,
                'address' => $company->address,
                'city' => $company->city,
                'postcode' => $company->postcode,
                'country' => $company->country,
                'approval_status' => $company->approval_status?->value,
                'notes' => $company->notes,
            ] : null,
            'approved_at' => $user->approved_at?->toIso8601String(),
            'created_at' => $user->created_at?->toIso8601String(),
        ];
    }
}
