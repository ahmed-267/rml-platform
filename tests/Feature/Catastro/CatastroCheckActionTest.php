<?php

namespace Tests\Feature\Catastro;

use App\Enums\ApprovalStatus;
use App\Enums\CatastroVerificationStatus;
use App\Enums\LeadStatus;
use App\Enums\UserRole;
use App\Models\Lead;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CatastroCheckActionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        config(['services.catastro.provider_mode' => 'fixture']);
    }

    #[Test]
    public function admin_can_check_using_stored_cadastral_reference(): void
    {
        $admin = User::factory()->create(['approval_status' => ApprovalStatus::Approved]);
        $admin->assignRole(UserRole::SuperAdmin);

        $lead = Lead::factory()->create([
            'status' => LeadStatus::Listed,
            'address_line_1' => 'Calle Gloria 51',
            'city' => 'Santa Cruz de Mudela',
            'postcode' => '13730',
            'size_m2' => 308,
            'cadastral_reference' => '1302801VK4700F0001AA',
        ]);

        $this->actingAs($admin)
            ->from(route('admin.leads-bought.show', $lead))
            ->post(route('admin.catastro.check', $lead))
            ->assertRedirect()
            ->assertSessionHas('success');

        $lead->refresh();
        $this->assertDatabaseHas('lead_catastro_snapshots', [
            'lead_id' => $lead->id,
            'cadastral_reference' => '1302801VK4700F0001AA',
            'is_current' => true,
        ]);

        $snapshot = $lead->latestCatastroSnapshot;
        $this->assertNotNull($snapshot);
        $this->assertContains(
            $snapshot->verification_status,
            [
                CatastroVerificationStatus::Matched,
                CatastroVerificationStatus::PartiallyMatched,
                CatastroVerificationStatus::MismatchDetected,
                CatastroVerificationStatus::ManualReviewRequired,
            ],
        );
    }

    #[Test]
    public function admin_check_returns_success_flash_on_mismatch(): void
    {
        $admin = User::factory()->create(['approval_status' => ApprovalStatus::Approved]);
        $admin->assignRole(UserRole::SuperAdmin);

        $lead = Lead::factory()->create([
            'status' => LeadStatus::Listed,
            'address_line_1' => 'Calle Industria 12',
            'city' => 'Valencia',
            'postcode' => '46015',
            'size_m2' => 95,
            'cadastral_reference' => '4625001VH2786N0001XX',
        ]);

        $this->actingAs($admin)
            ->from(route('admin.leads-bought.show', $lead))
            ->post(route('admin.catastro.check', $lead))
            ->assertRedirect()
            ->assertSessionHas('success')
            ->assertSessionMissing('error');

        $snapshot = $lead->fresh()->latestCatastroSnapshot;
        $this->assertNotNull($snapshot);
        $this->assertSame(
            CatastroVerificationStatus::MismatchDetected,
            $snapshot->verification_status,
        );
    }

    #[Test]
    public function admin_check_returns_error_flash_when_service_unavailable(): void
    {
        $admin = User::factory()->create(['approval_status' => ApprovalStatus::Approved]);
        $admin->assignRole(UserRole::SuperAdmin);

        $lead = Lead::factory()->create([
            'status' => LeadStatus::Listed,
            'cadastral_reference' => '2807906VK4700A9999ZZ',
        ]);

        $this->actingAs($admin)
            ->from(route('admin.leads-bought.show', $lead))
            ->post(route('admin.catastro.check', $lead))
            ->assertRedirect()
            ->assertSessionHas('error')
            ->assertSessionMissing('success');

        $snapshot = $lead->fresh()->latestCatastroSnapshot;
        $this->assertNotNull($snapshot);
        $this->assertSame(
            CatastroVerificationStatus::ServiceUnavailable,
            $snapshot->verification_status,
        );
    }

    #[Test]
    public function admin_check_requires_stored_cadastral_reference(): void
    {
        $admin = User::factory()->create(['approval_status' => ApprovalStatus::Approved]);
        $admin->assignRole(UserRole::SuperAdmin);

        $lead = Lead::factory()->create([
            'status' => LeadStatus::Listed,
            'cadastral_reference' => null,
        ]);

        $this->actingAs($admin)
            ->from(route('admin.leads-bought.show', $lead))
            ->post(route('admin.catastro.check', $lead))
            ->assertRedirect()
            ->assertSessionHas('error', __('rml.catastro.errors.no_stored_reference'));

        $this->assertDatabaseMissing('lead_catastro_snapshots', [
            'lead_id' => $lead->id,
        ]);
    }

    #[Test]
    public function buyer_cannot_run_admin_catastro_check(): void
    {
        $buyer = User::factory()->create(['approval_status' => ApprovalStatus::Approved]);
        $buyer->assignRole(UserRole::BuyerAdmin);

        $lead = Lead::factory()->create([
            'status' => LeadStatus::Listed,
            'cadastral_reference' => '1302801VK4700F0001AA',
        ]);

        $this->actingAs($buyer)
            ->post(route('admin.catastro.check', $lead))
            ->assertForbidden();
    }

    #[Test]
    public function auditor_can_check_via_auditor_route(): void
    {
        $auditor = User::factory()->create(['approval_status' => ApprovalStatus::Approved]);
        $auditor->assignRole(UserRole::InternalAuditor);

        $lead = Lead::factory()->create([
            'status' => LeadStatus::Listed,
            'address_line_1' => 'Calle Gloria 51',
            'city' => 'Santa Cruz de Mudela',
            'postcode' => '13730',
            'size_m2' => 308,
            'cadastral_reference' => '1302801VK4700F0001AA',
        ]);

        $this->actingAs($auditor)
            ->from(route('auditor.audits.show', $lead))
            ->post(route('auditor.catastro.check', $lead))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('lead_catastro_snapshots', [
            'lead_id' => $lead->id,
            'is_current' => true,
        ]);
    }

    #[Test]
    public function admin_check_returns_error_flash_for_property_not_found_without_throwing(): void
    {
        $admin = User::factory()->create(['approval_status' => ApprovalStatus::Approved]);
        $admin->assignRole(UserRole::SuperAdmin);

        // Valid-format reference with no fixture mapping → property_not_found.
        $lead = Lead::factory()->create([
            'status' => LeadStatus::Listed,
            'cadastral_reference' => '9999999VK9999S0001ZZ',
        ]);

        $this->actingAs($admin)
            ->from(route('admin.leads-bought.show', $lead))
            ->post(route('admin.catastro.check', $lead))
            ->assertRedirect()
            ->assertSessionHas('error')
            ->assertSessionMissing('success');

        $snapshot = $lead->fresh()->latestCatastroSnapshot;
        $this->assertNotNull($snapshot);
        $this->assertSame(
            CatastroVerificationStatus::PropertyNotFound,
            $snapshot->verification_status,
        );
    }
}
