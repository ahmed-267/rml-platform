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
use App\Services\Admin\AdminNearbyLeadMatchService;
use App\Services\DistanceService;
use Database\Seeders\DemoUserSeeder;
use Database\Seeders\DomainDemoSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SchemeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminNearbyLeadMatchTest extends TestCase
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

    public function test_map_match_returns_eligible_leads_sorted_by_distance(): void
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

        $near = Lead::factory()->listed()->create([
            'latitude' => 40.42,
            'longitude' => -3.70,
            'size_m2' => 100,
            'geocoding_status' => GeocodingStatus::Successful->value,
        ]);

        $far = Lead::factory()->listed()->create([
            'latitude' => 41.3874,
            'longitude' => 2.1686,
            'size_m2' => 80,
            'geocoding_status' => GeocodingStatus::Successful->value,
        ]);

        $rejected = Lead::factory()->create([
            'status' => LeadStatus::Rejected,
            'latitude' => 40.417,
            'longitude' => -3.704,
            'geocoding_status' => GeocodingStatus::Successful->value,
        ]);

        $response = $this->actingAs($admin)
            ->get(route('admin.leads.index', [
                'tab' => 'registered',
                'view' => 'map',
                'match_installer_id' => $installer->id,
                'radius_km' => 50,
            ]));

        $response->assertOk()->assertInertia(function ($page) use ($near, $far, $rejected, $installer) {
            $page->component('Admin/Leads/Index')
                ->where('map.nearby.active', true)
                ->where('map.nearby.installer.id', $installer->id)
                ->where('map.nearby.radius_km', 50);

            $leads = collect($page->toArray()['props']['map']['leads'] ?? []);
            $ids = $leads->pluck('id')->all();

            $this->assertContains($near->id, $ids);
            $this->assertNotContains($far->id, $ids);
            $this->assertNotContains($rejected->id, $ids);
            $this->assertTrue($leads->every(fn ($lead) => isset($lead['distance_km'])));

            $distances = $leads->pluck('distance_km')->all();
            $sorted = $distances;
            sort($sorted);
            $this->assertSame($sorted, $distances);
        });
    }

    public function test_table_match_includes_distance_and_sorts_closest_first(): void
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

        $closer = Lead::factory()->listed()->create([
            'latitude' => 40.42,
            'longitude' => -3.70,
            'size_m2' => 50,
            'geocoding_status' => GeocodingStatus::Successful->value,
        ]);
        $further = Lead::factory()->listed()->create([
            'latitude' => 40.48,
            'longitude' => -3.65,
            'size_m2' => 60,
            'geocoding_status' => GeocodingStatus::Successful->value,
        ]);

        $response = $this->actingAs($admin)
            ->get(route('admin.leads.index', [
                'tab' => 'registered',
                'view' => 'table',
                'match_installer_id' => $installer->id,
                'radius_km' => 100,
            ]));

        $response->assertOk()->assertInertia(function ($page) use ($closer, $further) {
            $page->where('nearby.active', true)
                ->where('filters.sort', 'distance')
                ->where('filters.direction', 'asc');

            $rows = collect($page->toArray()['props']['leads']['data'] ?? []);
            $this->assertTrue($rows->contains(fn ($row) => $row['id'] === $closer->id));
            $this->assertTrue($rows->contains(fn ($row) => $row['id'] === $further->id));

            $matched = $rows->whereIn('id', [$closer->id, $further->id])->values();
            $this->assertLessThan(
                (float) $matched->firstWhere('id', $further->id)['distance_km'],
                (float) $matched->firstWhere('id', $closer->id)['distance_km'],
            );
        });
    }

    public function test_locked_package_leads_are_excluded_from_match(): void
    {
        $distance = new DistanceService;
        $service = app(AdminNearbyLeadMatchService::class);

        $installer = Company::query()
            ->where('type', CompanyType::Buyer->value)
            ->where('approval_status', ApprovalStatus::Approved->value)
            ->firstOrFail();
        $installer->forceFill([
            'latitude' => 40.4168,
            'longitude' => -3.7038,
            'geocoding_status' => GeocodingStatus::Successful->value,
        ])->save();

        $admin = User::query()->where('email', 'admin@rml.test')->firstOrFail();
        $schemeId = Lead::query()->value('scheme_id');

        $lead = Lead::factory()->listed()->create([
            'latitude' => 40.42,
            'longitude' => -3.70,
            'geocoding_status' => GeocodingStatus::Successful->value,
            'scheme_id' => $schemeId,
        ]);

        $package = LeadPackage::query()->create([
            'package_reference' => 'PKG-TEST-1',
            'name' => 'Locked package',
            'package_type' => 'custom',
            'scheme_id' => $schemeId,
            'status' => PackageStatus::Locked->value,
            'created_by_user_id' => $admin->id,
        ]);
        $package->leads()->attach($lead->id);

        $request = request()->merge([
            'match_installer_id' => $installer->id,
            'radius_km' => 50,
        ]);

        $result = $service->match($request);

        $this->assertTrue($result['active']);
        $this->assertNotContains($lead->id, $result['lead_ids']);
        $this->assertGreaterThan(
            0,
            $distance->betweenKm(40.4168, -3.7038, 40.42, -3.70),
        );
    }

    public function test_buyer_cannot_access_admin_nearby_match(): void
    {
        $buyer = User::query()->where('email', 'buyer@rml.test')->firstOrFail();

        $this->actingAs($buyer)
            ->get(route('admin.leads.index', [
                'tab' => 'registered',
                'view' => 'map',
                'match_installer_id' => 1,
                'radius_km' => 50,
            ]))
            ->assertForbidden();
    }
}
