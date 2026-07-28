<?php

namespace App\Services;

use App\Enums\ApprovalStatus;
use App\Enums\UserRole;
use App\Mail\AccountApprovalStatusNotification;
use App\Models\User;
use App\Support\RejectionReasons;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Throwable;

class ApprovalService
{
    public function __construct(
        private readonly AuditLogService $auditLogService = new AuditLogService,
    ) {}

    public function approve(User $user, User $actor): User
    {
        return DB::transaction(function () use ($user, $actor) {
            $old = $this->snapshot($user);

            $user->forceFill([
                'approval_status' => ApprovalStatus::Approved,
                'approved_at' => now(),
                'approved_by' => $actor->id,
            ])->save();

            $user->sellerProfile?->forceFill([
                'approval_status' => ApprovalStatus::Approved,
            ])->save();

            $user->buyerProfile?->forceFill([
                'approval_status' => ApprovalStatus::Approved,
            ])->save();

            // Staff join an existing company — do not flip company approval from staff actions.
            if (! $user->hasRole(UserRole::SellerStaff->value)) {
                $company = $user->sellerProfile?->company ?? $user->buyerProfile?->company;
                $company?->forceFill([
                    'approval_status' => ApprovalStatus::Approved,
                    'approved_at' => now(),
                    'approved_by' => $actor->id,
                ])->save();
            }

            $user = $user->fresh(['sellerProfile.company', 'buyerProfile.company']);

            $this->auditLogService->log(
                'registration.approved',
                $user,
                $old,
                $this->snapshot($user),
                $actor,
            );

            $this->notifyUser($user, 'approved');

            return $user;
        });
    }

    public function reject(User $user, User $actor, string $reasonCode, ?string $comment = null): User
    {
        $label = RejectionReasons::label(RejectionReasons::CONTEXT_ACCOUNTS, $reasonCode);
        $comment = trim((string) $comment) ?: null;
        $message = RejectionReasons::composeMessage(
            RejectionReasons::CONTEXT_ACCOUNTS,
            $reasonCode,
            $comment,
        );

        return DB::transaction(function () use ($user, $actor, $reasonCode, $label, $comment, $message) {
            $old = $this->snapshot($user);

            $user->forceFill([
                'approval_status' => ApprovalStatus::Rejected,
                'approved_at' => null,
                'approved_by' => $actor->id,
                'rejection_reason_code' => $reasonCode,
                'rejection_reason' => $label,
                'rejection_comment' => $comment,
                'rejected_at' => now(),
            ])->save();

            $user->sellerProfile?->forceFill([
                'approval_status' => ApprovalStatus::Rejected,
            ])->save();

            $user->buyerProfile?->forceFill([
                'approval_status' => ApprovalStatus::Rejected,
            ])->save();

            if (! $user->hasRole(UserRole::SellerStaff->value)) {
                $company = $user->sellerProfile?->company ?? $user->buyerProfile?->company;
                if ($company) {
                    $notes = trim(($company->notes ? $company->notes."\n" : '').'Rejection: '.$message);
                    $company->forceFill([
                        'approval_status' => ApprovalStatus::Rejected,
                        'approved_at' => null,
                        'approved_by' => $actor->id,
                        'notes' => $notes,
                    ])->save();
                }
            }

            $user = $user->fresh(['sellerProfile.company', 'buyerProfile.company']);

            $this->auditLogService->log(
                'registration.rejected',
                $user,
                $old,
                [
                    ...$this->snapshot($user),
                    'rejection_reason_code' => $reasonCode,
                    'rejection_reason' => $label,
                    'rejection_comment' => $comment,
                ],
                $actor,
            );

            $this->notifyUser($user, 'rejected', $message);

            return $user;
        });
    }

    public function suspend(User $user, User $actor, ?string $reason = null): User
    {
        return DB::transaction(function () use ($user, $actor, $reason) {
            $old = $this->snapshot($user);

            $user->forceFill([
                'approval_status' => ApprovalStatus::Suspended,
            ])->save();

            $user->sellerProfile?->forceFill([
                'approval_status' => ApprovalStatus::Suspended,
            ])->save();

            $user->buyerProfile?->forceFill([
                'approval_status' => ApprovalStatus::Suspended,
            ])->save();

            if (! $user->hasRole(UserRole::SellerStaff->value)) {
                $company = $user->sellerProfile?->company ?? $user->buyerProfile?->company;
                $company?->forceFill([
                    'approval_status' => ApprovalStatus::Suspended,
                ])->save();
            }

            $user = $user->fresh(['sellerProfile.company', 'buyerProfile.company']);

            $this->auditLogService->log(
                'registration.suspended',
                $user,
                $old,
                [...$this->snapshot($user), 'reason' => $reason],
                $actor,
            );

            $this->notifyUser($user, 'suspended', $reason);

            return $user;
        });
    }

    public function reinstate(User $user, User $actor): User
    {
        return DB::transaction(function () use ($user, $actor) {
            $old = $this->snapshot($user);

            $user->forceFill([
                'approval_status' => ApprovalStatus::Approved,
                'approved_at' => $user->approved_at ?? now(),
                'approved_by' => $actor->id,
            ])->save();

            $user->sellerProfile?->forceFill([
                'approval_status' => ApprovalStatus::Approved,
            ])->save();

            $user->buyerProfile?->forceFill([
                'approval_status' => ApprovalStatus::Approved,
            ])->save();

            if (! $user->hasRole(UserRole::SellerStaff->value)) {
                $company = $user->sellerProfile?->company ?? $user->buyerProfile?->company;
                $company?->forceFill([
                    'approval_status' => ApprovalStatus::Approved,
                    'approved_at' => $company->approved_at ?? now(),
                    'approved_by' => $actor->id,
                ])->save();
            }

            $user = $user->fresh(['sellerProfile.company', 'buyerProfile.company']);

            $this->auditLogService->log(
                'registration.reinstated',
                $user,
                $old,
                $this->snapshot($user),
                $actor,
            );

            $this->notifyUser($user, 'approved');

            return $user;
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(User $user): array
    {
        $company = $user->sellerProfile?->company ?? $user->buyerProfile?->company;

        return [
            'user_id' => $user->id,
            'email' => $user->email,
            'approval_status' => $user->approval_status?->value,
            'company_id' => $company?->id,
            'company_status' => $company?->approval_status?->value,
            'roles' => $user->getRoleNames()->values()->all(),
        ];
    }

    private function notifyUser(User $user, string $status, ?string $reason = null): void
    {
        try {
            Mail::to($user->email)->send(new AccountApprovalStatusNotification($user, $status, $reason));
        } catch (Throwable) {
            // Ignore mail transport errors in local environments.
        }
    }
}
