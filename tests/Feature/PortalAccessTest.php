<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Database\Seeders\DemoUserSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PortalAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            RolePermissionSeeder::class,
            DemoUserSeeder::class,
        ]);
    }

    public function test_demo_users_exist_for_all_roles(): void
    {
        $expected = [
            'admin@rml.test' => UserRole::SuperAdmin,
            'staff@rml.test' => UserRole::AdminStaff,
            'auditor@rml.test' => UserRole::InternalAuditor,
            'seller.admin@rml.test' => UserRole::SellerCompanyAdmin,
            'seller.staff@rml.test' => UserRole::SellerStaff,
            'agent@rml.test' => UserRole::IndividualSellerAgent,
            'buyer@rml.test' => UserRole::BuyerAdmin,
        ];

        foreach ($expected as $email => $role) {
            $user = User::query()->where('email', $email)->first();
            $this->assertNotNull($user, "Missing demo user {$email}");
            $this->assertTrue($user->hasRole($role->value));
        }
    }

    public function test_login_redirects_each_role_to_correct_dashboard(): void
    {
        $cases = [
            'admin@rml.test' => '/admin/dashboard',
            'staff@rml.test' => '/admin/dashboard',
            'auditor@rml.test' => '/auditor/dashboard',
            'seller.admin@rml.test' => '/seller/dashboard',
            'seller.staff@rml.test' => '/seller/dashboard',
            'agent@rml.test' => '/seller/dashboard',
            'buyer@rml.test' => '/buyer/dashboard',
        ];

        foreach ($cases as $email => $path) {
            $response = $this->post('/login', [
                'email' => $email,
                'password' => 'password',
            ]);

            $response->assertRedirect($path);
            $this->assertAuthenticated();
            $this->post('/logout');
        }
    }

    public function test_dashboard_route_redirects_to_portal_home(): void
    {
        $admin = User::query()->where('email', 'admin@rml.test')->firstOrFail();

        $this->actingAs($admin)
            ->get('/dashboard')
            ->assertRedirect(route('admin.dashboard'));
    }

    public function test_buyer_cannot_access_admin_dashboard(): void
    {
        $buyer = User::query()->where('email', 'buyer@rml.test')->firstOrFail();

        $this->actingAs($buyer)
            ->get('/admin/dashboard')
            ->assertForbidden();
    }

    public function test_seller_cannot_access_buyer_dashboard(): void
    {
        $seller = User::query()->where('email', 'seller.admin@rml.test')->firstOrFail();

        $this->actingAs($seller)
            ->get('/buyer/dashboard')
            ->assertForbidden();
    }

    public function test_auditor_cannot_access_other_portals(): void
    {
        $auditor = User::query()->where('email', 'auditor@rml.test')->firstOrFail();

        $this->actingAs($auditor)->get('/admin/dashboard')->assertForbidden();
        $this->actingAs($auditor)->get('/seller/dashboard')->assertForbidden();
        $this->actingAs($auditor)->get('/buyer/dashboard')->assertForbidden();
    }

    public function test_guest_cannot_access_portals(): void
    {
        $this->get('/admin/dashboard')->assertRedirect(route('login'));
        $this->get('/seller/dashboard')->assertRedirect(route('login'));
        $this->get('/buyer/dashboard')->assertRedirect(route('login'));
        $this->get('/auditor/dashboard')->assertRedirect(route('login'));
        $this->get('/dashboard')->assertRedirect(route('login'));
    }

    public function test_pending_seller_is_redirected_to_pending_approval(): void
    {
        $this->post('/login', [
            'email' => 'pending.seller@rml.test',
            'password' => 'password',
        ])->assertRedirect('/pending-approval');
    }

    public function test_locale_can_be_updated(): void
    {
        $this->from('/')
            ->post('/locale', ['locale' => 'es'])
            ->assertRedirect('/');

        $this->assertSame('es', session('locale'));
    }
}
