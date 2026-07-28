<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ApprovalStatus;
use App\Enums\AuditDecisionStatus;
use App\Enums\LeadStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AssignAuditorLeadsRequest;
use App\Http\Requests\Admin\RejectRegistrationRequest;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Models\Lead;
use App\Models\LeadAudit;
use App\Models\Purchase;
use App\Models\Scheme;
use App\Models\User;
use App\Models\Zone;
use App\Services\ApprovalService;
use App\Services\AuditLogService;
use App\Services\Auditor\AuditWorkflowService;
use App\Support\AccountActivity;
use App\Support\AccountListSorter;
use App\Support\AdminLeadPresenter;
use App\Support\ListPagination;
use App\Support\Permissions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function __construct(
        private readonly ApprovalService $approvalService,
        private readonly SellerController $sellerController,
        private readonly BuyerController $buyerController,
        private readonly AuditLogService $auditLogService,
        private readonly AuditWorkflowService $auditWorkflowService,
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $isSuperAdmin = $user?->hasRole(UserRole::SuperAdmin->value);

        abort_unless(
            $isSuperAdmin
                || $user?->can(Permissions::MANAGE_USERS)
                || $user?->can(Permissions::MANAGE_SELLERS)
                || $user?->can(Permissions::MANAGE_BUYERS)
                || $user?->can(Permissions::ACCEPT_REJECT_LEADS)
                || $user?->can(Permissions::AUDIT_LEADS),
            403,
        );

        $tab = $request->string('tab')->toString();
        if (! in_array($tab, ['users', 'sellers', 'buyers', 'auditors'], true)) {
            if ($isSuperAdmin || $user?->can(Permissions::MANAGE_USERS)) {
                $tab = 'users';
            } elseif (
                $user?->can(Permissions::ACCEPT_REJECT_LEADS)
                || $user?->can(Permissions::AUDIT_LEADS)
            ) {
                $tab = 'auditors';
            } elseif ($user?->can(Permissions::MANAGE_SELLERS)) {
                $tab = 'sellers';
            } elseif ($user?->can(Permissions::MANAGE_BUYERS)) {
                $tab = 'buyers';
            } else {
                abort(403);
            }
        }

        if ($tab === 'sellers') {
            abort_unless(
                $isSuperAdmin || $user?->can(Permissions::MANAGE_SELLERS),
                403,
            );

            return Inertia::render('Admin/Users/Index', [
                'tab' => $tab,
                ...$this->sellerController->indexProps($request),
            ]);
        }

        if ($tab === 'buyers') {
            abort_unless(
                $isSuperAdmin || $user?->can(Permissions::MANAGE_BUYERS),
                403,
            );

            return Inertia::render('Admin/Users/Index', [
                'tab' => $tab,
                ...$this->buyerController->indexProps($request),
            ]);
        }

        if ($tab === 'auditors') {
            abort_unless(
                $isSuperAdmin
                    || $user?->can(Permissions::MANAGE_USERS)
                    || $user?->can(Permissions::ACCEPT_REJECT_LEADS)
                    || $user?->can(Permissions::AUDIT_LEADS),
                403,
            );

            return Inertia::render('Admin/Users/Index', [
                'tab' => $tab,
                ...$this->auditorsIndexProps($request),
            ]);
        }

        abort_unless(
            $isSuperAdmin || $user?->can(Permissions::MANAGE_USERS),
            403,
        );

        return Inertia::render('Admin/Users/Index', [
            'tab' => 'users',
            ...$this->indexProps($request),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function indexProps(Request $request): array
    {
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
                $q->where('name', 'ilike', $search)
                    ->orWhere('email', 'ilike', $search)
                    ->orWhereHas('sellerProfile.company', fn ($cq) => $cq->where('name', 'ilike', $search))
                    ->orWhereHas('buyerProfile.company', fn ($cq) => $cq->where('name', 'ilike', $search));
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

        return [
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
        ];
    }

    public function show(Request $request, User $user): Response
    {
        abort_unless(
            $request->user()?->can(Permissions::MANAGE_USERS)
                || $request->user()?->hasRole(UserRole::SuperAdmin->value),
            403,
        );

        $user->load(['roles', 'sellerProfile.company', 'buyerProfile.company', 'approvedBy:id,name']);

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

        $status = ApprovalStatus::tryFrom((string) $request->input('approval_status'))
            ?? ApprovalStatus::Approved;
        if (! in_array($status, [ApprovalStatus::Pending, ApprovalStatus::Approved], true)) {
            $status = ApprovalStatus::Approved;
        }

        $user = User::query()->create([
            'name' => $request->string('name')->toString(),
            'email' => $request->string('email')->toString(),
            'password' => Hash::make($request->string('password')->toString()),
            'phone' => $request->input('phone'),
            'locale' => $request->input('locale', 'en'),
            'approval_status' => $status,
            'approved_at' => $status === ApprovalStatus::Approved ? now() : null,
            'approved_by' => $status === ApprovalStatus::Approved ? $request->user()->id : null,
        ]);

        $user->syncRoles([$role]);

        $redirectTab = $role === UserRole::InternalAuditor->value ? 'auditors' : 'users';

        return redirect()
            ->route('admin.users.index', ['tab' => $redirectTab])
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
        $this->approvalService->reject(
            $user,
            $request->user(),
            $request->string('reason_code')->toString(),
            $request->input('comment'),
        );

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

    public function destroy(Request $request, User $user): RedirectResponse
    {
        abort_unless(
            $request->user()?->hasRole(UserRole::SuperAdmin->value),
            403,
        );

        if ($user->id === $request->user()?->id) {
            return back()->with('error', __('rml.admin.users.delete_self_forbidden'));
        }

        if ($user->hasRole(UserRole::SuperAdmin->value)) {
            return back()->with('error', __('rml.admin.users.delete_super_admin_forbidden'));
        }

        $user->loadMissing(['buyerProfile', 'sellerProfile']);

        $hasLeads = Lead::query()->where('submitted_by_user_id', $user->id)->exists();
        $hasPurchases = Purchase::query()->where('buyer_user_id', $user->id)->exists();

        if ($hasLeads || $hasPurchases) {
            return back()->with('error', __('rml.admin.users.delete_blocked_has_data'));
        }

        $redirectTab = $user->hasRole(UserRole::InternalAuditor->value) ? 'auditors' : 'users';

        DB::transaction(function () use ($request, $user) {
            $this->auditLogService->log(
                'user.deleted',
                $user,
                [
                    'email' => $user->email,
                    'roles' => $user->getRoleNames()->values()->all(),
                ],
                null,
                $request->user(),
            );

            $user->sellerProfile?->delete();
            $user->buyerProfile?->delete();
            $user->syncRoles([]);
            $user->delete();
        });

        return redirect()
            ->route('admin.users.index', ['tab' => $redirectTab])
            ->with('success', __('rml.admin.users.deleted_flash'));
    }

    /**
     * @return array<string, mixed>
     */
    public function auditorsIndexProps(Request $request): array
    {
        $activeStatuses = [
            AuditDecisionStatus::Pending->value,
            AuditDecisionStatus::InReview->value,
            AuditDecisionStatus::NeedsMoreInformation->value,
        ];
        $completedStatuses = [
            AuditDecisionStatus::RecommendedAccept->value,
            AuditDecisionStatus::RecommendedReject->value,
            AuditDecisionStatus::Accepted->value,
            AuditDecisionStatus::Rejected->value,
        ];

        $query = User::query()
            ->role(UserRole::InternalAuditor->value)
            ->with('roles')
            ->withCount([
                'assignedAudits as assigned_audits_count' => fn ($q) => $q->whereIn('status', $activeStatuses),
                'assignedAudits as in_review_audits_count' => fn ($q) => $q->where(
                    'status',
                    AuditDecisionStatus::InReview->value,
                ),
                'assignedAudits as completed_audits_count' => fn ($q) => $q
                    ->where(function ($inner) use ($completedStatuses) {
                        $inner->whereNotNull('completed_at')
                            ->orWhereIn('status', $completedStatuses);
                    }),
            ]);

        if ($request->filled('approval_status')) {
            $query->where('approval_status', $request->string('approval_status')->toString());
        }

        if ($request->filled('search')) {
            $search = '%'.$request->string('search')->toString().'%';
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', $search)
                    ->orWhere('email', 'ilike', $search);
            });
        }

        $sortState = AccountListSorter::apply(
            $query,
            $request,
            ['name', 'email', 'status', 'date'],
            'users',
        );
        $perPage = ListPagination::perPage($request);

        $auditors = $query
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (User $auditor) => [
                'id' => $auditor->id,
                'name' => $auditor->name,
                'email' => $auditor->email,
                'phone' => $auditor->phone,
                'locale' => $auditor->locale,
                'role' => UserRole::InternalAuditor->value,
                'role_label' => UserRole::InternalAuditor->label(),
                'approval_status' => $auditor->approval_status?->value,
                'assigned_audits_count' => (int) $auditor->assigned_audits_count,
                'in_review_audits_count' => (int) $auditor->in_review_audits_count,
                'completed_audits_count' => (int) $auditor->completed_audits_count,
                'last_active_at' => $auditor->updated_at?->toIso8601String(),
                'created_at' => $auditor->created_at?->toIso8601String(),
            ]);

        $actor = $request->user();

        return [
            'auditors' => $auditors,
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
            'permissions' => [
                'can_create' => (bool) (
                    $actor?->hasRole(UserRole::SuperAdmin->value)
                    || $actor?->can(Permissions::MANAGE_USERS)
                ),
                'can_edit' => (bool) (
                    $actor?->hasRole(UserRole::SuperAdmin->value)
                    || $actor?->can(Permissions::MANAGE_USERS)
                ),
                'can_suspend' => (bool) (
                    $actor?->hasRole(UserRole::SuperAdmin->value)
                    || $actor?->can(Permissions::SUSPEND_USERS)
                    || $actor?->can(Permissions::MANAGE_USERS)
                ),
                'can_delete' => (bool) $actor?->hasRole(UserRole::SuperAdmin->value),
                'can_assign' => (bool) (
                    $actor?->hasRole(UserRole::SuperAdmin->value)
                    || $actor?->can(Permissions::ACCEPT_REJECT_LEADS)
                ),
            ],
        ];
    }

    public function eligibleAudits(Request $request, User $user): JsonResponse
    {
        $this->authorizeAssignAudits($request);
        $this->assertInternalAuditor($user);

        $eligibleStatuses = $this->assignableLeadStatuses();
        $statusFilter = $this->resolveAssignableStatusFilter($request->input('status'));

        $query = Lead::query()
            ->with([
                'scheme:id,name',
                'zone:id,code,scheme_id',
                'submittedBy:id,name',
                'sellerCompany:id,name,type',
            ])
            ->withMax('audits as latest_audit_id', 'id')
            ->whereIn('status', $statusFilter ?? $eligibleStatuses);

        if ($request->filled('scheme_id')) {
            $query->where('scheme_id', $request->integer('scheme_id'));
        }

        if ($request->filled('zone_code')) {
            $query->whereHas(
                'zone',
                fn ($q) => $q->where('code', $request->string('zone_code')->toString()),
            );
        }

        if ($request->filled('seller')) {
            $seller = '%'.$request->string('seller')->toString().'%';
            $query->where(function ($q) use ($seller) {
                $q->whereHas('sellerCompany', fn ($inner) => $inner->where('name', 'ilike', $seller))
                    ->orWhereHas('submittedBy', fn ($inner) => $inner->where('name', 'ilike', $seller));
            });
        }

        if ($request->filled('search')) {
            $search = '%'.$request->string('search')->toString().'%';
            $query->where(function ($q) use ($search) {
                $q->where('lead_reference', 'ilike', $search)
                    ->orWhereRaw('CAST(id AS TEXT) ILIKE ?', [$search]);
            });
        }

        $leads = $query
            ->latest('id')
            ->limit(100)
            ->get();

        $latestAudits = LeadAudit::query()
            ->with('auditor:id,name,email')
            ->whereIn('id', $leads->pluck('latest_audit_id')->filter()->all())
            ->get()
            ->keyBy('id');

        $rows = $leads->map(function (Lead $lead) use ($user, $latestAudits) {
            $row = AdminLeadPresenter::listRow($lead);
            $latestAudit = $lead->latest_audit_id
                ? $latestAudits->get($lead->latest_audit_id)
                : null;
            $alreadyAssigned = $latestAudit
                && (int) $latestAudit->auditor_user_id === (int) $user->id;

            $row['current_auditor'] = $latestAudit?->auditor ? [
                'id' => $latestAudit->auditor->id,
                'name' => $latestAudit->auditor->name,
                'email' => $latestAudit->auditor->email,
            ] : null;
            $row['already_assigned'] = $alreadyAssigned;

            return $row;
        })->values()->all();

        $zoneCodes = Zone::query()
            ->orderBy('code')
            ->pluck('code')
            ->unique()
            ->values()
            ->map(fn (string $code) => ['code' => $code])
            ->all();

        return response()->json([
            'leads' => $rows,
            'filterOptions' => [
                'statuses' => [
                    [
                        'value' => 'pending_review',
                        'label' => __('rml.lead_statuses.pending_review'),
                    ],
                    [
                        'value' => 'needs_information',
                        'label' => __('rml.lead_statuses.needs_information'),
                    ],
                ],
                'schemes' => Scheme::query()->orderBy('name')->get(['id', 'name'])
                    ->map(fn (Scheme $scheme) => [
                        'id' => $scheme->id,
                        'name' => $scheme->name,
                    ])
                    ->values()
                    ->all(),
                'zones' => $zoneCodes,
            ],
        ]);
    }

    public function assignAudits(AssignAuditorLeadsRequest $request, User $user): RedirectResponse
    {
        $this->assertInternalAuditor($user);

        abort_unless(
            $user->approval_status === ApprovalStatus::Approved,
            422,
            __('rml.admin.users.assign_auditor_not_approved'),
        );

        $leadIds = collect($request->validated('lead_ids'))->map(fn ($id) => (int) $id)->unique()->values();
        $eligibleStatuses = $this->assignableLeadStatuses();

        $leads = Lead::query()
            ->whereIn('id', $leadIds)
            ->whereIn('status', $eligibleStatuses)
            ->get();

        abort_if($leads->count() !== $leadIds->count(), 422, __('rml.admin.users.assign_leads_invalid'));

        $actor = $request->user();
        foreach ($leads as $lead) {
            $this->auditWorkflowService->assignAuditor($actor, $lead, $user);
        }

        return redirect()
            ->route('admin.users.index', ['tab' => 'auditors'])
            ->with('success', __('rml.admin.users.audits_assigned_flash', [
                'count' => $leads->count(),
                'name' => $user->name,
            ]));
    }

    private function authorizeAssignAudits(Request $request): void
    {
        abort_unless(
            $request->user()?->hasRole(UserRole::SuperAdmin->value)
            || $request->user()?->can(Permissions::ACCEPT_REJECT_LEADS),
            403,
        );
    }

    private function assertInternalAuditor(User $user): void
    {
        abort_unless(
            $user->hasRole(UserRole::InternalAuditor->value),
            404,
        );
    }

    /**
     * @return list<string>
     */
    private function assignableLeadStatuses(): array
    {
        return [
            LeadStatus::Submitted->value,
            LeadStatus::PendingEvidence->value,
            LeadStatus::PendingValidation->value,
            LeadStatus::Validating->value,
            LeadStatus::NeedsMoreInformation->value,
        ];
    }

    /**
     * @return list<string>|null
     */
    private function resolveAssignableStatusFilter(mixed $status): ?array
    {
        if (! is_string($status) || $status === '') {
            return null;
        }

        if ($status === 'pending_review') {
            return [
                LeadStatus::Submitted->value,
                LeadStatus::PendingValidation->value,
                LeadStatus::Validating->value,
            ];
        }

        if ($status === 'needs_information') {
            return [
                LeadStatus::PendingEvidence->value,
                LeadStatus::NeedsMoreInformation->value,
            ];
        }

        $eligible = $this->assignableLeadStatuses();
        abort_unless(in_array($status, $eligible, true), 422);

        return [$status];
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
            'phone' => $user->phone,
            'locale' => $user->locale,
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
            'rejection_reason_code' => $user->rejection_reason_code,
            'rejection_reason' => $user->rejection_reason,
            'rejection_comment' => $user->rejection_comment,
            'rejected_at' => $user->rejected_at?->toIso8601String(),
            'rejected_by' => $user->approval_status === ApprovalStatus::Rejected && $user->approvedBy
                ? [
                    'id' => $user->approvedBy->id,
                    'name' => $user->approvedBy->name,
                ]
                : null,
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
