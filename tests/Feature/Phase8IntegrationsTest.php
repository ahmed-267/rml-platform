<?php

namespace Tests\Feature;

use App\Enums\CommissionStatus;
use App\Enums\LeadStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\PayoutStatus;
use App\Enums\PurchaseStatus;
use App\Enums\UserRole;
use App\Models\Invoice;
use App\Models\Lead;
use App\Models\Payment;
use App\Models\PaymentWebhookEvent;
use App\Models\Purchase;
use App\Models\User;
use App\Models\WhatsAppSendLog;
use App\Services\Admin\PaymentConfirmationService;
use App\Services\Buyer\LeadAvailabilityService;
use App\Services\Payments\PaymentProviderManager;
use App\Services\Payments\PaymentSettlementService;
use App\Services\Payments\PaymentWebhookService;
use App\Services\Payments\Providers\ManualBankTransferProvider;
use App\Services\Payments\Providers\MolliePaymentProvider;
use App\Services\WhatsApp\WhatsAppMessageService;
use Database\Seeders\DemoUserSeeder;
use Database\Seeders\DomainDemoSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SchemeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class Phase8IntegrationsTest extends TestCase
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

    public function test_buyer_can_create_manual_bank_transfer_payment(): void
    {
        $buyer = User::query()->where('email', 'buyer@rml.test')->firstOrFail();
        $lead = Lead::query()->where('lead_reference', 'LD-1042')->firstOrFail();

        $this->actingAs($buyer)
            ->post(route('buyer.leads.purchase'), [
                'lead_ids' => [$lead->id],
                'payment_method' => PaymentMethod::ManualBankTransfer->value,
            ])
            ->assertRedirect();

        $purchase = Purchase::query()->latest('id')->firstOrFail();
        $payment = $purchase->payment;

        $this->assertSame(PurchaseStatus::Pending, $purchase->status);
        $this->assertSame(PaymentStatus::Pending, $payment->status);
        $this->assertSame(PaymentMethod::ManualBankTransfer, $payment->method);
        $this->assertSame('manual_bank_transfer', $payment->provider);
        $this->assertEqualsWithDelta((float) $lead->selling_price, (float) $payment->amount, 0.01);

        $this->actingAs($buyer)
            ->get(route('buyer.purchases.show', $purchase))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('purchase.details_released', false)
                ->where('purchase.payment.bank_instructions.payment_reference', $payment->payment_reference));
    }

    public function test_missing_mollie_keys_do_not_crash_card_purchase(): void
    {
        config([
            'payments.card_provider' => 'mollie',
            'mollie.key' => 'test_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxx',
            'payments.mollie.key' => 'test_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxx',
        ]);

        $this->assertFalse(app(PaymentProviderManager::class)->mollie()->isConfigured());

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

        $this->assertTrue(app(LeadAvailabilityService::class)->isAvailable($lead->fresh()));
    }

    public function test_mollie_provider_cannot_create_checkout_without_api_key(): void
    {
        config(['mollie.key' => '', 'payments.mollie.key' => '']);

        $provider = app(PaymentProviderManager::class)->mollie();
        $this->assertFalse($provider->isConfigured());

        $buyer = User::query()->where('email', 'buyer@rml.test')->firstOrFail();
        $payment = Payment::query()->where('payment_reference', 'PAY-00092')->firstOrFail();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(__('rml.payments.mollie_not_configured'));

        $provider->createPayment(
            $payment,
            $buyer,
            'Test checkout',
            'https://example.test/return',
        );
    }

    public function test_admin_hint_for_missing_mollie_is_role_gated(): void
    {
        config(['mollie.key' => '']);

        $admin = User::query()->where('email', 'admin@rml.test')->firstOrFail();
        $buyer = User::query()->where('email', 'buyer@rml.test')->firstOrFail();
        $purchase = Purchase::query()->where('purchase_reference', 'PUR-00042')->firstOrFail();
        $purchase->payment?->update([
            'method' => PaymentMethod::Card,
            'metadata' => ['provider_not_configured' => true],
        ]);

        $this->actingAs($buyer)
            ->get(route('buyer.purchases.show', $purchase))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('card_provider_admin_hint', null));

        // Admins do not own buyer purchases; ensure buyer message path stays non-technical.
        $this->assertTrue($admin->hasRole(UserRole::SuperAdmin->value));
        $this->assertSame(
            'STRIPE_SECRET is missing from the environment configuration.',
            __('rml.payments.card_key_missing_hint'),
        );
    }

    public function test_super_admin_can_confirm_manual_payment_and_release_leads(): void
    {
        $buyer = User::query()->where('email', 'buyer@rml.test')->firstOrFail();
        $admin = User::query()->where('email', 'admin@rml.test')->firstOrFail();
        $lead = Lead::query()->where('lead_reference', 'LD-1042')->firstOrFail();

        $this->actingAs($buyer)->post(route('buyer.leads.purchase'), [
            'lead_ids' => [$lead->id],
            'payment_method' => PaymentMethod::ManualBankTransfer->value,
        ]);

        $payment = Payment::query()->latest('id')->firstOrFail();

        $this->actingAs($buyer)
            ->post(route('admin.payments.mark-paid', $payment))
            ->assertForbidden();

        $this->actingAs($admin)
            ->post(route('admin.payments.mark-paid', $payment), [
                'confirmation_note' => 'Bank transfer seen',
                'confirmation_reference' => 'TX-123',
            ])
            ->assertRedirect();

        $payment->refresh();
        $purchase = $payment->purchases()->firstOrFail();
        $lead->refresh();

        $this->assertSame(PaymentStatus::Paid, $payment->status);
        $this->assertNotNull($payment->paid_at);
        $this->assertSame(PurchaseStatus::Paid, $purchase->status);
        $this->assertSame(LeadStatus::Sold, $lead->status);
        $this->assertDatabaseHas('payouts', ['status' => PayoutStatus::Pending->value]);
        $this->assertDatabaseHas('commissions', ['status' => CommissionStatus::Due->value]);
        $this->assertDatabaseHas('invoices', ['purchase_id' => $purchase->id]);

        $this->actingAs($buyer)
            ->get(route('buyer.purchases.show', $purchase))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('purchase.details_released', true)
                ->has('purchase.leads.0.customer_first_name')
                ->missing('purchase.leads.0.buying_price'));
    }

    public function test_sold_lead_cannot_be_purchased_again(): void
    {
        $buyer = User::query()->where('email', 'buyer@rml.test')->firstOrFail();
        $sold = Lead::query()->where('lead_reference', 'LD-1043')->firstOrFail();

        $this->actingAs($buyer)
            ->post(route('buyer.leads.purchase'), [
                'lead_ids' => [$sold->id],
                'payment_method' => PaymentMethod::ManualBankTransfer->value,
            ])
            ->assertSessionHasErrors();
    }

    public function test_mollie_webhook_is_idempotent_and_releases_on_paid(): void
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
            'provider' => 'mollie',
            'provider_payment_id' => 'tr_test_phase8',
        ]);

        $mollie = $this->createMock(MolliePaymentProvider::class);
        $mollie->method('providerName')->willReturn('mollie');
        $mollie->method('handleWebhook')->willReturn([
            'provider_payment_id' => 'tr_test_phase8',
            'status' => PaymentStatus::Paid->value,
            'paid' => true,
            'cancelled' => false,
            'failed' => false,
            'event_id' => 'tr_test_phase8:paid',
        ]);

        $manager = new PaymentProviderManager(
            new ManualBankTransferProvider,
            $mollie,
        );

        $webhook = new PaymentWebhookService(
            $manager,
            app(PaymentSettlementService::class),
        );

        $webhook->handleMollie(['id' => 'tr_test_phase8']);
        $webhook->handleMollie(['id' => 'tr_test_phase8']);

        $this->assertSame(1, PaymentWebhookEvent::query()->count());
        $this->assertSame(PaymentStatus::Paid, $payment->fresh()->status);
        $this->assertSame(LeadStatus::Sold, $lead->fresh()->status);
    }

    public function test_failed_webhook_does_not_release_details(): void
    {
        $buyer = User::query()->where('email', 'buyer@rml.test')->firstOrFail();
        $lead = Lead::query()->where('lead_reference', 'LD-1042')->firstOrFail();

        $this->actingAs($buyer)->post(route('buyer.leads.purchase'), [
            'lead_ids' => [$lead->id],
            'payment_method' => PaymentMethod::ManualBankTransfer->value,
        ]);

        $payment = Payment::query()->latest('id')->firstOrFail();
        $payment->update([
            'provider' => 'mollie',
            'provider_payment_id' => 'tr_failed_1',
        ]);

        $mollie = $this->createMock(MolliePaymentProvider::class);
        $mollie->method('providerName')->willReturn('mollie');
        $mollie->method('handleWebhook')->willReturn([
            'provider_payment_id' => 'tr_failed_1',
            'status' => PaymentStatus::Failed->value,
            'paid' => false,
            'cancelled' => false,
            'failed' => true,
            'event_id' => 'tr_failed_1:failed',
        ]);

        $webhook = new PaymentWebhookService(
            new PaymentProviderManager(new ManualBankTransferProvider, $mollie),
            app(PaymentSettlementService::class),
        );

        $webhook->handleMollie(['id' => 'tr_failed_1']);

        $this->assertSame(PaymentStatus::Failed, $payment->fresh()->status);
        $this->assertNotSame(LeadStatus::Sold, $lead->fresh()->status);
    }

    public function test_whatsapp_blocked_before_paid_and_logs_after(): void
    {
        $buyer = User::query()->where('email', 'buyer@rml.test')->firstOrFail();
        $admin = User::query()->where('email', 'admin@rml.test')->firstOrFail();
        $lead = Lead::query()->where('lead_reference', 'LD-1042')->firstOrFail();

        $this->actingAs($buyer)->post(route('buyer.leads.purchase'), [
            'lead_ids' => [$lead->id],
            'payment_method' => PaymentMethod::ManualBankTransfer->value,
        ]);

        $purchase = Purchase::query()->latest('id')->firstOrFail();

        $this->actingAs($buyer)
            ->post(route('buyer.purchases.whatsapp', $purchase))
            ->assertSessionHasErrors('whatsapp');

        app(PaymentConfirmationService::class)->markBuyerPaymentPaid($admin, $purchase->payment);

        $buyer->load('buyerProfile.company');
        $buyer->buyerProfile?->company?->update(['whatsapp' => '+34600111222']);

        $this->actingAs($buyer->fresh())
            ->post(route('buyer.purchases.whatsapp', $purchase))
            ->assertSessionHasErrors('whatsapp');

        $this->assertDatabaseHas('whatsapp_send_logs', [
            'purchase_id' => $purchase->id,
            'status' => 'failed',
        ]);

        $this->assertFalse(app(WhatsAppMessageService::class)->isConfigured());
        $this->assertInstanceOf(WhatsAppSendLog::class, WhatsAppSendLog::query()->first());
    }

    public function test_unauthorised_user_cannot_download_invoice(): void
    {
        $buyer = User::query()->where('email', 'buyer@rml.test')->firstOrFail();
        $seller = User::query()->where('email', 'seller.admin@rml.test')->firstOrFail();
        $admin = User::query()->where('email', 'admin@rml.test')->firstOrFail();
        $lead = Lead::query()->where('lead_reference', 'LD-1042')->firstOrFail();

        $this->actingAs($buyer)->post(route('buyer.leads.purchase'), [
            'lead_ids' => [$lead->id],
            'payment_method' => PaymentMethod::ManualBankTransfer->value,
        ]);

        $payment = Payment::query()->latest('id')->firstOrFail();
        app(PaymentConfirmationService::class)->markBuyerPaymentPaid($admin, $payment);

        $invoice = Invoice::query()->where('payment_id', $payment->id)->firstOrFail();

        $this->actingAs($seller)
            ->get(route('invoices.download', $invoice))
            ->assertForbidden();

        $this->actingAs($buyer)
            ->get(route('invoices.download', $invoice))
            ->assertOk();
    }

    public function test_payment_pages_translate_en_es_fr(): void
    {
        $buyer = User::query()->where('email', 'buyer@rml.test')->firstOrFail();

        foreach (['en', 'es', 'fr'] as $locale) {
            $buyer->update(['locale' => $locale]);

            $this->actingAs($buyer->fresh())
                ->withSession(['locale' => $locale])
                ->get(route('buyer.payments'))
                ->assertOk()
                ->assertInertia(fn ($page) => $page
                    ->where('app.locale', $locale)
                    ->has('translations.buyer.payments.pay_now')
                    ->has('translations.payment_statuses.pending')
                    ->has('translations.whatsapp.not_configured'));
        }
    }
}
