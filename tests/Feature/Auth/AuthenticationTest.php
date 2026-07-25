<?php

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
    }

    public function test_users_can_authenticate_using_the_login_screen(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $user = User::factory()->create([
            'email' => 'buyer.login@rml.test',
        ]);
        $user->assignRole(UserRole::BuyerAdmin);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect('/buyer/dashboard');
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout');

        $this->assertGuest();
        $response->assertRedirect(route('login'));
    }

    public function test_guest_login_is_not_redirected_to_admin(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Auth/Login'));

        $this->get('/register')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Auth/Register'));
    }

    public function test_authenticated_users_are_redirected_from_login_by_role(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $admin = User::factory()->create(['email' => 'admin.redirect@rml.test']);
        $admin->assignRole(UserRole::SuperAdmin);

        $this->actingAs($admin)
            ->get('/login')
            ->assertRedirect('/admin/dashboard');
    }
}
