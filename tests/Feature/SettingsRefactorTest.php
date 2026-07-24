<?php

namespace Tests\Feature;

use App\Models\CommissionRule;
use App\Models\Scheme;
use App\Models\User;
use Database\Seeders\DemoUserSeeder;
use Database\Seeders\DomainDemoSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SchemeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingsRefactorTest extends TestCase
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

    public function test_settings_page_exposes_four_tabs_payload(): void
    {
        $admin = User::query()->where('email', 'admin@rml.test')->firstOrFail();

        $this->actingAs($admin)
            ->get(route('admin.settings.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Settings/Index')
                ->where('tab', 'schemes')
                ->has('schemes')
                ->has('commission_rules')
                ->has('templates')
                ->has('general')
                ->missing('needs_attention'));
    }

    public function test_super_admin_can_create_update_and_deactivate_scheme(): void
    {
        $admin = User::query()->where('email', 'admin@rml.test')->firstOrFail();

        $this->actingAs($admin)
            ->post(route('admin.settings.schemes.store'), [
                'name' => 'Solar Panels',
                'description' => 'Roof solar scheme',
                'active' => false,
                'metadata' => [
                    'lead_type' => 'other',
                    'pricing_basis' => 'fixed_price',
                    'eligibility' => [
                        'conditions' => 'Owner occupied',
                    ],
                    'algorithm' => [
                        'formula' => 'base * zone * size',
                    ],
                ],
                'base_price' => 2.5,
            ])
            ->assertRedirect(route('admin.settings.index', ['tab' => 'schemes']));

        $scheme = Scheme::query()->where('slug', 'solar-panels')->firstOrFail();
        $this->assertFalse($scheme->active);
        $this->assertSame('Owner occupied', $scheme->metadata['eligibility']['conditions'] ?? null);
        $this->assertDatabaseHas('audit_logs', ['action' => 'settings.scheme_created']);

        $this->actingAs($admin)
            ->put(route('admin.settings.schemes.update', $scheme), [
                'name' => 'Solar Panels Plus',
                'description' => 'Updated',
                'active' => false,
                'metadata' => [
                    'lead_type' => 'other',
                    'pricing_basis' => 'fixed_price',
                    'eligibility' => [
                        'conditions' => 'Updated eligibility',
                    ],
                ],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('schemes', [
            'id' => $scheme->id,
            'name' => 'Solar Panels Plus',
        ]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'settings.scheme_updated']);

        // Fresh scheme with no leads can be deleted.
        $this->actingAs($admin)
            ->delete(route('admin.settings.schemes.destroy', $scheme))
            ->assertRedirect();

        $this->assertDatabaseMissing('schemes', ['id' => $scheme->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'settings.scheme_deleted']);
    }

    public function test_incomplete_scheme_cannot_be_activated(): void
    {
        $admin = User::query()->where('email', 'admin@rml.test')->firstOrFail();

        $this->actingAs($admin)
            ->post(route('admin.settings.schemes.store'), [
                'name' => 'Incomplete Scheme',
                'description' => 'Missing required activation fields',
                'active' => true,
                'metadata' => [
                    'pricing_basis' => 'zone_m2',
                ],
            ])
            ->assertSessionHasErrors('active');

        $this->assertDatabaseMissing('schemes', ['name' => 'Incomplete Scheme']);
    }

    public function test_complete_scheme_can_be_activated(): void
    {
        $admin = User::query()->where('email', 'admin@rml.test')->firstOrFail();

        $this->actingAs($admin)
            ->post(route('admin.settings.schemes.store'), [
                'name' => 'Complete Custom Scheme',
                'description' => 'Fully configured scheme ready for activation.',
                'active' => true,
                'metadata' => [
                    'lead_type' => 'building_envelope',
                    'pricing_basis' => 'zone_m2',
                    'required_inputs' => ['zone', 'area_m2', 'photos', 'homeowner_agreement'],
                    'evidence' => ['photos', 'homeowner_agreement'],
                ],
                'zone_prices' => [
                    'D1' => 3.0,
                    'D2' => 4.0,
                    'E1' => 4.0,
                    'E2' => 5.0,
                ],
                'base_price' => 3.0,
                'price_per_m2' => 3.0,
                'create_default_zones' => true,
            ])
            ->assertRedirect(route('admin.settings.index', ['tab' => 'schemes']));

        $this->assertDatabaseHas('schemes', [
            'name' => 'Complete Custom Scheme',
            'active' => true,
        ]);
    }

    public function test_seeded_schemes_expose_type_specific_pricing_basis(): void
    {
        $admin = User::query()->where('email', 'admin@rml.test')->firstOrFail();

        $this->actingAs($admin)
            ->get(route('admin.settings.index', ['tab' => 'schemes']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Settings/Index')
                ->has('schemes', 3)
                ->where('schemes.0.slug', 'insulation')
                ->where('schemes.0.lead_type', 'building_envelope')
                ->where('schemes.0.pricing_basis', 'zone_m2')
                ->where('schemes.1.slug', 'double-glazing')
                ->where('schemes.1.lead_type', 'windows_envelope')
                ->where('schemes.1.pricing_basis', 'window_area_count')
                ->where('schemes.2.slug', 'heat-pumps')
                ->where('schemes.2.lead_type', 'heating_system')
                ->where('schemes.2.pricing_basis', 'per_lead_kw'));
    }

    public function test_scheme_with_leads_is_deactivated_instead_of_deleted(): void
    {
        $admin = User::query()->where('email', 'admin@rml.test')->firstOrFail();
        $scheme = Scheme::query()->whereHas('leads')->firstOrFail();

        $this->actingAs($admin)
            ->delete(route('admin.settings.schemes.destroy', $scheme))
            ->assertRedirect();

        $this->assertDatabaseHas('schemes', [
            'id' => $scheme->id,
            'active' => false,
        ]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'settings.scheme_deactivated']);
    }

    public function test_super_admin_can_manage_commission_rules(): void
    {
        $admin = User::query()->where('email', 'admin@rml.test')->firstOrFail();

        $this->actingAs($admin)
            ->post(route('admin.settings.commissions.store'), [
                'name' => 'Special Agent Rule',
                'applies_to' => 'individual_agent',
                'percentage' => 12.5,
                'active' => true,
                'notes' => 'Test rule',
            ])
            ->assertRedirect(route('admin.settings.index', ['tab' => 'commissions']));

        $rule = CommissionRule::query()->where('name', 'Special Agent Rule')->firstOrFail();
        $this->assertDatabaseHas('audit_logs', ['action' => 'settings.commission_created']);

        $this->actingAs($admin)
            ->put(route('admin.settings.commissions.update', $rule), [
                'percentage' => 14,
                'active' => true,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('audit_logs', ['action' => 'settings.commission_updated']);

        $this->actingAs($admin)
            ->delete(route('admin.settings.commissions.destroy', $rule), [
                'deactivate_only' => true,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('commission_rules', [
            'id' => $rule->id,
            'active' => false,
        ]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'settings.commission_deactivated']);
    }

    public function test_non_admin_cannot_manage_settings(): void
    {
        $seller = User::query()->where('email', 'seller.admin@rml.test')->firstOrFail();

        $this->actingAs($seller)
            ->get(route('admin.settings.index'))
            ->assertForbidden();

        $this->actingAs($seller)
            ->post(route('admin.settings.schemes.store'), [
                'name' => 'Hack',
            ])
            ->assertForbidden();
    }

    public function test_logs_tab_loads_audit_logs(): void
    {
        $admin = User::query()->where('email', 'admin@rml.test')->firstOrFail();

        $this->actingAs($admin)
            ->get(route('admin.settings.index', ['tab' => 'logs']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Settings/Index')
                ->where('tab', 'logs')
                ->has('logs.data')
                ->has('log_users')
                ->has('log_actions')
                ->where('log_filters.sort', 'date')
                ->where('log_filters.direction', 'desc'));
    }

    public function test_logs_tab_filters_by_user_and_sorts_by_date(): void
    {
        $admin = User::query()->where('email', 'admin@rml.test')->firstOrFail();

        $this->actingAs($admin)
            ->get(route('admin.settings.index', [
                'tab' => 'logs',
                'user_id' => $admin->id,
                'sort' => 'date',
                'direction' => 'asc',
            ]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Settings/Index')
                ->where('tab', 'logs')
                ->where('log_filters.user_id', (string) $admin->id)
                ->where('log_filters.sort', 'date')
                ->where('log_filters.direction', 'asc')
                ->has('logs.data'));
    }

    public function test_commission_seed_has_only_default_rules(): void
    {
        $this->assertDatabaseHas('commission_rules', ['name' => 'Seller Company Default', 'percentage' => 10]);
        $this->assertDatabaseHas('commission_rules', ['name' => 'Seller Staff Default', 'percentage' => 3]);
        $this->assertDatabaseHas('commission_rules', ['name' => 'Individual Agent Default', 'percentage' => 7]);
        $this->assertDatabaseMissing('commission_rules', ['name' => 'Premium Seller Company']);
        $this->assertDatabaseMissing('commission_rules', ['name' => 'Legacy Agent Rule']);
        $this->assertSame(3, CommissionRule::query()->count());
    }
}
