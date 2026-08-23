<?php

namespace App\Services\Admin;

use App\Enums\ApprovalStatus;
use App\Enums\CompanyType;
use App\Enums\PackageStatus;
use App\Enums\PaymentMethod;
use App\Models\Company;
use App\Models\LeadPackage;
use App\Models\Purchase;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\Buyer\BuyerPurchaseService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Admin / Super Admin purchases on behalf of an approved buyer.
 *
 * Does not implement a separate sale engine — delegates to BuyerPurchaseService
 * (same purchase, payment, invoice, and privacy rules as the Buyer Portal).
 */
class AdminBuyerPurchaseService
{
    public function __construct(
        private readonly BuyerPurchaseService $purchaseService = new BuyerPurchaseService,
        private readonly AuditLogService $auditLogService = new AuditLogService,
    ) {}

    /**
     * @param  list<int>  $leadIds
     */
    public function buyLeads(
        User $admin,
        Company $buyerCompany,
        array $leadIds,
        PaymentMethod $method,
        ?User $buyerUser = null,
    ): Purchase {
        $this->assertApprovedBuyerCompany($buyerCompany);
        $buyer = $this->resolveBuyerUser($buyerCompany, $buyerUser);

        return DB::transaction(function () use ($admin, $buyer, $buyerCompany, $leadIds, $method) {
            $purchase = $this->purchaseService->createLeadPurchase($buyer, $leadIds, $method);

            $this->auditLogService->log(
                'purchase.created_by_admin',
                $purchase,
                null,
                [
                    'type' => 'leads',
                    'lead_ids' => $leadIds,
                    'buyer_company_id' => $buyerCompany->id,
                    'buyer_user_id' => $buyer->id,
                    'payment_method' => $method->value,
                    'payment_id' => $purchase->payment_id,
                    'amount' => $purchase->total_amount,
                    'admin_user_id' => $admin->id,
                ],
                $admin,
            );

            // Legacy audit action kept for existing reports/tests.
            $this->auditLogService->log(
                'sale.created_by_admin',
                $purchase,
                null,
                [
                    'type' => 'leads',
                    'lead_ids' => $leadIds,
                    'buyer_company_id' => $purchase->buyer_company_id,
                    'payment_method' => $method->value,
                    'payment_id' => $purchase->payment_id,
                    'amount' => $purchase->total_amount,
                    'admin_user_id' => $admin->id,
                ],
                $admin,
            );

            return $purchase->fresh(['payment', 'items.lead', 'buyerCompany', 'buyerUser']);
        });
    }

    public function buyPackage(
        User $admin,
        Company $buyerCompany,
        LeadPackage $package,
        PaymentMethod $method,
        ?User $buyerUser = null,
    ): Purchase {
        $this->assertApprovedBuyerCompany($buyerCompany);
        $buyer = $this->resolveBuyerUser($buyerCompany, $buyerUser);

        return DB::transaction(function () use ($admin, $buyer, $buyerCompany, $package, $method) {
            $package = LeadPackage::query()->whereKey($package->id)->lockForUpdate()->firstOrFail();

            if (! in_array($package->status, [PackageStatus::Available, PackageStatus::Draft], true)) {
                throw ValidationException::withMessages([
                    'package_id' => __('rml.admin.sales.package_unavailable'),
                ]);
            }

            if ($package->buyer_company_id === null) {
                $package->update(['buyer_company_id' => $buyerCompany->id]);
            } elseif ((int) $package->buyer_company_id !== (int) $buyerCompany->id) {
                throw ValidationException::withMessages([
                    'package_id' => __('rml.admin.sales.package_assigned_elsewhere'),
                ]);
            }

            if ($package->status === PackageStatus::Draft) {
                $package->update(['status' => PackageStatus::Available]);
            }

            $purchase = $this->purchaseService->createPackagePurchase($buyer, $package->fresh(), $method);

            $this->auditLogService->log(
                'purchase.created_by_admin',
                $purchase,
                null,
                [
                    'type' => 'package',
                    'package_id' => $package->id,
                    'buyer_company_id' => $buyerCompany->id,
                    'buyer_user_id' => $buyer->id,
                    'payment_method' => $method->value,
                    'payment_id' => $purchase->payment_id,
                    'amount' => $purchase->total_amount,
                    'admin_user_id' => $admin->id,
                ],
                $admin,
            );

            $this->auditLogService->log(
                'sale.created_by_admin',
                $purchase,
                null,
                [
                    'type' => 'package',
                    'package_id' => $package->id,
                    'buyer_company_id' => $purchase->buyer_company_id,
                    'payment_method' => $method->value,
                    'payment_id' => $purchase->payment_id,
                    'amount' => $purchase->total_amount,
                    'admin_user_id' => $admin->id,
                ],
                $admin,
            );

            return $purchase->fresh(['payment', 'items.lead', 'items.leadPackage', 'buyerCompany', 'buyerUser']);
        });
    }

