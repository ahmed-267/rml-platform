<?php

namespace Tests\Feature;

use App\Enums\ApprovalStatus;
use App\Enums\LeadStatus;
use App\Enums\MessageThreadCategory;
use App\Enums\MessageThreadStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\PurchaseStatus;
use App\Models\Lead;
use App\Models\MessageThread;
use App\Models\Purchase;
use App\Models\User;
use Database\Seeders\DemoUserSeeder;
use Database\Seeders\DomainDemoSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SchemeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BuyerPortalTest extends TestCase
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
    }

    public function test_approved_buyer_can_access_dashboard(): void
    {
        $buyer = User::query()->where('email', 'buyer@rml.test')->firstOrFail();

        $this->actingAs($buyer)
            ->get(route('buyer.dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Buyer/Dashboard')
                ->has('kpis')
                ->has('recommended_leads'));
    }

    public function test_pending_and_suspended_buyer_cannot_access_dashboard(): void
    {
        $buyer = User::query()->where('email', 'buyer@rml.test')->firstOrFail();
        $buyer->update(['approval_status' => ApprovalStatus::Pending]);

        $this->actingAs($buyer->fresh())
            ->get(route('buyer.dashboard'))
            ->assertRedirect(route('pending-approval'));

        $buyer->update(['approval_status' => ApprovalStatus::Suspended]);

        $this->actingAs($buyer->fresh())
            ->get(route('buyer.dashboard'))
            ->assertRedirect(route('pending-approval'));
    }

    public function test_buyer_cannot_access_seller_admin_auditor_routes(): void
    {
        $buyer = User::query()->where('email', 'buyer@rml.test')->firstOrFail();

        $this->actingAs($buyer)->get(route('seller.dashboard'))->assertForbidden();
        $this->actingAs($buyer)->get(route('admin.dashboard'))->assertForbidden();
        $this->actingAs($buyer)->get(route('auditor.dashboard'))->assertForbidden();
    }

    public function test_marketplace_hides_customer_and_seller_and_sold_leads(): void
    {
        $buyer = User::query()->where('email', 'buyer@rml.test')->firstOrFail();

        $this->actingAs($buyer)
            ->get(route('buyer.leads.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Buyer/Leads/Index')
                ->has('leads.data')
                ->where('leads.data', function ($data) {
                    $refs = collect($data)->pluck('lead_reference');

                    return ! $refs->contains('LD-1043')
                        && collect($data)->every(fn ($lead) => ! array_key_exists('customer_first_name', $lead)
                            && ! array_key_exists('buying_price', $lead)
                            && ! array_key_exists('seller_company', $lead));
                }));
    }

    public function test_buyer_cannot_purchase_sold_lead(): void
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

    public function test_buyer_can_create_pending_purchase_without_releasing_details(): void
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
        $this->assertSame(PurchaseStatus::Pending, $purchase->status);
        $this->assertSame(PaymentStatus::Pending, $purchase->payment->status);
        $this->assertEqualsWithDelta((float) $lead->selling_price, (float) $purchase->total_amount, 0.01);

        $this->actingAs($buyer)
            ->get(route('buyer.purchases.show', $purchase))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('purchase.details_released', false)
                ->where('purchase.leads.0.details_released', false)
                ->missing('purchase.leads.0.customer_phone'));
    }

    public function test_paid_purchase_releases_customer_details(): void
    {
        $buyer = User::query()->where('email', 'buyer@rml.test')->firstOrFail();
        $purchase = Purchase::query()->where('purchase_reference', 'PUR-00041')->firstOrFail();

        $this->actingAs($buyer)
            ->get(route('buyer.purchases.show', $purchase))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('purchase.details_released', true)
                ->where('purchase.leads.0.details_released', true)
                ->has('purchase.leads.0.customer_first_name')
                ->missing('purchase.leads.0.buying_price')
                ->missing('purchase.leads.0.seller_company'));
    }

    public function test_pricing_uses_zone_times_size(): void
    {
        $buyer = User::query()->where('email', 'buyer@rml.test')->firstOrFail();
        $lead = Lead::query()->where('lead_reference', 'LD-1042')->firstOrFail();

        $this->assertSame(LeadStatus::Listed, $lead->status);
        $this->assertEqualsWithDelta(4.0, (float) $lead->selling_price / (float) $lead->size_m2, 0.01);

        $this->actingAs($buyer)
            ->post(route('buyer.leads.purchase'), [
                'lead_ids' => [$lead->id],
                'payment_method' => PaymentMethod::Card->value,
            ])
            ->assertRedirect();

        $purchase = Purchase::query()->latest('id')->firstOrFail();
        $this->assertEqualsWithDelta((float) $lead->selling_price, (float) $purchase->total_amount, 0.01);
    }

    public function test_pending_purchase_locks_lead_from_marketplace(): void
    {
        $buyer = User::query()->where('email', 'buyer@rml.test')->firstOrFail();

        $this->actingAs($buyer)
            ->get(route('buyer.leads.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('leads.data', function ($data) {
                    return ! collect($data)->pluck('lead_reference')->contains('LD-1041');
                }));
    }

    public function test_package_preview_uses_available_leads(): void
    {
        $buyer = User::query()->where('email', 'buyer@rml.test')->firstOrFail();

        $this->actingAs($buyer)
            ->get(route('buyer.packages.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Buyer/Packages/Index')
                ->has('mixed_zone')
                ->has('prebuilt'));
    }

    public function test_buyer_sees_own_payments_only(): void
    {
        $buyer = User::query()->where('email', 'buyer@rml.test')->firstOrFail();

        $this->actingAs($buyer)
            ->get(route('buyer.payments'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Buyer/Payments')
                ->has('payments')
                ->has('tab')
                ->has('tabCounts')
                ->has('filters'));
    }

    public function test_buyer_messages_scoped_to_own_threads(): void
    {
        $buyer = User::query()->where('email', 'buyer@rml.test')->firstOrFail();
        $seller = User::query()->where('email', 'seller.admin@rml.test')->firstOrFail();

        $this->actingAs($buyer)
            ->post(route('buyer.messages.store'), [
                'subject' => 'Payment question',
                'body' => 'When will payment clear?',
                'category' => MessageThreadCategory::PaymentQuery->value,
            ])
            ->assertRedirect();

        $other = MessageThread::query()->create([
            'thread_reference' => 'THR-88888',
            'subject' => 'Seller thread',
            'category' => MessageThreadCategory::SellerIssue,
            'status' => MessageThreadStatus::Open,
            'created_by_user_id' => $seller->id,
        ]);

        $this->actingAs($buyer)
            ->get(route('buyer.messages.show', $other))
            ->assertForbidden();
    }

    public function test_buyer_can_update_profile_but_not_approval_status(): void
    {
        $buyer = User::query()->where('email', 'buyer@rml.test')->firstOrFail();

        $this->actingAs($buyer)
            ->put(route('buyer.profile.update'), [
                'name' => 'Warm Homes Contact',
                'phone' => '+34600999888',
                'company_name' => 'Warm Homes Spain',
                'address' => 'Calle Nueva 10',
                'city' => 'Madrid',
                'postcode' => '28002',
                'country' => 'ES',
                'services_offered' => ['insulation', 'heat_pumps'],
                'preferred_zones' => ['D1', 'E1'],
                'max_distance_km' => 60,
                'approval_status' => 'approved',
            ])
            ->assertRedirect();

        $buyer->refresh();
        $this->assertSame('Warm Homes Contact', $buyer->name);
        $this->assertSame(ApprovalStatus::Approved, $buyer->approval_status);
        $this->assertSame(60.0, (float) $buyer->buyerProfile->max_distance_km);
        $this->assertSame('Calle Nueva 10', $buyer->buyerProfile->company->address);
    }
}
