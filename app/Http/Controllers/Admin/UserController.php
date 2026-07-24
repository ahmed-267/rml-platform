<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ApprovalStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RejectRegistrationRequest;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Models\Lead;
use App\Models\Purchase;
use App\Models\User;
use App\Services\ApprovalService;
use App\Support\AccountActivity;
use App\Support\AccountListSorter;
use App\Support\ListPagination;
use App\Support\Permissions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function __construct(
        private readonly ApprovalService $approvalService,
    ) {}

    public function index(Request $request): Response
    {
        abort_unless(
            $request->user()?->can(Permissions::MANAGE_USERS)
                || $request->user()?->hasRole(UserRole::SuperAdmin->value),
            403,
        );

        $query = User::query()->with(['roles', 'sellerProfile.company', 'buyerProfile.company']);

        if ($request->filled('role')) {
            $query->whereHas('roles', fn ($q) => $q->where('name', $request->string('role')->toString()));
        }

        if ($request->filled('approval_status')) {
            $query->where('approval_status', $request->string('approval_status')->toString());
        }

        if ($request->filled('search')) {
            $search = '%'.$request->string('search')->toString().'%';
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', $search)
                    ->orWhere('email', 'like', $search)
                    ->orWhereHas('sellerProfile.company', fn ($cq) => $cq->where('name', 'like', $search))
                    ->orWhereHas('buyerProfile.company', fn ($cq) => $cq->where('name', 'like', $search));
            });
        }

        $sortState = AccountListSorter::apply(
            $query,
            $request,
            ['name', 'company', 'email', 'role', 'status', 'date'],
            'users',
        );

        $perPage = ListPagination::perPage($request);

        $users = $query
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (User $user) => $this->transformListRow($user));

        return Inertia::render('Admin/Users/Index', [
            'users' => $users,
            'filters' => [
                'role' => $request->input('role'),
                'approval_status' => $request->input('approval_status'),
                'search' => $request->input('search'),
                'sort' => $sortState['sort'],
                'direction' => $sortState['direction'],
                'per_page' => $perPage,
            ],
            'filterOptions' => [
                'roles' => [
                    UserRole::SuperAdmin->value,
                    UserRole::AdminStaff->value,
                    UserRole::InternalAuditor->value,
                    UserRole::SellerCompanyAdmin->value,
                    UserRole::SellerStaff->value,
                    UserRole::IndividualSellerAgent->value,
                    UserRole::BuyerAdmin->value,
                ],
                'assignable_roles' => $this->assignableRoles($request->user()),
                'creatable_roles' => $this->creatableRoles(),
                'approval_statuses' => ApprovalStatus::values(),
            ],
        ]);
    }

    public function show(Request $request, User $user): Response
    {
        abort_unless(
            $request->user()?->can(Permissions::MANAGE_USERS)
                || $request->user()?->hasRole(UserRole::SuperAdmin->value),
            403,
        );

        $user->load(['roles', 'sellerProfile.company', 'buyerProfile.company']);

        return Inertia::render('Admin/Users/Show', [
            'user' => $this->transformDetail($user),
            'assignable_roles' => $this->assignableRoles($request->user()),
            'activity' => AccountActivity::recentForUser($user),
            'related_summary' => $this->relatedSummary($user),
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $role = $request->string('role')->toString();

        abort_unless(
            in_array($role, $this->creatableRoles(), true),
            403,
            __('rml.admin.users.super_admin_create_forbidden'),
        );

        $user = User::query()->create([
            'name' => $request->string('name')->toString(),
            'email' => $request->string('email')->toString(),
            'password' => Hash::make($request->string('password')->toString()),
            'phone' => $request->input('phone'),
            'locale' => $request->input('locale', 'en'),
            'approval_status' => ApprovalStatus::Approved,
            'approved_at' => now(),
            'approved_by' => $request->user()->id,
        ]);

        $user->syncRoles([$role]);

        return redirect()
            ->route('admin.users.show', $user)
            ->with('success', __('rml.admin.users.created_flash'));
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        if ($request->filled('role')) {
            $role = $request->string('role')->toString();
            $this->assertCanAssignRole($request->user(), $role);

            if ($user->hasRole(UserRole::SuperAdmin->value) && ! $request->user()?->hasRole(UserRole::SuperAdmin->value)) {
                abort(403);
            }

            $user->syncRoles([$role]);
        }

        $user->fill($request->only(['name', 'email', 'phone', 'locale']));

        if ($request->filled('password')) {
            $user->password = Hash::make($request->string('password')->toString());
        }

        $user->save();

        return back()->with('success', __('rml.admin.users.updated_flash'));
    }

    public function approve(Request $request, User $user): RedirectResponse
    {
        $this->authorizeUserAction($request, $user);
        $this->approvalService->approve($user, $request->user());

        return back()->with('success', __('rml.admin.users.approved_flash'));
    }

    public function reject(RejectRegistrationRequest $request, User $user): RedirectResponse
    {
        $this->authorizeUserAction($request, $user);
        $this->approvalService->reject($user, $request->user(), $request->string('reason')->toString());

        return back()->with('success', __('rml.admin.users.rejected_flash'));
    }

    public function suspend(Request $request, User $user): RedirectResponse
    {
        $this->authorizeUserAction($request, $user);
        $this->approvalService->suspend($user, $request->user(), $request->input('reason'));

        return back()->with('success', __('rml.admin.users.suspended_flash'));
    }

    public function reinstate(Request $request, User $user): RedirectResponse
    {
        $this->authorizeUserAction($request, $user);
        $this->approvalService->reinstate($user, $request->user());

        return back()->with('success', __('rml.admin.users.reinstated_flash'));
    }

    private function authorizeUserAction(Request $request, User $user): void
    {
        abort_unless(
            $request->user()?->can(Permissions::SUSPEND_USERS)
                || $request->user()?->can(Permissions::MANAGE_USERS)
                || $request->user()?->hasRole(UserRole::SuperAdmin->value),
            403,
        );

        if ($user->hasRole(UserRole::SuperAdmin->value) && ! $request->user()?->hasRole(UserRole::SuperAdmin->value)) {
            abort(403);
        }
    }

    /**
     * @return list<string>
     */
    private function assignableRoles(?User $actor): array
    {
        $roles = [
            UserRole::AdminStaff->value,
            UserRole::InternalAuditor->value,
        ];

        if ($actor?->hasRole(UserRole::SuperAdmin->value)) {
            $roles[] = UserRole::SuperAdmin->value;
        }

        return $roles;
    }

    /**
     * Roles available when creating a user from /admin/users.
     * Super Admin cannot be created from this form.
     *
     * @return list<string>
     */
    private function creatableRoles(): array
    {
        return [
            UserRole::AdminStaff->value,
            UserRole::InternalAuditor->value,
            UserRole::SellerCompanyAdmin->value,
            UserRole::SellerStaff->value,
            UserRole::IndividualSellerAgent->value,
            UserRole::BuyerAdmin->value,
        ];
    }

    private function assertCanAssignRole(?User $actor, string $role): void
    {
        if ($role === UserRole::SuperAdmin->value && ! $actor?->hasRole(UserRole::SuperAdmin->value)) {
            abort(403, __('rml.admin.users.super_admin_forbidden'));
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function transformListRow(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'company_name' => $this->organisationName($user),
            'role' => $user->primaryRole()?->value,
            'role_label' => $user->primaryRole()?->label(),
            'approval_status' => $user->approval_status?->value,
            'created_at' => $user->created_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function transformDetail(User $user): array
    {
        $user->loadMissing(['sellerProfile.company', 'buyerProfile.company']);

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'locale' => $user->locale,
            'company_name' => $this->organisationName($user),
            'role' => $user->primaryRole()?->value,
            'role_label' => $user->primaryRole()?->label(),
            'roles' => $user->getRoleNames()->values()->all(),
            'portal' => $user->primaryRole()?->portal(),
            'approval_status' => $user->approval_status?->value,
            'approved_at' => $user->approved_at?->toIso8601String(),
            'created_at' => $user->created_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, int|float>
     */
    private function relatedSummary(User $user): array
    {
        if ($user->hasAnyRole([
            UserRole::SellerCompanyAdmin->value,
            UserRole::SellerStaff->value,
            UserRole::IndividualSellerAgent->value,
        ])) {
            return [
                'leads_submitted' => Lead::query()->where('submitted_by_user_id', $user->id)->count(),
                'leads_sold' => Lead::query()
                    ->where('submitted_by_user_id', $user->id)
                    ->where('status', 'sold')
                    ->count(),
            ];
        }

        if ($user->hasRole(UserRole::BuyerAdmin->value)) {
            $companyId = $user->buyerProfile?->company_id;

            return [
                'purchases' => $companyId
                    ? Purchase::query()->where('buyer_company_id', $companyId)->count()
                    : 0,
            ];
        }

        return [
            'activity_count' => count(AccountActivity::recentForUser($user, 20)),
        ];
    }

    private function organisationName(User $user): string
    {
        $company = $user->sellerProfile?->company?->name
            ?? $user->buyerProfile?->company?->name;

        if ($company) {
            return $company;
        }

        if ($user->hasAnyRole([
            UserRole::SuperAdmin->value,
            UserRole::AdminStaff->value,
            UserRole::InternalAuditor->value,
        ])) {
            return __('rml.admin.users.rml_internal');
        }

        return __('rml.admin.common.not_available');
    }
}
