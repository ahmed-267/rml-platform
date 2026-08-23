<?php

namespace Tests\Feature\Catastro;

use App\Enums\ApprovalStatus;
use App\Enums\CatastroVerificationStatus;
use App\Enums\LeadStatus;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Lead;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CatastroLookupTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        config(['services.catastro.provider_mode' => 'fixture']);
    }

    #[Test]
    public function admin_can_lookup_by_reference_and_store_snapshot(): void
    {
        $admin = User::factory()->create(['approval_status' => ApprovalStatus::Approved]);
        $admin->assignRole(UserRole::SuperAdmin);

        $lead = Lead::factory()->create([
            'status' => LeadStatus::Listed,
            'address_line_1' => 'Calle Gloria 51',
            'city' => 'Santa Cruz de Mudela',
            'postcode' => '13730',
            'size_m2' => 308,
            'property_type' => 'detached',
            'cadastral_reference' => null,
            'catastro_status' => 'not_checked',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.catastro.lookup.reference', $lead), [
                'cadastral_reference' => '9872023VH5797S0001WX',
            ])
            ->assertRedirect();

        $lead->refresh();
        $this->assertSame('9872023VH5797S0001WX', $lead->cadastral_reference);
        $this->assertSame('matched', $lead->catastro_status);
        $this->assertSame('national_catastro', $lead->catastro_provider);
        $this->assertNotNull($lead->catastro_checked_at);
        $this->assertNotNull($lead->catastro_matched_address);
        $this->assertSame('SANTA CRUZ DE MUDELA', $lead->catastro_municipality);
        $this->assertSame('CIUDAD REAL', $lead->catastro_province);
        $this->assertSame('13730', $lead->catastro_postcode);
        $this->assertSame('Residencial', $lead->catastro_property_type);
        $this->assertEquals(308.0, (float) $lead->catastro_built_area);
        $this->assertSame(1980, $lead->catastro_construction_year);
        $this->assertIsArray($lead->catastro_raw_response_json);
        $this->assertArrayHasKey('property', $lead->catastro_raw_response_json);
        $this->assertArrayNotHasKey('titular', $lead->catastro_raw_response_json);
        $this->assertArrayNotHasKey('cadastral_value', $lead->catastro_raw_response_json);
        $this->assertNull($lead->catastro_error_message);
        $this->assertSame([], $lead->catastro_warnings_json ?? []);

        $this->assertDatabaseHas('lead_catastro_snapshots', [
            'lead_id' => $lead->id,
            'cadastral_reference' => '9872023VH5797S0001WX',
            'is_current' => true,
        ]);

        $snapshot = $lead->latestCatastroSnapshot;
        $this->assertNotNull($snapshot);
        $this->assertSame(CatastroVerificationStatus::Matched, $snapshot->verification_status);

        $this->assertTrue(
            AuditLog::query()->where('action', 'catastro_lookup_requested')->where('entity_id', $lead->id)->exists(),
        );
        $this->assertTrue(
            AuditLog::query()->where('action', 'catastro_lookup_succeeded')->where('entity_id', $lead->id)->exists(),
        );
        $this->assertFalse(
            AuditLog::query()->where('action', 'catastro_mismatch_found')->where('entity_id', $lead->id)->exists(),
        );
    }

    #[Test]
    public function live_http_transport_parses_official_xml_response(): void
    {
        config(['services.catastro.provider_mode' => 'live']);

        Http::fake([
            '*Consulta_DNPRC*' => Http::response(
                file_get_contents(base_path('tests/Fixtures/catastro_dnprc_single.xml')),
                200,
                ['Content-Type' => 'application/xml'],
            ),
        ]);

        $admin = User::factory()->create(['approval_status' => ApprovalStatus::Approved]);
        $admin->assignRole(UserRole::SuperAdmin);
        $lead = Lead::factory()->create(['status' => LeadStatus::Listed]);

        $this->actingAs($admin)
            ->post(route('admin.catastro.lookup.reference', $lead), [
                'cadastral_reference' => '9872023VH5797S0001WX',
            ])
            ->assertRedirect();

        $this->assertTrue(
            AuditLog::query()->where('action', 'catastro_lookup_requested')->where('entity_id', $lead->id)->exists(),
        );
        $this->assertSame('national_catastro', $lead->fresh()->catastro_provider);
        $this->assertNotNull($lead->fresh()->catastro_checked_at);
        Http::assertSent(fn ($request) => str_contains($request->url(), '/rest/Consulta_DNPRC'));
    }

    #[Test]
    public function live_transport_falls_back_to_official_json_when_xml_is_unavailable(): void
    {
        config(['services.catastro.provider_mode' => 'live']);

        Http::fake(function ($request) {
            if (str_contains($request->url(), '/rest/Consulta_DNPRC')) {
                return Http::response('Service unavailable', 503);
            }

            return Http::response(
                json_decode(
                    file_get_contents(base_path('tests/Fixtures/catastro_dnprc_single.json')),
                    true,
                    512,
                    JSON_THROW_ON_ERROR,
                ),
                200,
                ['Content-Type' => 'application/json'],
            );
        });

        $admin = User::factory()->create(['approval_status' => ApprovalStatus::Approved]);
        $admin->assignRole(UserRole::SuperAdmin);
        $lead = Lead::factory()->create(['status' => LeadStatus::Listed]);

        $this->actingAs($admin)
            ->post(route('admin.catastro.lookup.reference', $lead), [
                'cadastral_reference' => '9872023VH5797S0001WX',
            ])
            ->assertRedirect();

        $this->assertSame('national_catastro', $lead->fresh()->catastro_provider);
        $this->assertNotSame('unavailable', $lead->fresh()->catastro_status);
        Http::assertSent(fn ($request) => str_contains($request->url(), '/json/Consulta_DNPRC'));
    }

    #[Test]
    public function fixture_mismatch_persists_summary_status_and_mismatch_audit(): void
    {
        $admin = User::factory()->create(['approval_status' => ApprovalStatus::Approved]);
        $admin->assignRole(UserRole::SuperAdmin);

        $lead = Lead::factory()->create([
            'status' => LeadStatus::Listed,
            'address_line_1' => 'Calle Industria 12',
            'city' => 'Valencia',
            'postcode' => '46015',
            'size_m2' => 95,
            'property_type' => 'detached',
            'cadastral_reference' => null,
            'catastro_status' => 'not_checked',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.catastro.lookup.reference', $lead), [
                'cadastral_reference' => '4625001VH2786N0001XX',
            ])
            ->assertRedirect();

        $lead->refresh();
        $this->assertSame('4625001VH2786N0001XX', $lead->cadastral_reference);
        $this->assertSame('mismatch', $lead->catastro_status);
        $this->assertSame('MADRID', $lead->catastro_municipality);
        $this->assertSame('28001', $lead->catastro_postcode);
        $this->assertSame('Industrial', $lead->catastro_property_type);
        $this->assertIsArray($lead->catastro_warnings_json);
        $this->assertNotEmpty($lead->catastro_warnings_json);
        $this->assertIsArray($lead->catastro_raw_response_json);
        $this->assertArrayHasKey('property', $lead->catastro_raw_response_json ?? []);
        $this->assertArrayNotHasKey('titular', $lead->catastro_raw_response_json ?? []);

        $this->assertSame(
            CatastroVerificationStatus::MismatchDetected,
            $lead->latestCatastroSnapshot?->verification_status,
        );

        $this->assertTrue(
            AuditLog::query()->where('action', 'catastro_lookup_requested')->where('entity_id', $lead->id)->exists(),
        );
        $this->assertTrue(
            AuditLog::query()->where('action', 'catastro_lookup_succeeded')->where('entity_id', $lead->id)->exists(),
        );
        $this->assertTrue(
            AuditLog::query()->where('action', 'catastro_mismatch_found')->where('entity_id', $lead->id)->exists(),
        );
    }

    #[Test]
    public function buyer_cannot_run_catastro_lookup(): void
    {
        $buyer = User::factory()->create(['approval_status' => ApprovalStatus::Approved]);
        $buyer->assignRole(UserRole::BuyerAdmin);
        $lead = Lead::factory()->create(['status' => LeadStatus::Listed]);

        $this->actingAs($buyer)
            ->post(route('admin.catastro.lookup.reference', $lead), [
                'cadastral_reference' => '9872023VH5797S0001WX',
            ])
            ->assertForbidden();
    }

    #[Test]
    public function invalid_reference_returns_validation_error(): void
    {
        $admin = User::factory()->create(['approval_status' => ApprovalStatus::Approved]);
        $admin->assignRole(UserRole::SuperAdmin);
        $lead = Lead::factory()->create(['status' => LeadStatus::Listed]);

        $this->actingAs($admin)
            ->from(route('admin.leads-bought.show', $lead))
            ->post(route('admin.catastro.lookup.reference', $lead), [
                'cadastral_reference' => 'NOPE',
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('cadastral_reference');
    }

    #[Test]
    public function regional_provider_required_for_navarra_address(): void
    {
        $admin = User::factory()->create(['approval_status' => ApprovalStatus::Approved]);
        $admin->assignRole(UserRole::SuperAdmin);
        $lead = Lead::factory()->create(['status' => LeadStatus::Listed]);

        $this->actingAs($admin)
            ->post(route('admin.catastro.lookup.address', $lead), [
                'province' => 'Navarra',
                'municipality' => 'Pamplona',
                'street_name' => 'Mayor',
                'street_number' => '1',
            ])
            ->assertRedirect();

        $snapshot = $lead->fresh()->latestCatastroSnapshot;
        $this->assertNotNull($snapshot);
        $this->assertSame(
            CatastroVerificationStatus::RegionalProviderRequired,
            $snapshot->verification_status,
        );
        $this->assertSame('mismatch', $lead->fresh()->catastro_status);
        $this->assertTrue(
            AuditLog::query()->where('action', 'catastro_mismatch_found')->where('entity_id', $lead->id)->exists(),
        );
    }
}
