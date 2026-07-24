<?php

namespace App\Http\Controllers\Seller;

use App\Enums\InvitationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Seller\AcceptStaffInvitationRequest;
use App\Models\SellerStaffInvitation;
use App\Services\StaffInvitationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AcceptInvitationController extends Controller
{
    public function __construct(
        private readonly StaffInvitationService $staffInvitationService,
    ) {}

    public function show(Request $request, string $token): Response|RedirectResponse
    {
        $invitation = $this->findPendingInvitation($token);

        if (! $invitation) {
            return redirect()
                ->route('login')
                ->withErrors(['email' => __('rml.seller.staff.invite_invalid')]);
        }

        return Inertia::render('Auth/AcceptStaffInvitation', [
            'token' => $invitation->token,
            'email' => $invitation->email,
            'company_name' => $invitation->company?->name,
            'expires_at' => $invitation->expires_at?->toIso8601String(),
        ]);
    }

    public function store(AcceptStaffInvitationRequest $request, string $token): RedirectResponse
    {
        $invitation = $this->findPendingInvitation($token);

        if (! $invitation) {
            return redirect()
                ->route('login')
                ->withErrors(['email' => __('rml.seller.staff.invite_invalid')]);
        }

        $this->staffInvitationService->accept($invitation, $request->userPayload());

        return redirect()
            ->route('login')
            ->with('success', __('rml.seller.staff.accept_success'));
    }

    private function findPendingInvitation(string $token): ?SellerStaffInvitation
    {
        $invitation = SellerStaffInvitation::query()
            ->with('company:id,name')
            ->where('token', $token)
            ->where('status', InvitationStatus::Pending)
            ->first();

        if (! $invitation) {
            return null;
        }

        if ($invitation->expires_at !== null && $invitation->expires_at->isPast()) {
            $invitation->update(['status' => InvitationStatus::Expired]);

            return null;
        }

        return $invitation;
    }
}
