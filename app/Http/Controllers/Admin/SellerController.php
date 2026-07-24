<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ApprovalStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RejectRegistrationRequest;
use App\Http\Requests\Admin\UpdateSellerRequest;
use App\Models\Commission;
use App\Models\Lead;
use App\Models\Payout;
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

class SellerController extends Controller
{
    public function __construct(
        private readonly ApprovalService $approvalService,
    ) {}

    public function index(Request $request): Response
    {
        abort_unless(
            $request->user()?->can(Permissions::MANAGE_SELLERS)
                || $request->user()?->hasRole(UserRole::SuperAdmin->value),
            403,
        );

        $query = User::query()
            ->with(['sellerProfile.company', 'roles'])
            ->whereHas('roles', fn ($q) => $q->whereIn('name', [
                UserRole::SellerCompanyAdmin->value,
                UserRole::SellerStaff->value,
                UserRole::IndividualSellerAgent->value,
            ]));

        if ($request->filled('approval_status')) {
            $query->where('approval_status', $request->string('approval_status')->toString());
        }

        if ($request->filled('role')) {
            $query->whereHas('roles', fn ($q) => $q->where(
                'name',
                $request->string('role')->toString(),
            ));
        }

        if ($request->filled('search')) {
            $search = '%'.$request->string('search')->toString().'%';
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', $search)
                    ->orWhere('email', 'like', $search)
                    ->orWhereHas('sellerProfile.company', fn ($cq) => $cq->where('name', 'like', $search));
            });
        }

        $sellersQuery = $query
            ->withCount([
                'submittedLeads as leads_submitted_count',
                'submittedLeads as leads_sold_count' => fn ($q) => $q->where('status', 'sold'),
            ]);

        $sortState = AccountListSorter::apply(
            $sellersQuery,
            $request,
            ['name', 'company', 'email', 'role', 'status', 'leads_submitted', 'leads_sold', 'date'],
            'sellers',
        );

        $perPage = ListPagination::perPage($request);

        $sellers = $sellersQuery
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (User $user) => $this->transformListRow($user));

        return Inertia::render('Admin/Sellers/Index', [
            'sellers' => $sellers,
            'filters' => [
                'approval_status' => $request->input('approval_status'),
                'role' => $request->input('role'),
                'search' => $request->input('search'),
                'sort' => $sortState['sort'],
                'direction' => $sortState['direction'],
                'per_page' => $perPage,
            ],
            'filterOptions' => [
                'approval_statuses' => ApprovalStatus::values(),
                'roles' => [
                    UserRole::SellerCompanyAdmin->value,
                    UserRole::SellerStaff->value,
                    UserRole::IndividualSellerAgent->value,
                ],
            ],
        ]);
    }

    public function show(Request $request, User $user): Response
    {
        $this->assertSeller($user);
        abort_unless(
            $request->user()?->can(Permissions::MANAGE_SELLERS)
                || $request->user()?->hasRole(UserRole::SuperAdmin->value),
            403,
        );

        $user->load([
            'sellerProfile.company',
            'roles',
            'submittedLeads' => fn ($q) => $q->latest()->limit(5),
        ]);

        $companyId = $user->sellerProfile?->company_id;

        $staff = $companyId
            ? User::query()
                ->whereHas('sellerProfile', fn ($q) => $q->where('company_id', $companyId))
                ->where('id', '!=', $user->id)
                ->with('roles')
                ->latest()
                ->limit(5)
                ->get()
                ->map(fn (User $staffUser) => $this->transformStaff($staffUser))
            : collect();

        $leadStats = [
            'submitted' => Lead::query()->where('submitted_by_user_id', $user->id)->count(),
            'sold' => Lead::query()->where('submitted_by_user_id', $user->id)->where('status', 'sold')->count(),
            'listed' => Lead::query()->where('submitted_by_user_id', $user->id)->where('status', 'listed')->count(),
            'rejected' => Lead::query()->where('submitted_by_user_id', $user->id)->where('status', 'rejected')->count(),
        ];

        $commissions = Commission::query()
            ->where('seller_user_id', $user->id)
            ->latest()
            ->limit(5)
            ->get()
            ->map(fn (Commission $c) => [
                'id' => $c->id,
                'commission_reference' => $c->commission_reference,
                'status' => $c->status?->value,
                'commission_amount' => $c->commission_amount !== null ? (float) $c->commission_amount : null,
                'due_at' => $c->due_at?->toIso8601String(),
            ]);

        $payouts = Payout::query()
            ->where('seller_user_id', $user->id)
            ->latest()
            ->limit(5)
            ->get()
            ->map(fn (Payout $p) => [
                'id' => $p->id,
                'payout_reference' => $p->payout_reference,
                'status' => $p->status?->value,
                'amount' => $p->amount !== null ? (float) $p->amount : null,
                'due_date' => $p->due_date?->toDateString(),
            ]);

        return Inertia::render('Admin/Sellers/Show', [
            'seller' => $this->transformDetail($user),
            'staff' => $staff->values()->all(),
            'lead_stats' => $leadStats,
            'recent_leads' => $user->submittedLeads->take(5)->map(fn (Lead $lead) => [
                'id' => $lead->id,
                'lead_reference' => $lead->lead_reference,
                'status' => $lead->status?->value,
                'created_at' => $lead->created_at?->toIso8601String(),
            ])->values()->all(),
            'commissions' => $commissions,
            'payouts' => $payouts,
            'activity' => AccountActivity::recentForUser($user),
        ]);
    }

    public function update(UpdateSellerRequest $request, User $user): RedirectResponse
    {
        $this->assertSeller($user);

        $user->fill($request->only(['name', 'email', 'phone', 'locale']));
        $user->save();

        if ($user->sellerProfile) {
            $user->sellerProfile->fill($request->only([
                'commission_rate',
                'bank_account_iban',
                'bank_account_name',
                'payout_method',
            ]));
            $user->sellerProfile->save();
        }

        $company = $user->sellerProfile?->company;
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

        return back()->with('success', __('rml.admin.sellers.updated_flash'));
    }

    public function approve(Request $request, User $user): RedirectResponse
    {
        $this->authorizeSellerAction($request, $user);
        $this->approvalService->approve($user, $request->user());

        return back()->with('success', __('rml.admin.sellers.approved_flash'));
    }

    public function reject(RejectRegistrationRequest $request, User $user): RedirectResponse
    {
        $this->authorizeSellerAction($request, $user);
        $this->approvalService->reject($user, $request->user(), $request->string('reason')->toString());

        return back()->with('success', __('rml.admin.sellers.rejected_flash'));
    }

    public function suspend(Request $request, User $user): RedirectResponse
    {
        $this->authorizeSellerAction($request, $user);
        $this->approvalService->suspend($user, $request->user(), $request->input('reason'));

        return back()->with('success', __('rml.admin.sellers.suspended_flash'));
    }

    public function reinstate(Request $request, User $user): RedirectResponse
    {
        $this->authorizeSellerAction($request, $user);
        $this->approvalService->reinstate($user, $request->user());

        return back()->with('success', __('rml.admin.sellers.reinstated_flash'));
    }

    public function deactivate(Request $request, User $user): RedirectResponse
    {
        return $this->suspend($request, $user);
    }

    private function assertSeller(User $user): void
    {
        abort_unless($user->hasAnyRole([
            UserRole::SellerCompanyAdmin->value,
            UserRole::SellerStaff->value,
            UserRole::IndividualSellerAgent->value,
        ]), 404);
    }

    private function authorizeSellerAction(Request $request, User $user): void
    {
        $this->assertSeller($user);
        abort_unless(
            $request->user()?->can(Permissions::APPROVE_SELLERS)
                || $request->user()?->can(Permissions::MANAGE_SELLERS)
                || $request->user()?->hasRole(UserRole::SuperAdmin->value),
            403,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function transformListRow(User $user): array
    {
        $profile = $user->sellerProfile;

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->primaryRole()?->value,
            'role_label' => $user->primaryRole()?->label(),
            'seller_type' => $profile?->seller_type?->value,
            'company_name' => $profile?->company?->name,
            'approval_status' => $user->approval_status?->value,
            'leads_submitted_count' => $user->leads_submitted_count ?? 0,
            'leads_sold_count' => $user->leads_sold_count ?? 0,
            'created_at' => $user->created_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function transformDetail(User $user): array
    {
        $profile = $user->sellerProfile;
        $company = $profile?->company;

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'locale' => $user->locale,
            'approval_status' => $user->approval_status?->value,
            'role' => $user->primaryRole()?->value,
            'role_label' => $user->primaryRole()?->label(),
            'seller_type' => $profile?->seller_type?->value,
            'commission_rate' => $profile?->commission_rate !== null ? (float) $profile->commission_rate : null,
            'bank_account_iban' => $profile?->bank_account_iban,
            'bank_account_name' => $profile?->bank_account_name,
            'payout_method' => $profile?->payout_method,
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

    /**
     * @return array<string, mixed>
     */
    private function transformStaff(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->primaryRole()?->value,
            'approval_status' => $user->approval_status?->value,
        ];
    }
}
