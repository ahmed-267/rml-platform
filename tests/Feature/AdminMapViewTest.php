<?php

namespace Tests\Feature;

use App\Enums\ApprovalStatus;
use App\Enums\CompanyType;
use App\Enums\GeocodingStatus;
use App\Enums\LeadStatus;
use App\Models\Company;
use App\Models\Lead;
use App\Models\User;
use App\Models\Zone;
use App\Support\LeadStatusPresentation;
use Database\Seeders\DemoUserSeeder;
use Database\Seeders\DomainDemoSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SchemeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminMapViewTest extends TestCase
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

    public function test_super_admin_map_view_returns_lead_and_installer_markers(): void
    {
        $admin = User::query()->where('email', 'admin@rml.test')->firstOrFail();

        $lead = Lead::query()
            ->where('status', '!=', LeadStatus::Draft->value)
            ->where('status', '!=', LeadStatus::Sold->value)
            ->firstOrFail();
        $lead->forceFill([
            'latitude' => 40.4168,
            'longitude' => -3.7038,
            'geocoding_status' => GeocodingStatus::Successful->value,
        ])->save();

        $buyerCompany = Company::query()
            ->where('type', CompanyType::Buyer->value)
            ->where('approval_status', ApprovalStatus::Approved->value)
            ->firstOrFail();
        $buyerCompany->forceFill([
            'latitude' => 40.42,
            'longitude' => -3.70,
            'geocoding_status' => GeocodingStatus::Successful->value,
            'formatted_address' => 'Madrid warehouse',
        ])->save();

        $this->actingAs($admin)
            ->get(route('admin.leads.index', [
                'tab' => 'registered',
                'view' => 'map',
            ]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Leads/Index')
                ->where('view', 'map')
                ->where('map.can_see_installers', true)
                ->has('map.leads')
                ->has('map.installers')
                ->has('map.center.lat')
                ->has('map.center.lng')
                ->missing('map.leads.0.customer_address')
                ->where('filterOptions.statuses', LeadStatusPresentation::visibleValuesForMapTab('registered')));
    }

    public function test_registered_map_excludes_sold_leads(): void
    {
        $admin = User::query()->where('email', 'admin@rml.test')->firstOrFail();

        Lead::query()->update([
            'latitude' => 40.4,
            'longitude' => -3.7,
            'geocoding_status' => GeocodingStatus::Successful->value,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.leads.index', [
                'tab' => 'registered',
                'view' => 'map',
            ]))
            ->assertOk()
            ->assertInertia(function ($page) {
                $page->component('Admin/Leads/Index')->where('view', 'map');
                $leads = collect($page->toArray()['props']['map']['leads'] ?? []);
                $this->assertTrue(
                    $leads->every(fn ($lead) => ($lead['status'] ?? null) !== LeadStatusPresentation::SOLD
                        && ($lead['status'] ?? null) !== LeadStatus::Sold->value),
                );

                return $page;
            });
    }

    public function test_sold_map_only_includes_sold_leads(): void
    {
        $admin = User::query()->where('email', 'admin@rml.test')->firstOrFail();

        Lead::query()->where('status', LeadStatus::Sold->value)->update([
            'latitude' => 40.3,
            'longitude' => -3.73,
            'geocoding_status' => GeocodingStatus::Successful->value,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.leads.index', [
                'tab' => 'sold',
                'view' => 'map',
            ]))
            ->assertOk()
            ->assertInertia(function ($page) {
                $page->component('Admin/Leads/Index')->where('view', 'map');
                $leads = collect($page->toArray()['props']['map']['leads'] ?? []);
                $this->assertNotEmpty($leads);
                $this->assertTrue(
                    $leads->every(fn ($lead) => ($lead['status'] ?? null) === LeadStatusPresentation::SOLD
                        || ($lead['status'] ?? null) === LeadStatus::Sold->value),
                );

                return $page;
            });
    }

    public function test_registered_map_hides_installer_filter_and_zone_works(): void
    {
        $admin = User::query()->where('email', 'admin@rml.test')->firstOrFail();
        $d2 = Zone::query()->where('code', 'D2')->firstOrFail();

        Lead::query()->update([
            'latitude' => 40.4,
            'longitude' => -3.7,
            'geocoding_status' => GeocodingStatus::Successful->value,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.leads.index', [
                'tab' => 'registered',
                'view' => 'map',
                'zone_code' => 'D2',
            ]))
            ->assertOk()
            ->assertInertia(function ($page) use ($d2) {
                $page->component('Admin/Leads/Index')
                    ->where('view', 'map')
                    ->where('map.show_installer_filter', false)
                    ->where('filterOptions.installers', [])
                    ->where(
                        'filterOptions.statuses',
                        LeadStatusPresentation::visibleValuesForMapTab('registered'),
                    );

                $leads = collect($page->toArray()['props']['map']['leads'] ?? []);
                $this->assertTrue($leads->isNotEmpty());
                $this->assertTrue($leads->every(fn ($lead) => ($lead['zone'] ?? null) === 'D2'));
                $this->assertTrue(
                    Lead::query()->where('zone_id', $d2->id)->whereKey($leads->pluck('id'))->exists(),
                );

                return $page;
            });
    }

    public function test_sold_map_shows_buyer_filter_and_hides_zone(): void
    {
        $admin = User::query()->where('email', 'admin@rml.test')->firstOrFail();

        Lead::query()->where('status', LeadStatus::Sold->value)->update([
            'latitude' => 40.3,
            'longitude' => -3.73,
            'geocoding_status' => GeocodingStatus::Successful->value,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.leads.index', [
                'tab' => 'sold',
                'view' => 'map',
            ]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Leads/Index')
                ->where('view', 'map')
                ->where('map.show_installer_filter', true)
                ->where('filterOptions.zones', [])
                ->where('filterOptions.statuses', [])
                ->has('filterOptions.installers')
                ->has('filterOptions.payment_statuses')
                ->has('filterOptions.release_statuses'));
    }

    public function test_zone_and_status_filters_apply_to_lead_markers(): void
    {
        $admin = User::query()->where('email', 'admin@rml.test')->firstOrFail();
        $d2 = Zone::query()->where('code', 'D2')->firstOrFail();

        Lead::query()->update([
            'latitude' => 40.4,
            'longitude' => -3.7,
            'geocoding_status' => GeocodingStatus::Successful->value,
        ]);

        $rejectedD2 = Lead::query()
            ->where('zone_id', $d2->id)
            ->where('status', LeadStatus::Rejected->value)
            ->firstOrFail();

        $this->actingAs($admin)
            ->get(route('admin.leads.index', [
                'tab' => 'registered',
                'view' => 'map',
                'zone_code' => 'D2',
                'status' => LeadStatusPresentation::REJECTED,
            ]))
            ->assertOk()
            ->assertInertia(function ($page) use ($rejectedD2) {
                $page->component('Admin/Leads/Index')->where('view', 'map');
                $leads = collect($page->toArray()['props']['map']['leads'] ?? []);
                $this->assertTrue($leads->every(fn ($lead) => ($lead['zone'] ?? null) === 'D2'));
                $this->assertTrue($leads->every(fn ($lead) => ($lead['status'] ?? null) === LeadStatusPresentation::REJECTED));
                $this->assertTrue($leads->contains(fn ($lead) => (int) $lead['id'] === $rejectedD2->id));

                return $page;
            });
    }

    public function test_lead_filters_do_not_clear_installer_markers(): void
    {
        $admin = User::query()->where('email', 'admin@rml.test')->firstOrFail();

        Company::query()
            ->where('type', CompanyType::Buyer->value)
            ->where('approval_status', ApprovalStatus::Approved->value)
            ->update([
                'latitude' => 40.42,
                'longitude' => -3.70,
                'geocoding_status' => GeocodingStatus::Successful->value,
            ]);

        $this->actingAs($admin)
            ->get(route('admin.leads.index', [
                'tab' => 'registered',
                'view' => 'map',
                'zone_code' => 'D2',
                'status' => LeadStatusPresentation::REJECTED,
                'marker_set' => 'both',
            ]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Leads/Index')
                ->has('map.installers')
                ->where('map.installers', fn ($installers) => count($installers) > 0));
    }

    public function test_combined_marker_set_returns_leads_and_installers_together(): void
    {
        $admin = User::query()->where('email', 'admin@rml.test')->firstOrFail();

        Lead::query()->update([
            'latitude' => 40.4,
            'longitude' => -3.7,
            'geocoding_status' => GeocodingStatus::Successful->value,
        ]);

        Company::query()
            ->where('type', CompanyType::Buyer->value)
            ->where('approval_status', ApprovalStatus::Approved->value)
            ->update([
                'latitude' => 41.38,
                'longitude' => 2.17,
                'geocoding_status' => GeocodingStatus::Successful->value,
            ]);

        $this->actingAs($admin)
            ->get(route('admin.leads.index', [
                'tab' => 'registered',
                'view' => 'map',
                'marker_set' => 'both',
            ]))
            ->assertOk()
            ->assertInertia(function ($page) {
                $page->component('Admin/Leads/Index')
                    ->where('view', 'map')
                    ->where('map.filters.marker_set', 'both');

                $props = $page->toArray()['props']['map'] ?? [];
                $leads = collect($props['leads'] ?? []);
                $installers = collect($props['installers'] ?? []);

                $this->assertTrue($leads->isNotEmpty(), 'Combined mode must include lead markers');
                $this->assertTrue($installers->isNotEmpty(), 'Combined mode must include installer markers');
                $this->assertTrue(
                    $leads->every(fn ($row) => ($row['type'] ?? null) === 'lead'),
                );
                $this->assertTrue(
                    $installers->every(fn ($row) => ($row['type'] ?? null) === 'installer'),
                );

                return $page;
            });
    }

    public function test_installer_only_marker_set_still_returns_installers(): void
    {
        $admin = User::query()->where('email', 'admin@rml.test')->firstOrFail();

        Company::query()
            ->where('type', CompanyType::Buyer->value)
            ->where('approval_status', ApprovalStatus::Approved->value)
            ->update([
                'latitude' => 40.42,
                'longitude' => -3.70,
                'geocoding_status' => GeocodingStatus::Successful->value,
            ]);

        $this->actingAs($admin)
            ->get(route('admin.leads.index', [
                'tab' => 'registered',
                'view' => 'map',
                'marker_set' => 'installers',
            ]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Leads/Index')
                ->where('map.filters.marker_set', 'installers')
                ->where('map.installers', fn ($installers) => count($installers) > 0));
    }

    public function test_buyer_and_seller_cannot_access_admin_map(): void
    {
        $seller = User::query()->where('email', 'seller.admin@rml.test')->firstOrFail();
        $buyer = User::query()->where('email', 'buyer@rml.test')->firstOrFail();

        $this->actingAs($seller)
            ->get(route('admin.leads.index', ['view' => 'map']))
            ->assertForbidden();

        $this->actingAs($buyer)
            ->get(route('admin.leads.index', ['view' => 'map']))
            ->assertForbidden();
    }

    public function test_map_filters_reduce_markers(): void
    {
        $admin = User::query()->where('email', 'admin@rml.test')->firstOrFail();
        $lead = Lead::query()
            ->where('status', '!=', LeadStatus::Draft->value)
            ->where('status', '!=', LeadStatus::Sold->value)
            ->firstOrFail();
        $lead->forceFill([
            'latitude' => 40.4168,
            'longitude' => -3.7038,
            'geocoding_status' => GeocodingStatus::Successful->value,
        ])->save();

        $this->actingAs($admin)
            ->get(route('admin.leads.index', [
                'tab' => 'registered',
                'view' => 'map',
                'scheme_id' => 999999,
            ]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Leads/Index')
                ->where('view', 'map')
                ->has('map.leads', 0));
    }
}
