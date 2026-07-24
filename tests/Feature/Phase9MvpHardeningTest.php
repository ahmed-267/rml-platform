<?php

namespace Tests\Feature;

use App\Enums\InvitationStatus;
use App\Enums\InvoiceType;
use App\Enums\LeadStatus;
use App\Enums\PaymentMethod;
use App\Enums\UserRole;
use App\Models\Invoice;
use App\Models\Lead;
use App\Models\SellerStaffInvitation;
use App\Models\User;
use App\Services\Buyer\LeadAvailabilityService;
use Database\Seeders\DemoUserSeeder;
use Database\Seeders\DomainDemoSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SchemeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class Phase9MvpHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            RolePermissionSeeder::class,
            DemoUserSeeder::class,
            SchemeSeeder::class,
            DomainDemoSeeder::class,
        ]);

        Mail::fake();
    }

    public function test_guest_cannot_access_portals(): void
    {
        $this->get(route('seller.dashboard'))->assertRedirect(route('login'));
        $this->get(route('buyer.dashboard'))->assertRedirect(route('login'));
        $this->get(route('admin.dashboard'))->assertRedirect(route('login'));
        $this->get(route('auditor.dashboard'))->assertRedirect(route('login'));
    }

    public function test_pending_user_cannot_access_seller_portal(): void
    {
        $pending = User::query()->where('email', 'pending.seller@rml.test')->firstOrFail();

        $this->actingAs($pending)
            ->get(route('seller.dashboard'))
            ->assertRedirect();
    }

    public function test_cross_portal_access_is_blocked(): void
    {
        $seller = User::query()->where('email', 'seller.admin@rml.test')->firstOrFail();
        $buyer = User::query()->where('email', 'buyer@rml.test')->firstOrFail();
        $auditor = User::query()->where('email', 'auditor@rml.test')->firstOrFail();

        $this->actingAs($seller)->get(route('buyer.dashboard'))->assertForbidden();
        $this->actingAs($buyer)->get(route('seller.dashboard'))->assertForbidden();
        $this->actingAs($auditor)->get(route('admin.settings.index'))->assertForbidden();
        $this->actingAs($auditor)->get(route('admin.payments.index'))->assertForbidden();
    }

    public function test_seller_commission_payload_omits_base_amount(): void
    {
        $seller = User::query()->where('email', 'seller.admin@rml.test')->firstOrFail();

        $this->actingAs($seller)
            ->get(route('seller.payments'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Seller/Payments')
                ->has('commissions.data.0')
                ->missing('commissions.data.0.base_amount'));
    }

    public function test_authorised_buyer_can_download_own_invoice(): void
    {
        $buyer = User::query()->where('email', 'buyer@rml.test')->firstOrFail();
        $invoice = Invoice::query()
            ->where('type', InvoiceType::BuyerInvoice)
            ->where('user_id', $buyer->id)
            ->firstOrFail();

        $this->actingAs($buyer)
            ->get(route('invoices.download', $invoice))
            ->assertOk();
    }

    public function test_seller_invitation_accept_flow_and_reuse_blocked(): void
    {
        $admin = User::query()->where('email', 'seller.admin@rml.test')->firstOrFail();

        $this->actingAs($admin)
            ->post(route('seller.staff.invite.store'), [
                'email' => 'phase9.staff@rml.test',
            ])
            ->assertRedirect();

        $invitation = SellerStaffInvitation::query()
            ->where('email', 'phase9.staff@rml.test')
            ->firstOrFail();

        $this->assertSame(InvitationStatus::Pending, $invitation->status);

        $this->post('/logout');

        $this->get(route('seller.staff.invitations.accept', $invitation->token))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Auth/AcceptStaffInvitation')
                ->where('email', 'phase9.staff@rml.test'));

        $this->post(route('seller.staff.invitations.accept.store', $invitation->token), [
            'name' => 'Phase Nine Staff',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertRedirect(route('login'));

        $invitation->refresh();
        $this->assertSame(InvitationStatus::Accepted, $invitation->status);

        $staff = User::query()->where('email', 'phase9.staff@rml.test')->firstOrFail();
        $this->assertTrue($staff->hasRole(UserRole::SellerStaff->value));

        $this->get(route('seller.staff.invitations.accept', $invitation->token))
            ->assertRedirect(route('login'));
    }

    public function test_expired_invitation_cannot_be_accepted(): void
    {
        $admin = User::query()->where('email', 'seller.admin@rml.test')->firstOrFail();

        $this->actingAs($admin)
            ->post(route('seller.staff.invite.store'), [
                'email' => 'expired.staff@rml.test',
            ]);

        $invitation = SellerStaffInvitation::query()
            ->where('email', 'expired.staff@rml.test')
            ->firstOrFail();

        $invitation->update(['expires_at' => now()->subDay()]);

        $this->post('/logout');

        $this->get(route('seller.staff.invitations.accept', $invitation->token))
            ->assertRedirect(route('login'));
    }

    public function test_card_purchase_without_mollie_keeps_details_locked(): void
    {
        config([
            'payments.card_provider' => 'stripe',
            'services.stripe.secret' => '',
            'payments.stripe.secret' => '',
            'mollie.key' => '',
        ]);

        $buyer = User::query()->where('email', 'buyer@rml.test')->firstOrFail();
        $lead = Lead::query()->where('lead_reference', 'LD-1042')->firstOrFail();

        $this->actingAs($buyer)
            ->from(route('buyer.leads.index'))
            ->post(route('buyer.leads.purchase'), [
                'lead_ids' => [$lead->id],
                'payment_method' => PaymentMethod::Card->value,
            ])
            ->assertSessionHasErrors('payment_method');

        // Card checkout failure unlocks the lead again.
        $this->assertTrue(app(LeadAvailabilityService::class)->isAvailable($lead->fresh()));
        $this->assertNotSame(LeadStatus::Sold, $lead->fresh()->status);
    }

    public function test_seller_payments_page_does_not_expose_buyer_company(): void
    {
        $seller = User::query()->where('email', 'seller.admin@rml.test')->firstOrFail();

        $this->actingAs($seller)
            ->get(route('seller.payments'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->missing('commissions.data.0.buyer_company')
                ->missing('payouts.data.0.buyer_name')
                ->missing('payouts.data.0.buying_price'));
    }
}
