<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_choice_screen_can_be_rendered(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Auth/Register'));
    }

    public function test_seller_and_buyer_registration_pages_can_be_rendered(): void
    {
        $this->get('/register/seller')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Auth/RegisterSeller'));

        $this->get('/register/buyer')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Auth/RegisterBuyer'));
    }

    public function test_legacy_register_post_is_removed(): void
    {
        $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertMethodNotAllowed();

        $this->assertGuest();
    }

    public function test_forgot_password_page_uses_rml_auth_layout(): void
    {
        $this->get('/forgot-password')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Auth/ForgotPassword'));
    }
}
