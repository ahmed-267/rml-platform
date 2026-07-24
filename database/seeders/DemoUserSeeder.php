<?php

namespace Database\Seeders;

use App\Enums\ApprovalStatus;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;

class DemoUserSeeder extends Seeder
{
    /**
     * Demo credentials for local/MVP development.
     * Password for all accounts: password
     */
    public function run(): void
    {
        $users = [
            [
                'name' => 'Super Admin',
                'email' => 'admin@rml.test',
                'role' => UserRole::SuperAdmin,
                'status' => ApprovalStatus::Approved,
            ],
            [
                'name' => 'Admin Staff',
                'email' => 'staff@rml.test',
                'role' => UserRole::AdminStaff,
                'status' => ApprovalStatus::Approved,
            ],
            [
                'name' => 'Internal Auditor',
                'email' => 'auditor@rml.test',
                'role' => UserRole::InternalAuditor,
                'status' => ApprovalStatus::Approved,
            ],
            [
                'name' => 'Carlos Martín',
                'email' => 'seller.admin@rml.test',
                'role' => UserRole::SellerCompanyAdmin,
                'status' => ApprovalStatus::Approved,
            ],
            [
                'name' => 'Elena García',
                'email' => 'seller.staff@rml.test',
                'role' => UserRole::SellerStaff,
                'status' => ApprovalStatus::Approved,
            ],
            [
                'name' => 'Pablo Herrera',
                'email' => 'agent@rml.test',
                'role' => UserRole::IndividualSellerAgent,
                'status' => ApprovalStatus::Approved,
            ],
            [
                'name' => 'Karim Benali',
                'email' => 'buyer@rml.test',
                'role' => UserRole::BuyerAdmin,
                'status' => ApprovalStatus::Approved,
            ],
            [
                'name' => 'Rafael Cano',
                'email' => 'pending.seller@rml.test',
                'role' => UserRole::SellerCompanyAdmin,
                'status' => ApprovalStatus::Pending,
            ],
        ];

        foreach ($users as $data) {
            $user = User::query()->updateOrCreate(
                ['email' => $data['email']],
                [
                    'name' => $data['name'],
                    'password' => 'password',
                    'email_verified_at' => now(),
                    'approval_status' => $data['status']->value,
                    'locale' => 'en',
                    'approved_at' => $data['status'] === ApprovalStatus::Approved ? now() : null,
                ],
            );

            $user->syncRoles([$data['role']->value]);
        }
    }
}
