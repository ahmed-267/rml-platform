<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Http\Requests\Seller\UpdateStaffCommissionRequest;
use App\Models\Lead;
use App\Models\SellerProfile;
use App\Models\SellerStaffInvitation;
use App\Models\User;
use App\Services\AuditLogService;
use App\Support\Permissions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StaffController extends Controller
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();

        $canManageStaff = $user?->can(Permissions::MANAGE_SELLER_STAFF) ?? false;
        $canManageCommissions = $user?->can(Permissions::MANAGE_STAFF_COMMISSIONS) ?? false;

        abort_unless($canManageStaff || $canManageCommissions, 403);

        $companyId = $user->sellerProfile?->company_id;
        abort_unless($companyId, 403);

        $tab = $this->resolveTab(
            $request->string('tab')->toString(),
            $canManageStaff,
        );

        $staff = SellerProfile::query()
            ->with(['user:id,name,email,approval_status'])
            ->where('company_id', $companyId)
            ->get()
            ->map(function (SellerProfile $profile) {
                $role = $profile->user?->getRoleNames()->first();

                return [
                    'user_id' => $profile->user_id,
                    'name' => $profile->user?->name,
                    'email' => $profile->user?->email,
                    'seller_type' => $profile->seller_type?->value,
                    'role' => $role,
                    'approval_status' => $profile->approval_status?->value
                        ?? $profile->user?->approval_status,
                    'commission_rate' => $profile->commission_rate !== null
                        ? (float) $profile->commission_rate
                        : null,
                    'leads_submitted' => Lead::query()
                        ->where('submitted_by_user_id', $profile->user_id)
                        ->count(),
                ];
            });

        $invitations = [];
        if ($canManageStaff) {
            $invitations = SellerStaffInvitation::query()
                ->where('company_id', $companyId)
                ->latest()
                ->get()
                ->map(fn (SellerStaffInvitation $invitation) => [
                    'id' => $invitation->id,
                    'email' => $invitation->email,
                    'status' => $invitation->status?->value,
                    'expires_at' => $invitation->expires_at?->toIso8601String(),
                    'accepted_at' => $invitation->accepted_at?->toIso8601String(),
                    'created_at' => $invitation->created_at?->toIso8601String(),
                ]);
        }

        return Inertia::render('Seller/Staff/Index', [
            'tab' => $tab,
            'staff' => $staff,
            'invitations' => $invitations,
            'can_manage_staff' => $canManageStaff,
            'can_manage_commissions' => $canManageCommissions,
        ]);
    }

    public function updateCommission(UpdateStaffCommissionRequest $request, User $user): RedirectResponse
    {
        $profile = $user->sellerProfile;
        abort_unless($profile, 404);

        $oldRate = $profile->commission_rate !== null
            ? (float) $profile->commission_rate
            : null;

        $profile->commission_rate = $request->input('commission_rate');
        $profile->save();

        $newRate = $profile->commission_rate !== null
            ? (float) $profile->commission_rate
            : null;

        $this->auditLogService->log(
            'seller_staff.commission_updated',
            $profile,
            ['commission_rate' => $oldRate],
            ['commission_rate' => $newRate, 'user_id' => $user->id],
            $request->user(),
        );

        return back()->with('success', __('rml.seller.staff.commission_updated'));
    }

    private function resolveTab(string $requested, bool $canManageStaff): string
    {
        if ($canManageStaff && $requested === 'invite') {
            return 'invite';
        }

        return 'staff';
    }
}
