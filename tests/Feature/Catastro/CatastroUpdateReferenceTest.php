<?php

namespace Tests\Feature\Catastro;

use App\Enums\ApprovalStatus;
use App\Enums\CadastralLookupStatus;
use App\Enums\LeadStatus;
use App\Enums\UserRole;
use App\Models\Lead;
use App\Models\LeadCatastroSnapshot;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CatastroUpdateReferenceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    #[Test]
    public function admin_can_add_cadastral_reference_without_running_lookup(): void
    {
        $admin = User::factory()->create(['approval_status' => ApprovalStatus::Approved]);
        $admin->assignRole(UserRole::SuperAdmin);

        $lead = Lead::factory()->create([
            'status' => LeadStatus::Listed,
            'cadastral_reference' => null,
            'catastro_status' => 'not_checked',
        ]);

        $this->actingAs($admin)
            ->from(route('admin.leads-bought.show', $lead))
            ->put(route('admin.catastro.reference.update', $lead), [
                'cadastral_reference' => '9872023VH5797S0001WX',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $lead->refresh();

        $this->assertSame('9872023VH5797S0001WX', $lead->cadastral_reference);
        $this->assertSame(CadastralLookupStatus::NotLookedUp, $lead->cadastral_lookup_status);
        $this->assertSame('not_checked', $lead->catastro_status);
        $this->assertDatabaseCount('lead_catastro_snapshots', 0);

        $this->actingAs($admin)
            ->get(route('admin.leads-bought.show', $lead))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('catastro.has_cadastral_reference', true)
                ->where('catastro.lead_submitted.cadastral_reference', '9872023VH5797S0001WX'));
    }

    #[Test]
    public function admin_can_clear_cadastral_reference(): void
    {
        $admin = User::factory()->create(['approval_status' => ApprovalStatus::Approved]);
        $admin->assignRole(UserRole::SuperAdmin);

        $lead = Lead::factory()->create([
            'status' => LeadStatus::Listed,
            'cadastral_reference' => '9872023VH5797S0001WX',
            'catastro_status' => 'matched',
            'catastro_provider' => 'national_catastro',
        ]);

        $this->actingAs($admin)
            ->put(route('admin.catastro.reference.update', $lead), [
                'cadastral_reference' => '',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $lead->refresh();

        $this->assertNull($lead->cadastral_reference);
        $this->assertSame('not_checked', $lead->catastro_status);
        $this->assertNull($lead->catastro_provider);
    }

    #[Test]
    public function changing_reference_clears_stale_current_snapshot_from_panel(): void
    {
        $admin = User::factory()->create(['approval_status' => ApprovalStatus::Approved]);
        $admin->assignRole(UserRole::SuperAdmin);

        $lead = Lead::factory()->create([
            'status' => LeadStatus::Listed,
            'cadastral_reference' => '1302801VK4700F0001AA',
            'catastro_status' => 'matched',
        ]);

        LeadCatastroSnapshot::query()->create([
            'lead_id' => $lead->id,
            'provider' => 'national_catastro',
            'search_method' => 'reference',
            'search_input' => ['cadastral_reference' => '1302801VK4700F0001AA'],
            'verification_status' => 'matched',
            'cadastral_reference' => '1302801VK4700F0001AA',
            'cadastral_address' => 'Calle Gloria 51',
            'is_current' => true,
            'lookup_at' => now(),
        ]);

        $this->actingAs($admin)
            ->put(route('admin.catastro.reference.update', $lead), [
                'cadastral_reference' => '9872023VH5797S0001WX',
            ])
            ->assertRedirect();

        $lead->refresh();
        $this->assertSame('9872023VH5797S0001WX', $lead->cadastral_reference);
        $this->assertSame('not_checked', $lead->catastro_status);

        $this->actingAs($admin)
            ->get(route('admin.leads-bought.show', $lead))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('catastro.current', null)
                ->where('catastro.lead_submitted.cadastral_reference', '9872023VH5797S0001WX'));
    }

    #[Test]
    public function invalid_cadastral_reference_is_rejected(): void
    {
        $admin = User::factory()->create(['approval_status' => ApprovalStatus::Approved]);
        $admin->assignRole(UserRole::SuperAdmin);

        $lead = Lead::factory()->create([
            'status' => LeadStatus::Listed,
            'cadastral_reference' => null,
        ]);

        $this->actingAs($admin)
            ->from(route('admin.leads-bought.show', $lead))
            ->put(route('admin.catastro.reference.update', $lead), [
                'cadastral_reference' => 'NOT-A-VALID-REF',
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('cadastral_reference');

        $this->assertNull($lead->fresh()->cadastral_reference);
    }
}
