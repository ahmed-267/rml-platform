<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicLandingTest extends TestCase
{
    use RefreshDatabase;

    public function test_landing_page_renders(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Welcome'));
    }

    public function test_homeowner_enquiry_placeholder_validates_and_flashes(): void
    {
        $this->from('/')
            ->post(route('enquiries.homeowner'), [
                'full_name' => 'Maria Lopez',
                'phone' => '+34 600 000 000',
                'email' => 'maria@example.com',
                'property_address' => 'Calle Mayor 12, Madrid',
                'postcode' => '28013',
                'service' => 'insulation',
                'message' => 'Interested in loft insulation',
                'consent' => '1',
            ])
            ->assertRedirect('/')
            ->assertSessionHas('success');

        $this->assertDatabaseHas('homeowner_enquiries', [
            'name' => 'Maria Lopez',
            'email' => 'maria@example.com',
        ]);
    }

    public function test_homeowner_enquiry_validation_requires_consent(): void
    {
        $this->from('/')
            ->post(route('enquiries.homeowner'), [
                'full_name' => 'Maria Lopez',
                'phone' => '+34 600 000 000',
                'email' => 'maria@example.com',
                'property_address' => 'Calle Mayor 12, Madrid',
                'postcode' => '28013',
                'service' => 'insulation',
            ])
            ->assertSessionHasErrors(['consent']);
    }

    public function test_contact_enquiry_placeholder_validates_and_flashes(): void
    {
        $this->from('/')
            ->post(route('enquiries.contact'), [
                'name' => 'Carlos Ruiz',
                'email' => 'carlos@example.com',
                'enquiry_type' => 'buyer',
                'subject' => 'Installer registration question',
                'message' => 'How long does approval take?',
            ])
            ->assertRedirect('/')
            ->assertSessionHas('success');
    }

    public function test_buy_sell_contact_are_not_separate_public_routes(): void
    {
        $this->get('/buy-leads')->assertNotFound();
        $this->get('/sell-leads')->assertNotFound();
        $this->get('/free-installation')->assertNotFound();
        $this->get('/contact')->assertNotFound();
    }
}
