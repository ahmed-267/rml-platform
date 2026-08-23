<?php

namespace Tests\Feature;

use App\Enums\ApprovalStatus;
use App\Enums\CompanyType;
use App\Enums\GeocodingStatus;
use App\Enums\LeadStatus;
use App\Enums\PackageStatus;
use App\Models\Company;
use App\Models\Lead;
use App\Models\LeadPackage;
use App\Models\User;
use Database\Seeders\DemoUserSeeder;
use Database\Seeders\DomainDemoSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SchemeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminPackageBuilderTest extends TestCase
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

    public function test_admin_can_create_package_from_nearby_eligible_leads(): void
    {
        $admin = User::query()->where('email', 'admin@rml.test')->firstOrFail();

        $installer = Company::query()
            ->where('type', CompanyType::Buyer->value)
            ->where('approval_status', ApprovalStatus::Approved->value)
            ->firstOrFail();
        $installer->forceFill([
            'latitude' => 40.4168,
            'longitude' => -3.7038,
            'city' => 'Madrid',
            'geocoding_status' => GeocodingStatus::Successful->value,
        ])->save();

        $leadA = Lead::factory()->listed()->create([
            'latitude' => 40.42,
            'longitude' => -3.70,
            'size_m2' => 100,
            'selling_price' => 500,
            'buying_price' => 200,
            'geocoding_status' => GeocodingStatus::Successful->value,
        ]);
        $leadB = Lead::factory()->listed()->create([
            'latitude' => 40.43,
            'longitude' => -3.71,
            'size_m2' => 80,
            'selling_price' => 400,
            'buying_price' => 160,
            'geocoding_status' => GeocodingStatus::Successful->value,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.packages.store'), [
                'match_installer_id' => $installer->id,
                'lead_ids' => [$leadA->id, $leadB->id],
                'radius_km' => 50,
                'name' => 'Madrid Insulation Leads Package',
            ])
            ->assertRedirect(route('admin.leads.index', ['tab' => 'packages']));

        $package = LeadPackage::query()->latest('id')->first();
        $this->assertNotNull($package);

        $this->assertSame(PackageStatus::Available, $package->status);
        $this->assertSame($installer->id, $package->buyer_company_id);
        $this->assertSame('Madrid Insulation Leads Package', $package->name);
        $this->assertEqualsCanonicalizing(
            [$leadA->id, $leadB->id],
            $package->leads()->pluck('leads.id')->all(),
        );
        $this->assertEquals(900.0, (float) $package->estimated_total);
    }

    public function test_packaged_leads_cannot_be_added_to_another_active_package(): void
    {
        $admin = User::query()->where('email', 'admin@rml.test')->firstOrFail();

        $installer = Company::query()
            ->where('type', CompanyType::Buyer->value)
            ->where('approval_status', ApprovalStatus::Approved->value)
            ->firstOrFail();
        $installer->forceFill([
            'latitude' => 40.4168,
            'longitude' => -3.7038,
            'geocoding_status' => GeocodingStatus::Successful->value,
        ])->save();

        $lead = Lead::factory()->listed()->create([
            'latitude' => 40.42,
            'longitude' => -3.70,
            'geocoding_status' => GeocodingStatus::Successful->value,
        ]);

        $this->actingAs($admin)->post(route('admin.packages.store'), [
            'match_installer_id' => $installer->id,
            'lead_ids' => [$lead->id],
            'radius_km' => 50,
        ])->assertRedirect();

        $this->actingAs($admin)
            ->post(route('admin.packages.store'), [
                'match_installer_id' => $installer->id,
                'lead_ids' => [$lead->id],
                'radius_km' => 50,
            ])
            ->assertSessionHasErrors('lead_ids');
    }

    public function test_pending_review_leads_cannot_be_packaged(): void
    {
        $admin = User::query()->where('email', 'admin@rml.test')->firstOrFail();

        $installer = Company::query()
            ->where('type', CompanyType::Buyer->value)
            ->where('approval_status', ApprovalStatus::Approved->value)
            ->firstOrFail();
        $installer->forceFill([
            'latitude' => 40.4168,
            'longitude' => -3.7038,
            'geocoding_status' => GeocodingStatus::Successful->value,
        ])->save();

        $lead = Lead::factory()->create([
            'status' => LeadStatus::NeedsMoreInformation,
            'latitude' => 40.42,
            'longitude' => -3.70,
            'geocoding_status' => GeocodingStatus::Successful->value,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.packages.store'), [
                'match_installer_id' => $installer->id,
                'lead_ids' => [$lead->id],
                'radius_km' => 50,
            ])
            ->assertSessionHasErrors('lead_ids');
    }

    public function test_buyer_sees_assigned_package_without_customer_pii(): void
    {
        $admin = User::query()->where('email', 'admin@rml.test')->firstOrFail();
        $buyer = User::query()->where('email', 'buyer@rml.test')->firstOrFail();
        $buyer->load('buyerProfile.company');

        $installer = $buyer->buyerProfile?->company;
        $this->assertNotNull($installer);
        $installer->forceFill([
            'latitude' => 40.4168,
            'longitude' => -3.7038,
            'approval_status' => ApprovalStatus::Approved->value,
            'geocoding_status' => GeocodingStatus::Successful->value,
        ])->save();

        $lead = Lead::factory()->listed()->create([
            'latitude' => 40.42,
            'longitude' => -3.70,
            'customer_first_name' => 'Secret',
            'customer_last_name' => 'Customer',
            'address_line_1' => 'Hidden Street 1',
            'selling_price' => 450,
            'geocoding_status' => GeocodingStatus::Successful->value,
        ]);

        $this->actingAs($admin)->post(route('admin.packages.store'), [
            'match_installer_id' => $installer->id,
            'lead_ids' => [$lead->id],
            'radius_km' => 50,
            'name' => 'Madrid Test Package',
        ]);

        $this->actingAs($buyer)
            ->get(route('buyer.packages.index'))
            ->assertOk()
            ->assertInertia(function ($page) {
                $page->component('Buyer/Packages/Index')->has('prebuilt');
                $prebuilt = collect($page->toArray()['props']['prebuilt'] ?? []);
                $assigned = $prebuilt->firstWhere('assigned', true);
                $this->assertNotNull($assigned);
                $this->assertSame('Madrid Test Package', $assigned['name']);
                $leadPayload = $assigned['leads'][0] ?? [];
                $this->assertArrayNotHasKey('customer_first_name', $leadPayload);
                $this->assertArrayNotHasKey('address_line_1', $leadPayload);
                $this->assertArrayNotHasKey('latitude', $leadPayload);
            });
    }

    public function test_cancelled_package_frees_leads_for_new_package(): void
    {
        $admin = User::query()->where('email', 'admin@rml.test')->firstOrFail();

        $installer = Company::query()
            ->where('type', CompanyType::Buyer->value)
            ->where('approval_status', ApprovalStatus::Approved->value)
            ->firstOrFail();
        $installer->forceFill([
            'latitude' => 40.4168,
            'longitude' => -3.7038,
            'geocoding_status' => GeocodingStatus::Successful->value,
        ])->save();

        $lead = Lead::factory()->listed()->create([
            'latitude' => 40.42,
            'longitude' => -3.70,
            'geocoding_status' => GeocodingStatus::Successful->value,
        ]);

        $this->actingAs($admin)->post(route('admin.packages.store'), [
            'match_installer_id' => $installer->id,
            'lead_ids' => [$lead->id],
            'radius_km' => 50,
        ]);

        $package = LeadPackage::query()->latest('id')->firstOrFail();

        $this->actingAs($admin)
            ->post(route('admin.packages.cancel', $package))
            ->assertRedirect(route('admin.leads.index', ['tab' => 'packages']));

        $this->assertSame(PackageStatus::Cancelled, $package->fresh()->status);

        $this->actingAs($admin)
            ->post(route('admin.packages.store'), [
                'match_installer_id' => $installer->id,
                'lead_ids' => [$lead->id],
                'radius_km' => 50,
            ])
            ->assertRedirect();

        $this->assertTrue(
            LeadPackage::query()
                ->where('status', PackageStatus::Available)
                ->whereHas('leads', fn ($q) => $q->where('leads.id', $lead->id))
                ->exists(),
        );
    }

    public function test_admin_can_create_manual_package_without_installer(): void
    {
        $admin = User::query()->where('email', 'admin@rml.test')->firstOrFail();

        $leadA = Lead::factory()->listed()->create([
            'latitude' => 40.42,
            'longitude' => -3.70,
            'size_m2' => 100,
            'selling_price' => 500,
            'buying_price' => 200,
            'geocoding_status' => GeocodingStatus::Successful->value,
        ]);
        $leadB = Lead::factory()->listed()->create([
            'latitude' => 41.38,
            'longitude' => 2.17,
            'size_m2' => 80,
            'selling_price' => 400,
            'buying_price' => 160,
            'geocoding_status' => GeocodingStatus::Successful->value,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.packages.store'), [
                'lead_ids' => [$leadA->id, $leadB->id],
                'name' => 'Mixed City Manual Package',
            ])
            ->assertRedirect(route('admin.leads.index', ['tab' => 'packages']));

        $package = LeadPackage::query()->latest('id')->first();
        $this->assertNotNull($package);
        $this->assertNull($package->buyer_company_id);
        $this->assertSame(PackageStatus::Available, $package->status);
        $this->assertSame('Mixed City Manual Package', $package->name);
        $this->assertEqualsCanonicalizing(
            [$leadA->id, $leadB->id],
            $package->leads()->pluck('leads.id')->all(),
        );
    }

    public function test_admin_can_assign_buyer_to_manual_package(): void
    {
        $admin = User::query()->where('email', 'admin@rml.test')->firstOrFail();

        $installer = Company::query()
            ->where('type', CompanyType::Buyer->value)
            ->where('approval_status', ApprovalStatus::Approved->value)
            ->firstOrFail();
        $installer->forceFill([
            'latitude' => 40.4168,
            'longitude' => -3.7038,
            'geocoding_status' => GeocodingStatus::Successful->value,
        ])->save();

        $lead = Lead::factory()->listed()->create([
            'latitude' => 40.42,
            'longitude' => -3.70,
            'geocoding_status' => GeocodingStatus::Successful->value,
        ]);

        $this->actingAs($admin)->post(route('admin.packages.store'), [
            'lead_ids' => [$lead->id],
            'name' => 'Awaiting Buyer Package',
        ])->assertRedirect();

        $package = LeadPackage::query()->latest('id')->firstOrFail();
        $this->assertNull($package->buyer_company_id);

        $this->actingAs($admin)
            ->post(route('admin.packages.assign-buyer', $package), [
                'buyer_company_id' => $installer->id,
            ])
            ->assertRedirect(route('admin.packages.show', $package));

        $this->assertSame($installer->id, $package->fresh()->buyer_company_id);
    }

    public function test_leads_hub_packages_tab_embeds_package_index(): void
    {
        $admin = User::query()->where('email', 'admin@rml.test')->firstOrFail();

        $this->actingAs($admin)
            ->get(route('admin.leads.index', ['tab' => 'packages']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Leads/Index')
                ->where('tab', 'packages')
                ->has('packages'));
    }

    public function test_buyer_cannot_create_admin_packages(): void
    {
        $buyer = User::query()->where('email', 'buyer@rml.test')->firstOrFail();

        $this->actingAs($buyer)
            ->post(route('admin.packages.store'), [
                'match_installer_id' => 1,
                'lead_ids' => [1],
            ])
            ->assertForbidden();
    }
}
