<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RejectRegistrationRequest;
use App\Models\User;
use App\Services\ApprovalService;
use App\Support\Permissions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ApprovalController extends Controller
{
    public function __construct(
        private readonly ApprovalService $approvalService,
    ) {}

    public function index(Request $request): RedirectResponse
    {
        abort_unless(
            $request->user()?->can(Permissions::APPROVE_SELLERS)
                || $request->user()?->can(Permissions::APPROVE_BUYERS)
                || $request->user()?->can(Permissions::MANAGE_USERS)
                || $request->user()?->hasRole(UserRole::SuperAdmin->value),
            403,
        );

        return redirect()->route('admin.sellers.index', ['approval_status' => 'pending']);
    }

    public function approve(Request $request, User $user): RedirectResponse
    {
        $this->authorizeAction($request, $user);

        $this->approvalService->approve($user, $request->user());

        return back()->with('success', __('rml.approvals.approved_flash'));
    }

    public function reject(RejectRegistrationRequest $request, User $user): RedirectResponse
    {
        $this->authorizeAction($request, $user);

        $this->approvalService->reject($user, $request->user(), $request->string('reason')->toString());

        return back()->with('success', __('rml.approvals.rejected_flash'));
    }

    public function suspend(Request $request, User $user): RedirectResponse
    {
        $this->authorizeAction($request, $user);

        $this->approvalService->suspend(
            $user,
            $request->user(),
            $request->input('reason'),
        );

        return back()->with('success', __('rml.approvals.suspended_flash'));
    }

    private function authorizeAction(Request $request, User $user): void
    {
        $isSeller = $user->hasAnyRole([
            UserRole::SellerCompanyAdmin->value,
            UserRole::SellerStaff->value,
            UserRole::IndividualSellerAgent->value,
        ]);
        $isBuyer = $user->hasRole(UserRole::BuyerAdmin->value);

        if ($isSeller) {
            abort_unless(
                $request->user()?->can(Permissions::APPROVE_SELLERS)
                    || $request->user()?->hasRole(UserRole::SuperAdmin->value),
                403,
            );

            return;
        }

        if ($isBuyer) {
            abort_unless(
                $request->user()?->can(Permissions::APPROVE_BUYERS)
                    || $request->user()?->hasRole(UserRole::SuperAdmin->value),
                403,
            );

            return;
        }

        abort_unless(
            $request->user()?->can(Permissions::MANAGE_USERS)
                || $request->user()?->hasRole(UserRole::SuperAdmin->value),
            403,
        );
    }
}
