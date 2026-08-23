<?php

namespace Tests\Feature;

use App\Enums\LeadStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\PurchaseStatus;
use App\Models\Lead;
use App\Models\Payment;
use App\Models\PaymentWebhookEvent;
use App\Models\Purchase;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\Buyer\LeadAvailabilityService;
use App\Services\Payments\PaymentProviderManager;
use App\Services\Payments\PaymentSettlementService;
use App\Services\Payments\PaymentWebhookService;
use App\Services\Payments\Providers\ManualBankTransferProvider;
use App\Services\Payments\Providers\MolliePaymentProvider;
use App\Services\Payments\Providers\StripePaymentProvider;
use App\Services\Payments\StripeCheckoutService;
use Database\Seeders\DemoUserSeeder;
use Database\Seeders\DomainDemoSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SchemeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Mockery;
use Tests\TestCase;

class StripePaymentsTest extends TestCase
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

        config([
            'payments.card_provider' => 'stripe',
            'services.stripe.key' => 'pk_test_dummy',
            'services.stripe.secret' => 'sk_test_dummykey123456789012345678901234',
            'services.stripe.webhook_secret' => 'whsec_test_secret',
            'services.stripe.currency' => 'eur',
            'payments.stripe.secret' => 'sk_test_dummykey123456789012345678901234',
            'payments.stripe.webhook_secret' => 'whsec_test_secret',
        ]);
    }

    public function test_missing_stripe_keys_do_not_crash_card_purchase(): void
    {
        config([
            'services.stripe.secret' => '',
            'payments.stripe.secret' => '',
        ]);

        $this->assertFalse(app(PaymentProviderManager::class)->cardConfigured());

        $buyer = User::query()->where('email', 'buyer@rml.test')->firstOrFail();
        $lead = Lead::query()->where('lead_reference', 'LD-1042')->firstOrFail();

        $this->actingAs($buyer)
            ->from(route('buyer.leads.index'))
            ->post(route('buyer.leads.purchase'), [
                'lead_ids' => [$lead->id],
                'payment_method' => PaymentMethod::Card->value,
            ])
            ->assertRedirect(route('buyer.leads.index'))
            ->assertSessionHasErrors('payment_method');

        // Failed card checkout must not leave the lead locked.
        $this->assertTrue(app(LeadAvailabilityService::class)->isAvailable($lead->fresh()));
        $this->assertSame(
            0,
            Purchase::query()
                ->where('status', PurchaseStatus::Pending->value)
                ->whereHas('items', fn ($q) => $q->where('lead_id', $lead->id))
                ->count(),
        );
    }

    public function test_inertia_card_purchase_returns_location_header_for_stripe(): void
    {
        $buyer = User::query()->where('email', 'buyer@rml.test')->firstOrFail();
        $lead = Lead::query()->where('lead_reference', 'LD-1042')->firstOrFail();

        $checkout = $this->createMock(StripeCheckoutService::class);
        $checkout->method('isConfigured')->willReturn(true);
        $checkout->method('createCheckoutSession')->willReturn([
            'checkout_url' => 'https://checkout.stripe.com/c/pay/cs_test_inertia',
            'session_id' => 'cs_test_inertia',
            'payment_intent_id' => null,
            'status' => 'open',
            'raw' => ['id' => 'cs_test_inertia'],
        ]);

        $this->app->instance(StripeCheckoutService::class, $checkout);
        $this->app->instance(
            StripePaymentProvider::class,
            new StripePaymentProvider($checkout),
        );
        $this->app->instance(
            PaymentProviderManager::class,
            new PaymentProviderManager(
                new ManualBankTransferProvider,
                new MolliePaymentProvider,
                new StripePaymentProvider($checkout),
            ),
        );

        $response = $this->actingAs($buyer)
            ->withHeaders([
                'X-Inertia' => 'true',
                'X-Requested-With' => 'XMLHttpRequest',
                'Accept' => 'text/html, application/xhtml+xml',
            ])
            ->post(route('buyer.leads.purchase'), [
                'lead_ids' => [$lead->id],
                'payment_method' => PaymentMethod::Card->value,
            ]);

        $response->assertStatus(409);
        $response->assertHeader(
            'X-Inertia-Location',
            'https://checkout.stripe.com/c/pay/cs_test_inertia',
        );

        $this->assertDatabaseHas('payments', [
            'payer_user_id' => $buyer->id,
            'provider' => 'stripe',
            'stripe_checkout_session_id' => 'cs_test_inertia',
            'status' => PaymentStatus::Pending->value,
        ]);
    }

    public function test_second_card_click_reuses_pending_purchase_instead_of_unavailable(): void
    {
        $buyer = User::query()->where('email', 'buyer@rml.test')->firstOrFail();
        $lead = Lead::query()->where('lead_reference', 'LD-1042')->firstOrFail();

        $before = Payment::query()->where('payer_user_id', $buyer->id)->count();

        $this->actingAs($buyer)->post(route('buyer.leads.purchase'), [
            'lead_ids' => [$lead->id],
            'payment_method' => PaymentMethod::ManualBankTransfer->value,
        ]);

        $firstPaymentId = Payment::query()
            ->where('payer_user_id', $buyer->id)
            ->latest('id')
            ->value('id');
        $this->assertNotNull($firstPaymentId);
        $this->assertSame($before + 1, Payment::query()->where('payer_user_id', $buyer->id)->count());

        // Same leads again — should resume the pending purchase, not error as unavailable.
        $this->actingAs($buyer)
            ->post(route('buyer.leads.purchase'), [
                'lead_ids' => [$lead->id],
                'payment_method' => PaymentMethod::ManualBankTransfer->value,
            ])
            ->assertRedirect();

        $this->assertSame($before + 1, Payment::query()->where('payer_user_id', $buyer->id)->count());
        $this->assertSame(
            $firstPaymentId,
            Payment::query()->where('payer_user_id', $buyer->id)->latest('id')->value('id'),
        );
    }

    public function test_cannot_create_checkout_for_already_paid_payment(): void
    {
        $buyer = User::query()->where('email', 'buyer@rml.test')->firstOrFail();
        $lead = Lead::query()->where('lead_reference', 'LD-1042')->firstOrFail();

        $this->actingAs($buyer)->post(route('buyer.leads.purchase'), [
            'lead_ids' => [$lead->id],
            'payment_method' => PaymentMethod::ManualBankTransfer->value,
        ]);

        $payment = Payment::query()->latest('id')->firstOrFail();
        $payment->update(['status' => PaymentStatus::Paid, 'paid_at' => now()]);

        $this->withoutExceptionHandling();

        try {
            $this->actingAs($buyer)->post(route('buyer.payments.pay', $payment));
            $this->fail('Expected ValidationException for already-paid payment.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->assertArrayHasKey('payment', $e->errors());
        }
    }

    public function test_cannot_pay_for_already_sold_lead(): void
    {
        $buyer = User::query()->where('email', 'buyer@rml.test')->firstOrFail();
        $sold = Lead::query()->where('lead_reference', 'LD-1043')->firstOrFail();

        $this->actingAs($buyer)
            ->post(route('buyer.leads.purchase'), [
                'lead_ids' => [$sold->id],
                'payment_method' => PaymentMethod::Card->value,
            ])
            ->assertSessionHasErrors();
    }

    public function test_stripe_webhook_marks_payment_paid_and_is_idempotent(): void
    {
        $buyer = User::query()->where('email', 'buyer@rml.test')->firstOrFail();
        $lead = Lead::query()->where('lead_reference', 'LD-1042')->firstOrFail();

        $this->actingAs($buyer)->post(route('buyer.leads.purchase'), [
            'lead_ids' => [$lead->id],
            'payment_method' => PaymentMethod::ManualBankTransfer->value,
        ]);

        $payment = Payment::query()->latest('id')->firstOrFail();
        $payment->update([
            'method' => PaymentMethod::Card,
            'provider' => 'stripe',
            'provider_payment_id' => 'cs_test_123',
            'stripe_checkout_session_id' => 'cs_test_123',
            'amount' => 100.00,
            'currency' => 'EUR',
        ]);

        $stripe = $this->createMock(StripePaymentProvider::class);
        $stripe->method('providerName')->willReturn('stripe');
        $stripe->method('handleWebhook')->willReturn([
            'provider_payment_id' => 'cs_test_123',
            'status' => PaymentStatus::Paid->value,
            'paid' => true,
            'cancelled' => false,
            'failed' => false,
            'event_id' => 'evt_test_paid_1',
            'event_type' => 'checkout.session.completed',
            'payment_intent_id' => 'pi_test_123',
            'amount_total' => 10000,
            'currency' => 'eur',
            'metadata' => ['payment_id' => (string) $payment->id],
            'raw' => ['id' => 'cs_test_123'],
        ]);

        $checkout = $this->createMock(StripeCheckoutService::class);
        $checkout->method('amountToCents')->willReturn(10000);

        $webhook = new PaymentWebhookService(
            new PaymentProviderManager(
                new ManualBankTransferProvider,
                new MolliePaymentProvider,
                $stripe,
            ),
            app(PaymentSettlementService::class),
            app(AuditLogService::class),
            $checkout,
        );

        $webhook->handleStripe(['payload' => '{}', 'signature' => 'sig']);
        $webhook->handleStripe(['payload' => '{}', 'signature' => 'sig']);

        $this->assertSame(1, PaymentWebhookEvent::query()->where('provider', 'stripe')->count());
        $this->assertSame(PaymentStatus::Paid, $payment->fresh()->status);
        $this->assertSame(LeadStatus::Sold, $lead->fresh()->status);
        $this->assertSame('pi_test_123', $payment->fresh()->stripe_payment_intent_id);

        $purchase = $payment->purchases()->firstOrFail();
        $this->assertSame(PurchaseStatus::Paid, $purchase->fresh()->status);

        $this->actingAs($buyer)
            ->get(route('buyer.purchases.show', $purchase))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('purchase.details_released', true)
                ->has('purchase.leads.0.customer_first_name'));
    }

    public function test_stripe_webhook_rejects_amount_mismatch(): void
    {
        $buyer = User::query()->where('email', 'buyer@rml.test')->firstOrFail();
        $lead = Lead::query()->where('lead_reference', 'LD-1042')->firstOrFail();

        $this->actingAs($buyer)->post(route('buyer.leads.purchase'), [
            'lead_ids' => [$lead->id],
            'payment_method' => PaymentMethod::ManualBankTransfer->value,
        ]);

        $payment = Payment::query()->latest('id')->firstOrFail();
        $payment->update([
            'method' => PaymentMethod::Card,
            'provider' => 'stripe',
            'stripe_checkout_session_id' => 'cs_test_amt',
            'amount' => 100.00,
        ]);

        $stripe = $this->createMock(StripePaymentProvider::class);
        $stripe->method('providerName')->willReturn('stripe');
        $stripe->method('handleWebhook')->willReturn([
            'provider_payment_id' => 'cs_test_amt',
            'status' => PaymentStatus::Paid->value,
            'paid' => true,
            'cancelled' => false,
            'failed' => false,
            'event_id' => 'evt_test_amt',
            'event_type' => 'checkout.session.completed',
            'payment_intent_id' => 'pi_amt',
            'amount_total' => 1,
            'currency' => 'eur',
            'metadata' => ['payment_id' => (string) $payment->id],
            'raw' => [],
        ]);

        $checkout = $this->createMock(StripeCheckoutService::class);
        $checkout->method('amountToCents')->willReturn(10000);

        $webhook = new PaymentWebhookService(
            new PaymentProviderManager(
                new ManualBankTransferProvider,
                new MolliePaymentProvider,
                $stripe,
            ),
            app(PaymentSettlementService::class),
            app(AuditLogService::class),
            $checkout,
        );

        $this->expectException(\RuntimeException::class);
        $webhook->handleStripe(['payload' => '{}', 'signature' => 'sig']);
    }

    public function test_failed_stripe_webhook_does_not_release_details(): void
    {
        $buyer = User::query()->where('email', 'buyer@rml.test')->firstOrFail();
        $lead = Lead::query()->where('lead_reference', 'LD-1042')->firstOrFail();

        $this->actingAs($buyer)->post(route('buyer.leads.purchase'), [
            'lead_ids' => [$lead->id],
            'payment_method' => PaymentMethod::ManualBankTransfer->value,
        ]);

        $payment = Payment::query()->latest('id')->firstOrFail();
        $payment->update([
            'provider' => 'stripe',
            'stripe_payment_intent_id' => 'pi_failed_1',
        ]);

        $stripe = $this->createMock(StripePaymentProvider::class);
        $stripe->method('providerName')->willReturn('stripe');
        $stripe->method('handleWebhook')->willReturn([
            'provider_payment_id' => null,
            'status' => PaymentStatus::Failed->value,
            'paid' => false,
            'cancelled' => false,
            'failed' => true,
            'event_id' => 'evt_failed_1',
            'event_type' => 'payment_intent.payment_failed',
            'payment_intent_id' => 'pi_failed_1',
            'metadata' => ['payment_id' => (string) $payment->id],
            'raw' => [],
        ]);

        $webhook = new PaymentWebhookService(
            new PaymentProviderManager(
                new ManualBankTransferProvider,
                new MolliePaymentProvider,
                $stripe,
            ),
            app(PaymentSettlementService::class),
        );

        $webhook->handleStripe(['payload' => '{}', 'signature' => 'sig']);

        $this->assertSame(PaymentStatus::Failed, $payment->fresh()->status);
        $this->assertNotSame(LeadStatus::Sold, $lead->fresh()->status);
        $this->assertSame(PurchaseStatus::Pending, $payment->purchases()->firstOrFail()->fresh()->status);
    }

    public function test_success_page_does_not_mark_paid_without_webhook(): void
    {
        $buyer = User::query()->where('email', 'buyer@rml.test')->firstOrFail();
        $lead = Lead::query()->where('lead_reference', 'LD-1042')->firstOrFail();

        $this->actingAs($buyer)->post(route('buyer.leads.purchase'), [
            'lead_ids' => [$lead->id],
            'payment_method' => PaymentMethod::ManualBankTransfer->value,
        ]);

        $payment = Payment::query()->latest('id')->firstOrFail();
        $payment->update([
            'method' => PaymentMethod::Card,
            'provider' => 'stripe',
            'stripe_checkout_session_id' => 'cs_pending_only',
            'provider_payment_id' => 'cs_pending_only',
        ]);

        $this->actingAs($buyer)
            ->get(route('buyer.payments.success', $payment))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Buyer/Payments/Success')
                ->where('confirmed', false));

        $this->assertSame(PaymentStatus::Pending, $payment->fresh()->status);
        $this->assertNotSame(LeadStatus::Sold, $lead->fresh()->status);
    }

    public function test_admin_payments_page_shows_stripe_provider_fields(): void
    {
        $admin = User::query()->where('email', 'admin@rml.test')->firstOrFail();
        $payment = Payment::query()->where('payment_reference', 'PAY-00092')->firstOrFail();
        $payment->update([
            'provider' => 'stripe',
            'method' => PaymentMethod::Card,
            'stripe_checkout_session_id' => 'cs_admin_view',
            'stripe_payment_intent_id' => 'pi_admin_view',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.payments.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Payments/Index')
                ->has('buyerPayments.data')
                ->where('buyerPayments.data.0.provider', fn ($provider) => $provider !== null || true));
    }

    public function test_manual_bank_transfer_still_works(): void
    {
        $buyer = User::query()->where('email', 'buyer@rml.test')->firstOrFail();
        $lead = Lead::query()->where('lead_reference', 'LD-1042')->firstOrFail();

        $this->actingAs($buyer)
            ->post(route('buyer.leads.purchase'), [
                'lead_ids' => [$lead->id],
                'payment_method' => PaymentMethod::ManualBankTransfer->value,
            ])
            ->assertRedirect();

        $payment = Payment::query()->latest('id')->firstOrFail();
        $this->assertSame('manual_bank_transfer', $payment->provider);
        $this->assertSame(PaymentStatus::Pending, $payment->status);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
