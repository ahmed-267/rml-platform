<?php

namespace App\Models;

use App\Enums\ApprovalStatus;
use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

#[Fillable([
    'name',
    'email',
    'password',
    'approval_status',
    'locale',
    'phone',
    'approved_at',
    'approved_by',
    'rejection_reason_code',
    'rejection_reason',
    'rejection_comment',
    'rejected_at',
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'password' => 'hashed',
            'approval_status' => ApprovalStatus::class,
        ];
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(self::class, 'approved_by');
    }

    public function sellerProfile(): HasOne
    {
        return $this->hasOne(SellerProfile::class);
    }

    public function buyerProfile(): HasOne
    {
        return $this->hasOne(BuyerProfile::class);
    }

    public function submittedLeads(): HasMany
    {
        return $this->hasMany(Lead::class, 'submitted_by_user_id');
    }

    public function assignedAudits(): HasMany
    {
        return $this->hasMany(LeadAudit::class, 'auditor_user_id');
    }

    public function companiesApproved(): HasMany
    {
        return $this->hasMany(Company::class, 'approved_by');
    }

    public function isApproved(): bool
    {
        return $this->approval_status === ApprovalStatus::Approved;
    }

    public function isPendingApproval(): bool
    {
        return $this->approval_status === ApprovalStatus::Pending;
    }

    public function primaryRole(): ?UserRole
    {
        $roleName = $this->getRoleNames()->first();

        return $roleName ? UserRole::tryFrom($roleName) : null;
    }

    public function portal(): ?string
    {
        return $this->primaryRole()?->portal();
    }
}
