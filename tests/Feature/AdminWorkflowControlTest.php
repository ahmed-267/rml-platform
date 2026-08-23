<?php

namespace Tests\Feature;

use App\Enums\ApprovalStatus;
use App\Enums\CompanyType;
use App\Enums\LeadStatus;
use App\Enums\PackageStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\PurchaseStatus;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Lead;
use App\Models\LeadPackage;
use App\Models\Purchase;
use App\Models\Scheme;
use App\Models\User;
use App\Support\Permissions;
use Database\Seeders\DemoUserSeeder;
use Database\Seeders\DomainDemoSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SchemeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminWorkflowControlTest extends TestCase
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

    public function test_super_admin_can_create_internal_lead(): void
    {
        $admin = User::query()->where('email', 'admin@rml.test')->firstOrFail();
        $scheme = Scheme::query()->where('active', true)->firstOrFail();

        $this->actingAs($admin)
            ->post(route('admin.leads.store'), [
                'as_draft' => true,
                'lead_source' => 'rml_internal',
                'scheme_id' => $scheme->id,
                'customer_first_name' => 'Ana',
                'customer_last_name' => 'Garcia',
                'customer_phone' => '+34600111222',
                'customer_email' => 'ana.garcia@example.test',
                'address_line_1' => 'Calle Mayor 1',
                'city' => 'Madrid',
                'postcode' => '28013',
                'country' => 'ES',
                'property_type' => 'house',
                'size_m2' => 95,
                'buying_price' => 0,
            ])
            ->assertRedirect();

        $lead = Lead::query()->latest('id')->first();
        $this->assertNotNull($lead);
        $this->assertSame('rml_internal', $lead->lead_source);
        $this->assertSame(LeadStatus::Draft, $lead->status);
        $this->assertNull($lead->seller_company_id);
        $this->assertTrue(
            AuditLog::query()
                ->where('action', 'lead.created_by_admin')
                ->where('entity_id', $lead->id)
                ->exists(),
        );

        $this->actingAs($admin)
            ->get(route('admin.leads-bought.show', $lead))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/LeadsBought/Show')
                ->where('lead.status', LeadStatus::Draft->value));
    }

    public function test_package_create_filters_nearby_leads_for_installer(): void
    {
        $admin = User::query()->where('email', 'admin@rml.test')->firstOrFail();
        $buyer = User::query()->where('email', 'buyer@rml.test')->firstOrFail();
        $buyerCompany = $buyer->buyerProfile?->company;
        $this->assertNotNull($buyerCompany);

        $buyerCompany->forceFill([
            'latitude' => 40.4168,
            'longitude' => -3.7038,
            'geocoding_status' => \App\Enums\GeocodingStatus::Successful->value,
        ])->save();

        $near = Lead::factory()->listed()->create([
            'latitude' => 40.42,
            'longitude' => -3.70,
            'geocoding_status' => \App\Enums\GeocodingStatus::Successful->value,
            'selling_price' => 500,
            'buying_price' => 200,
        ]);
        $far = Lead::factory()->listed()->create([
            'latitude' => 41.38,
            'longitude' => 2.17,
            'geocoding_status' => \App\Enums\GeocodingStatus::Successful->value,
            'selling_price' => 400,
            'buying_price' => 160,
        ]);

        $response = $this->actingAs($admin)
            ->get(route('admin.packages.create', [
                'match_installer_id' => $buyerCompany->id,
                'radius_km' => 25,
            ]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Packages/Create')
                ->where('nearby.active', true)
                ->where('filters.match_installer_id', $buyerCompany->id));

        $page = $response->original->getData()['page']['props'] ?? null;
        if (is_array($page)) {
            $ids = collect($page['eligible_leads'] ?? [])->pluck('id')->all();
            $this->assertContains($near->id, $ids);
            $this->assertNotContains($far->id, $ids);
        } else {
            $response->assertInertia(fn ($inertia) => $inertia
                ->has('eligible_leads')
                ->where('eligible_leads', fn ($leads) => collect($leads)->contains('id', $near->id)
                    && ! collect($leads)->contains('id', $far->id)));
        }
    }

    public function test_admin_staff_without_create_permission_cannot_create_lead(): void
    {
        Role::findByName('admin_staff')->revokePermissionTo(Permissions::CREATE_ADMIN_LEADS);

        $staff = User::factory()->create();
        $staff->assignRole('admin_staff');

        $scheme = Scheme::query()->where('active', true)->firstOrFail();

        $this->actingAs($staff)
            ->post(route('admin.leads.store'), [
                'as_draft' => true,
                'lead_source' => 'rml_internal',
                'scheme_id' => $scheme->id,
                'customer_first_name' => 'Ana',
                'customer_last_name' => 'Garcia',
                'customer_phone' => '+34600111222',
                'customer_email' => 'ana.garcia@example.test',
                'address_line_1' => 'Calle Mayor 1',
                'city' => 'Madrid',
                'postcode' => '28013',
                'country' => 'ES',
                'property_type' => 'house',
            ])
            ->assertForbidden();
    }

    public function test_super_admin_can_open_package_create_page(): void
    {
        $admin = User::query()->where('email', 'admin@rml.test')->firstOrFail();

        $this->actingAs($admin)
            ->get(route('admin.packages.create'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Packages/Create')
                ->has('eligible_leads')
                ->has('installers'));
    }

    public function test_super_admin_can_sell_lead_via_manual_transfer(): void
    {
        $admin = User::query()->where('email', 'admin@rml.test')->firstOrFail();
        $buyer = User::query()->where('email', 'buyer@rml.test')->firstOrFail();
        $buyerCompany = $buyer->buyerProfile?->company;
        $this->assertNotNull($buyerCompany);

        $lead = Lead::factory()->listed()->create([
            'selling_price' => 750,
            'buying_price' => 300,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.sales.store'), [
                'type' => 'lead',
                'buyer_company_id' => $buyerCompany->id,
                'buyer_user_id' => $buyer->id,
                'lead_ids' => [$lead->id],
                'payment_method' => PaymentMethod::ManualBankTransfer->value,
            ])
            ->assertRedirect();

        $purchase = Purchase::query()->latest('id')->firstOrFail();
        $this->assertSame(PurchaseStatus::Pending, $purchase->status);
        $this->assertSame($buyerCompany->id, $purchase->buyer_company_id);
        $this->assertSame(PaymentStatus::Pending, $purchase->payment?->status);
        $this->assertSame(PaymentMethod::ManualBankTransfer, $purchase->payment?->method);
        $this->assertTrue(
            AuditLog::query()
                ->where('action', 'sale.created_by_admin')
                ->where('entity_id', $purchase->id)
                ->exists(),
        );

        $this->actingAs($buyer)
            ->get(route('buyer.purchases.show', $purchase))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('purchase.details_released', false));
    }

    public function test_super_admin_can_sell_package_via_manual_transfer(): void
    {
        $admin = User::query()->where('email', 'admin@rml.test')->firstOrFail();
        $buyer = User::query()->where('email', 'buyer@rml.test')->firstOrFail();
        $buyerCompany = $buyer->buyerProfile?->company;
        $this->assertNotNull($buyerCompany);

        $leadA = Lead::factory()->listed()->create([
            'latitude' => 40.42,
            'longitude' => -3.70,
            'selling_price' => 500,
            'buying_price' => 200,
            'geocoding_status' => \App\Enums\GeocodingStatus::Successful->value,
        ]);
        $leadB = Lead::factory()->listed()->create([
            'latitude' => 41.38,
            'longitude' => 2.17,
            'selling_price' => 400,
            'buying_price' => 160,
            'geocoding_status' => \App\Enums\GeocodingStatus::Successful->value,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.packages.store'), [
                'lead_ids' => [$leadA->id, $leadB->id],
                'name' => 'Admin Sell Package',
            ])
            ->assertRedirect(route('admin.leads.index', ['tab' => 'packages']));

        $package = LeadPackage::query()->latest('id')->firstOrFail();
        $this->assertSame(PackageStatus::Available, $package->status);

        $this->actingAs($admin)
            ->post(route('admin.sales.store'), [
                'type' => 'package',
                'buyer_company_id' => $buyerCompany->id,
                'buyer_user_id' => $buyer->id,
                'package_id' => $package->id,
                'payment_method' => PaymentMethod::ManualBankTransfer->value,
            ])
            ->assertRedirect();

        $purchase = Purchase::query()->latest('id')->firstOrFail();
        $this->assertSame(PurchaseStatus::Pending, $purchase->status);
        $this->assertTrue(
            AuditLog::query()->where('action', 'sale.created_by_admin')->exists(),
        );
    }

    public function test_super_admin_can_update_package_settings(): void
    {
        $admin = User::query()->where('email', 'admin@rml.test')->firstOrFail();
        Cache::forget('rml.package_settings');
        Cache::forget('rml.lead_settings');

        $this->actingAs($admin)
            ->put(route('admin.settings.leads-packages.update'), [
                'default_status' => 'draft',
                'allow_mixed_scheme' => false,
                'allow_without_buyer' => false,
                'reservation_lock' => true,
                'expiry_days' => 21,
                'min_leads' => 1,
                'max_leads' => 50,
                'allow_evidence_later' => true,
                'require_internal_audit' => true,
                'calculation_method' => 'per_m2',
                'minimum_selling_price' => 100,
                'payout_method' => 'per_m2',
                'payout_rate_per_m2' => 2.5,
                'lead_reservation_hours' => 12,
                'on_payment_fail' => 'release',
            ])
            ->assertRedirect(route('admin.settings.index', ['tab' => 'leads_packages']));

        $cached = Cache::get('rml.package_settings');
        $this->assertIsArray($cached);
        $this->assertSame('draft', $cached['default_status']);
        $this->assertFalse($cached['allow_mixed_scheme']);
        $this->assertFalse($cached['allow_without_buyer']);
        $this->assertSame(21, $cached['expiry_days']);
        $this->assertSame(50, $cached['max_leads']);
        $this->assertSame(2.5, Cache::get('rml.payout_settings')['rate_per_m2']);
        $this->assertTrue(
            AuditLog::query()->where('action', 'settings.package_updated')->exists(),
        );
        $this->assertTrue(
            AuditLog::query()->where('action', 'settings.payout_rules_updated')->exists(),
        );
    }

    public function test_super_admin_can_open_leads_packages_settings_tab(): void
    {
        $admin = User::query()->where('email', 'admin@rml.test')->firstOrFail();

        $this->actingAs($admin)
            ->get(route('admin.settings.index', ['tab' => 'leads_packages']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Settings/Index')
                ->where('tab', 'leads_packages')
                ->has('lead_settings')
                ->has('package_settings')
                ->has('pricing_settings')
                ->has('payout_settings')
                ->has('reservation_settings')
                ->has('scheme_requirements'));
    }

    public function test_leads_hub_exposes_create_and_sell_flags(): void
    {
        $admin = User::query()->where('email', 'admin@rml.test')->firstOrFail();

        $this->actingAs($admin)
            ->get(route('admin.leads.index', ['tab' => 'registered']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('can_create', true)
                ->where('can_sell', true));

        $this->actingAs($admin)
            ->get(route('admin.leads.index', ['tab' => 'packages']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('can_create', true)
                ->where('can_sell', true)
                ->has('packages'));
    }

    public function test_admin_staff_without_sell_permission_cannot_sell(): void
    {
        Role::findByName('admin_staff')->revokePermissionTo(Permissions::SELL_TO_BUYERS);

        $staff = User::factory()->create();
        $staff->assignRole('admin_staff');

        $buyer = Company::query()
            ->where('type', CompanyType::Buyer->value)
            ->where('approval_status', ApprovalStatus::Approved->value)
            ->firstOrFail();

        $lead = Lead::factory()->listed()->create([
            'selling_price' => 500,
            'buying_price' => 200,
        ]);

        $this->actingAs($staff)
            ->post(route('admin.sales.store'), [
                'type' => 'lead',
                'buyer_company_id' => $buyer->id,
                'lead_ids' => [$lead->id],
                'payment_method' => PaymentMethod::ManualBankTransfer->value,
            ])
            ->assertForbidden();
    }

    public function test_super_admin_can_open_create_lead_page(): void
    {
        $admin = User::query()->where('email', 'admin@rml.test')->firstOrFail();

        $this->actingAs($admin)
            ->get(route('admin.leads.create'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Leads/Create')
                ->has('schemes')
                ->has('seller_companies')
                ->has('seller_agents')
                ->has('sources')
                ->has('translations.admin.leads.create')
                ->has('translations.seller.leads'));
    }

    public function test_buy_lead_page_lists_approved_buyers_only(): void
    {
        $admin = User::query()->where('email', 'admin@rml.test')->firstOrFail();

        $approvedCount = Company::query()
            ->where('type', CompanyType::Buyer->value)
            ->where('approval_status', ApprovalStatus::Approved->value)
            ->count();

        $this->assertGreaterThan(0, $approvedCount);

        $pending = Company::factory()->create([
            'type' => CompanyType::Buyer->value,
            'approval_status' => ApprovalStatus::Pending->value,
            'name' => 'ZZ Pending Buyer For Buy Flow',
        ]);

        $seller = Company::query()
            ->where('type', CompanyType::Seller->value)
            ->value('id');

        $this->actingAs($admin)
            ->get(route('admin.sales.create', ['type' => 'lead']))
            ->assertOk()
            ->assertInertia(function ($page) use ($approvedCount, $pending, $seller) {
                $page->component('Admin/Sales/Create')
                    ->has('buyers', $approvedCount)
                    ->has('buyers.0.id')
                    ->has('buyers.0.name')
                    ->has('buyers.0.status')
                    ->where('buyers.0.status', ApprovalStatus::Approved->value)
                    ->has('payment_methods')
                    ->has('card_configured')
                    ->missing('buyer_users');

                $buyerIds = collect($page->toArray()['props']['buyers'] ?? [])->pluck('id');
                $this->assertFalse($buyerIds->contains($pending->id));
                if ($seller) {
                    $this->assertFalse($buyerIds->contains($seller));
                }
            });
    }

    public function test_sell_lead_back_href_targets_valid_admin_routes(): void
    {
        $admin = User::query()->where('email', 'admin@rml.test')->firstOrFail();
        $lead = Lead::factory()->listed()->create([
            'selling_price' => 500,
            'buying_price' => 200,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.sales.create', [
                'type' => 'lead',
                'return_to' => 'registered',
            ]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Sales/Create')
                ->where('back_href', route('admin.leads.index', ['tab' => 'registered']))
                ->where('preselect_invalid', false));

        $this->actingAs($admin)
            ->get(route('admin.sales.create', [
                'type' => 'lead',
                'lead_id' => $lead->id,
                'return_to' => 'lead',
            ]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('back_href', route('admin.leads-bought.show', $lead))
                ->where('preselected.lead_id', $lead->id));
    }

    public function test_unauthorised_user_cannot_open_create_or_sell_pages(): void
    {
        $seller = User::query()->where('email', 'seller.admin@rml.test')->firstOrFail();

        $this->actingAs($seller)
            ->get(route('admin.leads.create'))
            ->assertForbidden();

        $this->actingAs($seller)
            ->get(route('admin.sales.create', ['type' => 'lead']))
            ->assertForbidden();
    }
}
