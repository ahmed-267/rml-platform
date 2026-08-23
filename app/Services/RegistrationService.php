<?php

namespace App\Services;

use App\Enums\ApprovalStatus;
use App\Enums\CompanyType;
use App\Enums\SellerType;
use App\Enums\UserRole;
use App\Mail\RegistrationPendingAdminNotification;
use App\Models\BuyerProfile;
use App\Models\Company;
use App\Models\SellerProfile;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Throwable;

class RegistrationService
{
    public function __construct(
        private readonly LocationService $locationService,
    ) {}

    /**
     * @param  array{
     *     account_type: string,
     *     name: string,
     *     email: string,
     *     password: string,
     *     phone?: string|null,
     *     whatsapp?: string|null,
     *     postcode?: string|null,
     *     address?: string|null,
     *     city?: string|null,
     *     country?: string|null,
     *     company_name?: string|null,
     * }  $data
     */
    public function registerSeller(array $data): User
    {
        return DB::transaction(function () use ($data) {
            $isCompany = ($data['account_type'] ?? '') === 'company';

            $user = User::query()->create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
                'phone' => $data['phone'] ?? $data['whatsapp'] ?? null,
                'approval_status' => ApprovalStatus::Pending,
                'locale' => app()->getLocale() ?: 'en',
                'email_verified_at' => now(),
            ]);

            $company = null;

            if ($isCompany) {
                $company = Company::query()->create([
                    'name' => $data['company_name'],
                    'type' => CompanyType::Seller,
                    'approval_status' => ApprovalStatus::Pending,
                    'contact_name' => $data['name'],
                    'email' => $data['email'],
                    'phone' => $data['phone'] ?? null,
                    'whatsapp' => $data['whatsapp'] ?? $data['phone'] ?? null,
                    'address' => $data['address'] ?? null,
                    'city' => $data['city'] ?? null,
                    'postcode' => $data['postcode'] ?? null,
                    'country' => $data['country'] ?? 'ES',
                ]);

                $this->locationService->geocodeCompany($company);

                $user->assignRole(UserRole::SellerCompanyAdmin->value);

                SellerProfile::query()->create([
                    'user_id' => $user->id,
                    'company_id' => $company->id,
                    'seller_type' => SellerType::CompanyAdmin,
                    'approval_status' => ApprovalStatus::Pending,
                ]);
            } else {
                $user->assignRole(UserRole::IndividualSellerAgent->value);

                SellerProfile::query()->create([
                    'user_id' => $user->id,
                    'company_id' => null,
                    'seller_type' => SellerType::IndividualAgent,
                    'approval_status' => ApprovalStatus::Pending,
                ]);
            }

            $this->notifyAdmins($user, 'seller');

            return $user->fresh(['sellerProfile.company']);
        });
    }

    /**
     * @param  array{
     *     name: string,
     *     email: string,
     *     password: string,
     *     company_name: string,
     *     phone?: string|null,
     *     whatsapp?: string|null,
     *     address?: string|null,
     *     city?: string|null,
     *     postcode?: string|null,
     *     country?: string|null,
     *     services_offered?: list<string>,
     *     preferred_zones?: list<string>,
     *     max_distance_km?: int|null,
     * }  $data
     */
    public function registerBuyer(array $data): User
    {
        return DB::transaction(function () use ($data) {
            $user = User::query()->create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
                'phone' => $data['phone'] ?? $data['whatsapp'] ?? null,
                'approval_status' => ApprovalStatus::Pending,
                'locale' => app()->getLocale() ?: 'en',
                'email_verified_at' => now(),
            ]);

            $company = Company::query()->create([
                'name' => $data['company_name'],
                'type' => CompanyType::Buyer,
                'approval_status' => ApprovalStatus::Pending,
                'contact_name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'whatsapp' => $data['whatsapp'] ?? null,
                'address' => $data['address'] ?? null,
                'city' => $data['city'] ?? null,
                'postcode' => $data['postcode'] ?? null,
                'country' => $data['country'] ?? 'ES',
            ]);

            $this->locationService->geocodeCompany($company);

            $user->assignRole(UserRole::BuyerAdmin->value);

            BuyerProfile::query()->create([
                'user_id' => $user->id,
                'company_id' => $company->id,
                'services_offered' => $data['services_offered'] ?? [],
                'preferred_zones' => $data['preferred_zones'] ?? [],
                'max_distance_km' => $data['max_distance_km'] ?? null,
                'billing_status' => 'pending',
                'approval_status' => ApprovalStatus::Pending,
            ]);

            $this->notifyAdmins($user, 'buyer');

            return $user->fresh(['buyerProfile.company']);
        });
    }

    private function notifyAdmins(User $user, string $type): void
    {
        try {
            $admins = User::role(UserRole::SuperAdmin->value)->get();

            foreach ($admins as $admin) {
                Mail::to($admin->email)->send(new RegistrationPendingAdminNotification($user, $type));
            }
        } catch (Throwable) {
            // Local/dev mail failures must not block registration.
        }
    }
}