    /**
     * Approved buyer/installer companies for the admin Buy as buyer UI.
     *
     * @return list<array<string, mixed>>
     */
    public function approvedBuyerOptions(): array
    {
        return Company::query()
            ->where('type', CompanyType::Buyer->value)
            ->where('approval_status', ApprovalStatus::Approved->value)
            ->withCount([
                'purchases as active_purchases_count' => fn ($q) => $q->whereIn('status', ['pending', 'paid']),
                'buyerProfiles as buyer_user_count',
            ])
            ->orderBy('name')
            ->get([
                'id',
                'name',
                'city',
                'postcode',
                'formatted_address',
                'approval_status',
            ])
            ->map(function (Company $company) {
                $baseLocation = $company->formatted_address
                    ?: trim(implode(', ', array_filter([
                        $company->city,
                        $company->postcode,
                    ])));

                return [
                    'id' => (int) $company->id,
                    'name' => $company->name,
                    'city' => $company->city,
                    'base_location' => $baseLocation !== '' ? $baseLocation : null,
                    'status' => $company->approval_status?->value ?? ApprovalStatus::Approved->value,
                    'active_purchases' => (int) ($company->active_purchases_count ?? 0),
                    'buyer_user_count' => (int) ($company->buyer_user_count ?? 0),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Buyer users belonging to approved buyer companies.
     *
     * @return list<array<string, mixed>>
     */
    public function buyerUserOptions(?array $companyIds = null): array
    {
        $companyIds ??= collect($this->approvedBuyerOptions())->pluck('id')->all();

        if ($companyIds === []) {
            return [];
        }

        return User::query()
            ->whereHas(
                'buyerProfile',
                fn ($q) => $q->whereIn('company_id', $companyIds),
            )
            ->with('buyerProfile:id,user_id,company_id')
            ->orderBy('name')
            ->get(['id', 'name', 'email'])
            ->map(fn (User $user) => [
                'id' => (int) $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'company_id' => $user->buyerProfile?->company_id
                    ? (int) $user->buyerProfile->company_id
                    : null,
            ])
            ->values()
            ->all();
    }

    public function resolveBuyerUser(Company $buyerCompany, ?User $buyerUser): User
    {
        if ($buyerUser) {
            $buyerUser->loadMissing('buyerProfile');
            if ((int) $buyerUser->buyerProfile?->company_id !== (int) $buyerCompany->id) {
                throw ValidationException::withMessages([
                    'buyer_user_id' => __('rml.admin.sales.buyer_mismatch'),
                ]);
            }

            return $buyerUser;
        }

        $user = User::query()
            ->whereHas(
                'buyerProfile',
                fn ($q) => $q->where('company_id', $buyerCompany->id),
            )
            ->orderBy('id')
            ->first();

        if (! $user) {
            throw ValidationException::withMessages([
                'buyer_company_id' => __('rml.admin.sales.buyer_user_missing'),
            ]);
        }

        return $user;
    }

    private function assertApprovedBuyerCompany(Company $buyerCompany): void
    {
        if ($buyerCompany->type !== CompanyType::Buyer) {
            throw ValidationException::withMessages([
                'buyer_company_id' => __('rml.admin.sales.buyer_invalid'),
            ]);
        }

        if ($buyerCompany->approval_status !== ApprovalStatus::Approved) {
            throw ValidationException::withMessages([
                'buyer_company_id' => __('rml.admin.sales.buyer_invalid'),
            ]);
        }
    }
}
