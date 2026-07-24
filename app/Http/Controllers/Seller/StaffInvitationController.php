<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Http\Requests\Seller\InviteStaffRequest;
use App\Models\SellerStaffInvitation;
use App\Services\StaffInvitationService;
use App\Support\Permissions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StaffInvitationController extends Controller
{
    public function __construct(
        private readonly StaffInvitationService $staffInvitationService,
    ) {}

    public function index(Request $request): Response
    {
        abort_unless($request->user()?->can(Permissions::MANAGE_SELLER_STAFF), 403);

        $companyId = $request->user()->sellerProfile?->company_id;
        abort_unless($companyId, 403);

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

        return Inertia::render('Seller/Staff/Invite', [
            'invitations' => $invitations,
        ]);
    }

    public function create(Request $request): Response
    {
        return $this->index($request);
    }

    public function store(InviteStaffRequest $request): RedirectResponse
    {
        $this->staffInvitationService->invite(
            $request->user(),
            $request->string('email')->toString(),
        );

        return back()->with('success', __('rml.seller.staff.invite_sent'));
    }

    public function destroy(Request $request, SellerStaffInvitation $invitation): RedirectResponse
    {
        $this->staffInvitationService->cancel($request->user(), $invitation);

        return back()->with('success', __('rml.seller.staff.invite_cancelled'));
    }
}
