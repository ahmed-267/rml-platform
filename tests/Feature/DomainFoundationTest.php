<?php

namespace Tests\Feature;

use App\Enums\CommissionAppliesTo;
use App\Enums\LeadStatus;
use App\Enums\PurchaseStatus;
use App\Models\Lead;
use App\Models\PricingRule;
use App\Models\Purchase;
use App\Models\Scheme;
use App\Models\User;
use App\Models\Zone;
use App\Services\AuditLogService;
use App\Services\CommissionService;
use App\Services\LeadPricingService;
use App\Support\Permissions;
use App\Support\ReferenceGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DomainFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_users_and_schemes_are_seeded(): void
    {
        $this->seed();

        $this->assertDatabaseHas('users', ['email' => 'seller.admin@rml.test']);
        $this->assertDatabaseHas('users', ['email' => 'buyer@rml.test']);
        $this->assertDatabaseHas('schemes', ['slug' => 'insulation']);
        $this->assertDatabaseHas('zones', ['code' => 'D1']);
        $this->assertDatabaseHas('leads', ['lead_reference' => 'LD-1041']);
        $this->assertDatabaseHas('companies', ['name' => 'Green Energy Spain']);
        $this->assertDatabaseHas('companies', ['name' => 'Warm Homes Spain']);
    }

    public function test_zone_pricing_matches_business_defaults(): void
    {
        $this->seed();

        $scheme = Scheme::query()->where('slug', 'insulation')->firstOrFail();
        $expected = ['D1' => 3.00, 'D2' => 4.00, 'E1' => 4.00, 'E2' => 5.00];

        foreach ($expected as $code => $price) {
            $zone = Zone::query()->where('scheme_id', $scheme->id)->where('code', $code)->firstOrFail();
            $rule = PricingRule::query()
                ->where('scheme_id', $scheme->id)
                ->where('zone_id', $zone->id)
                ->where('active', true)
                ->firstOrFail();

            $this->assertSame($price, (float) $rule->price_per_m2);
        }
    }

    public function test_lead_pricing_service_calculates_zone_times_size(): void
    {
        $this->seed();

        $lead = Lead::query()->where('lead_reference', 'LD-1041')->firstOrFail();
        $result = (new LeadPricingService)->calculate($lead);

        $this->assertSame(3.0, $result['price_per_m2']);
        $this->assertSame(95.0, $result['size_m2']);
        $this->assertSame(285.0, $result['selling_price']);
    }

    public function test_reference_generator_creates_unique_padded_references(): void
    {
        $this->seed();

        $leadRef = ReferenceGenerator::lead();
        $packageRef = ReferenceGenerator::package();
        $paymentRef = ReferenceGenerator::payment();

        $this->assertMatchesRegularExpression('/^LD-\d{4}$/', $leadRef);
        $this->assertMatchesRegularExpression('/^PKG-\d{3}$/', $packageRef);
        $this->assertMatchesRegularExpression('/^PAY-\d{5}$/', $paymentRef);
    }

    public function test_seller_cannot_view_buyer_info_and_buyer_customer_visibility_depends_on_payment(): void
    {
        $this->seed();

        $sellerAdmin = User::query()->where('email', 'seller.admin@rml.test')->firstOrFail();
        $buyer = User::query()->where('email', 'buyer@rml.test')->firstOrFail();
        $listedLead = Lead::query()->where('lead_reference', 'LD-1041')->firstOrFail();
        $soldLead = Lead::query()->where('lead_reference', 'LD-1043')->firstOrFail();

        $this->assertFalse($sellerAdmin->can('viewBuyerInfo', $listedLead));
        $this->assertFalse($buyer->can('viewSellerInfo', $listedLead));
        $this->assertFalse($buyer->can('viewCustomerDetails', $listedLead));
        $this->assertTrue($buyer->can('viewCustomerDetails', $soldLead));
        $this->assertTrue($sellerAdmin->can('view', $listedLead));
        $this->assertFalse($sellerAdmin->can('viewInternalPricing', $listedLead));
    }

    public function test_commission_becomes_due_only_after_sold_and_paid(): void
    {
        $this->seed();

        $service = new CommissionService;
        $soldLead = Lead::query()->where('lead_reference', 'LD-1043')->firstOrFail();
        $listedLead = Lead::query()->where('lead_reference', 'LD-1041')->firstOrFail();
        $paidPurchase = Purchase::query()->where('purchase_reference', 'PUR-00041')->firstOrFail();
        $pendingPurchase = Purchase::query()->where('purchase_reference', 'PUR-00042')->firstOrFail();

        $this->assertTrue($service->canBecomeDue($soldLead, $paidPurchase));
        $this->assertFalse($service->canBecomeDue($listedLead, $pendingPurchase));
        $this->assertSame(PurchaseStatus::Paid, $paidPurchase->status);
        $this->assertSame(LeadStatus::Sold, $soldLead->status);

        $calc = $service->calculate(420.00, 10.00);
        $this->assertSame(42.0, $calc['commission_amount']);
        $this->assertSame(CommissionAppliesTo::SellerStaff->value, CommissionAppliesTo::SellerStaff->value);
    }

    public function test_audit_log_service_writes_entries(): void
    {
        $this->seed();

        $admin = User::query()->where('email', 'admin@rml.test')->firstOrFail();
        $lead = Lead::query()->where('lead_reference', 'LD-1041')->firstOrFail();

        $log = (new AuditLogService)->log('test.action', $lead, ['a' => 1], ['a' => 2], $admin);

        $this->assertDatabaseHas('audit_logs', [
            'id' => $log->id,
            'action' => 'test.action',
            'user_id' => $admin->id,
        ]);

        $this->assertTrue($admin->can(Permissions::VIEW_AUDIT_LOGS));
        $this->assertTrue($admin->can('viewAny', $log));
    }
}
