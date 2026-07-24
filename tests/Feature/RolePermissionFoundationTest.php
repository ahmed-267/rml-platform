<?php

namespace Tests\Feature;

use App\Enums\ApprovalStatus;
use App\Enums\UserRole;
use App\Models\User;
use App\Support\Permissions;
use Database\Seeders\DemoUserSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RolePermissionFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_roles_and_permissions_are_seeded(): void
    {
        $this->seed(RolePermissionSeeder::class);

        foreach (UserRole::cases() as $role) {
            $this->assertDatabaseHas('roles', ['name' => $role->value]);
        }

        foreach (Permissions::all() as $permission) {
            $this->assertDatabaseHas('permissions', ['name' => $permission]);
        }

        $superAdmin = Role::findByName(UserRole::SuperAdmin->value);
        $this->assertTrue(
            $superAdmin->hasPermissionTo(Permissions::MANAGE_SETTINGS),
        );
    }

    public function test_demo_users_receive_correct_roles(): void
    {
        $this->seed([
            RolePermissionSeeder::class,
            DemoUserSeeder::class,
        ]);

        $admin = User::query()->where('email', 'admin@rml.test')->firstOrFail();
        $this->assertTrue($admin->hasRole(UserRole::SuperAdmin->value));
        $this->assertSame(ApprovalStatus::Approved, $admin->approval_status);
        $this->assertSame('admin', $admin->portal());

        $buyer = User::query()->where('email', 'buyer@rml.test')->firstOrFail();
        $this->assertTrue($buyer->hasRole(UserRole::BuyerAdmin->value));
        $this->assertTrue($buyer->can(Permissions::BUY_LEADS));
        $this->assertFalse($buyer->can(Permissions::MANAGE_SETTINGS));

        $pending = User::query()->where('email', 'pending.seller@rml.test')->firstOrFail();
        $this->assertTrue($pending->isPendingApproval());
    }

    public function test_authenticated_dashboard_is_reachable(): void
    {
        $this->seed([
            RolePermissionSeeder::class,
            DemoUserSeeder::class,
        ]);

        $admin = User::query()->where('email', 'admin@rml.test')->firstOrFail();

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk();
    }
}
