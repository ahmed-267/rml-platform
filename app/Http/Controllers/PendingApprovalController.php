<?php

namespace App\Http\Controllers;

use App\Enums\ApprovalStatus;
use App\Support\PortalRouter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PendingApprovalController extends Controller
{
    public function __invoke(Request $request): Response|RedirectResponse
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('login');
        }

        if (
            PortalRouter::requiresApprovalGate($user)
            && $user->approval_status === ApprovalStatus::Approved
        ) {
            return redirect()->to(PortalRouter::dashboardPath($user));
        }

        if (PortalRouter::isInternalRole($user)) {
            return redirect()->to(PortalRouter::dashboardPath($user));
        }

        $user->loadMissing(['sellerProfile.company', 'buyerProfile.company']);

        $company = $user->sellerProfile?->company ?? $user->buyerProfile?->company;

        return Inertia::render('Auth/PendingApproval', [
            'account' => [
                'name' => $user->name,
                'email' => $user->email,
                'approval_status' => $user->approval_status?->value,
                'role' => $user->primaryRole()?->value,
                'role_label' => $user->primaryRole()?->label(),
                'company_name' => $company?->name,
                'company_type' => $company?->type?->value,
                'submitted_at' => $user->created_at?->toIso8601String(),
            ],
        ]);
    }
}
