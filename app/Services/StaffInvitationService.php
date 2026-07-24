<?php

namespace App\Services;

use App\Enums\ApprovalStatus;
use App\Enums\InvitationStatus;
use App\Enums\SellerType;
use App\Enums\UserRole;
use App\Mail\StaffInvitationMail;
use App\Models\SellerProfile;
use App\Models\SellerStaffInvitation;
use App\Models\User;
use App\Support\Permissions;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class StaffInvitationService
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
    ) {}

    public function invite(User $admin, string $email): SellerStaffInvitation
    {
        if (! $admin->can(Permissions::MANAGE_SELLER_STAFF)) {
            throw new AuthorizationException('You are not allowed to invite seller staff.');
        }

        $companyId = $admin->sellerProfile?->company_id;

        if ($companyId === null) {
            throw ValidationException::withMessages([
                'email' => 'Only company admins with a company can invite staff.',
            ]);
        }

        $email = Str::lower(trim($email));

        return DB::transaction(function () use ($admin, $email, $companyId) {
            SellerStaffInvitation::query()
                ->where('company_id', $companyId)
                ->where('email', $email)
                ->where('status', InvitationStatus::Pending)
                ->update(['status' => InvitationStatus::Cancelled]);

            $invitation = SellerStaffInvitation::query()->create([
                'company_id' => $companyId,
                'invited_by' => $admin->id,
                'email' => $email,
                'token' => Str::random(64),
                'status' => InvitationStatus::Pending,
                'expires_at' => now()->addDays(7),
            ]);

            try {
                Mail::to($email)->send(new StaffInvitationMail($invitation));
            } catch (Throwable) {
                // Local/dev mail failures must not block invitations.
            }

            $this->auditLogService->log(
                'seller_staff.invited',
                $invitation,
                null,
                ['email' => $email, 'company_id' => $companyId],
                $admin,
            );

            return $invitation;
        });
    }

    public function cancel(User $admin, SellerStaffInvitation $invitation): SellerStaffInvitation
    {
        if (! $admin->can(Permissions::MANAGE_SELLER_STAFF)) {
            throw new AuthorizationException('You are not allowed to cancel staff invitations.');
        }

        $companyId = $admin->sellerProfile?->company_id;

        if ($companyId === null || $invitation->company_id !== $companyId) {
            throw new AuthorizationException('This invitation does not belong to your company.');
        }

        $invitation->update([
            'status' => InvitationStatus::Cancelled,
        ]);

        $this->auditLogService->log(
            'seller_staff.invitation_cancelled',
            $invitation,
            null,
            ['email' => $invitation->email],
            $admin,
        );

        return $invitation->fresh();
    }

    /**
     * Accept a staff invitation.
     *
     * Staff accounts remain pending approval until a Super Admin approves them.
     *
     * @param  array{name: string, password: string, phone?: string|null}  $userData
     */
    public function accept(SellerStaffInvitation $invitation, array $userData): User
    {
        if ($invitation->status !== InvitationStatus::Pending) {
            throw ValidationException::withMessages([
                'token' => 'This invitation is no longer valid.',
            ]);
        }

        if ($invitation->expires_at !== null && $invitation->expires_at->isPast()) {
            $invitation->update(['status' => InvitationStatus::Expired]);

            throw ValidationException::withMessages([
                'token' => 'This invitation has expired.',
            ]);
        }

        return DB::transaction(function () use ($invitation, $userData) {
            $user = User::query()->create([
                'name' => $userData['name'],
                'email' => $invitation->email,
                'password' => $userData['password'],
                'phone' => $userData['phone'] ?? null,
                'approval_status' => ApprovalStatus::Pending,
                'locale' => app()->getLocale() ?: 'en',
                'email_verified_at' => now(),
            ]);

            $user->assignRole(UserRole::SellerStaff->value);

            SellerProfile::query()->create([
                'user_id' => $user->id,
                'company_id' => $invitation->company_id,
                'seller_type' => SellerType::SellerStaff,
                'invited_by' => $invitation->invited_by,
                'approval_status' => ApprovalStatus::Pending,
            ]);

            $invitation->update([
                'status' => InvitationStatus::Accepted,
                'accepted_at' => now(),
            ]);

            $this->auditLogService->log(
                'seller_staff.invitation_accepted',
                $invitation,
                null,
                [
                    'user_id' => $user->id,
                    'company_id' => $invitation->company_id,
                    'note' => 'Staff remain pending approval until Super Admin approves',
                ],
                $user,
            );

            return $user->fresh(['sellerProfile.company']);
        });
    }
}
