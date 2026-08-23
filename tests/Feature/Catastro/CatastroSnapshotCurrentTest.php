<?php

namespace Tests\Feature\Catastro;

use App\Enums\ApprovalStatus;
use App\Enums\CatastroProvider;
use App\Enums\CatastroVerificationStatus;
use App\Enums\LeadStatus;
use App\Enums\UserRole;
use App\Models\Lead;
use App\Models\LeadCatastroSnapshot;
use App\Models\User;
use App\Services\Catastro\CatastroLookupService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CatastroSnapshotCurrentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    #[Test]
    public function is_current_column_exists_after_migrations(): void
    {
        $this->assertTrue(Schema::hasColumn('lead_catastro_snapshots', 'is_current'));
    }

    #[Test]
    public function mark_as_current_keeps_one_current_snapshot_per_lead(): void
    {
        $lead = Lead::factory()->create(['status' => LeadStatus::Listed]);

        $older = $this->makeSnapshot($lead, ['is_current' => true, 'cadastral_reference' => 'OLD']);
        $newer = $this->makeSnapshot($lead, ['is_current' => false, 'cadastral_reference' => 'NEW']);

        $newer->markAsCurrent();

        $this->assertFalse((bool) $older->fresh()->is_current);
        $this->assertTrue((bool) $newer->fresh()->is_current);
        $this->assertSame(
            1,
            LeadCatastroSnapshot::query()->where('lead_id', $lead->id)->where('is_current', true)->count(),
        );
    }

    #[Test]
    public function present_for_lead_returns_not_checked_when_no_snapshots(): void
    {
        $lead = Lead::factory()->create(['status' => LeadStatus::Listed]);

        $payload = app(CatastroLookupService::class)->presentForLead($lead);

        $this->assertNull($payload['current']);
        $this->assertSame([], $payload['history']);
        $this->assertSame([], $payload['candidates']);
    }

    #[Test]
    public function present_for_lead_repairs_missing_current_marker(): void
    {
        $lead = Lead::factory()->create([
            'status' => LeadStatus::Listed,
            'cadastral_reference' => '9872023VH5797S0001WX',
        ]);
        $this->makeSnapshot($lead, [
            'is_current' => false,
            'verification_status' => CatastroVerificationStatus::LookupFailed,
            'cadastral_reference' => '9872023VH5797S0001WX',
            'search_input' => ['cadastral_reference' => '9872023VH5797S0001WX'],
        ]);

        $payload = app(CatastroLookupService::class)->presentForLead($lead);

        $this->assertNotNull($payload['current']);
        $this->assertSame(
            CatastroVerificationStatus::LookupFailed->value,
            $payload['current']['verification_status'],
        );
        $this->assertTrue(
            (bool) LeadCatastroSnapshot::query()->where('lead_id', $lead->id)->where('is_current', true)->exists(),
        );
    }

    #[Test]
    public function present_for_lead_hides_snapshot_when_lead_has_no_cadastral_reference(): void
    {
        $lead = Lead::factory()->create([
            'status' => LeadStatus::Listed,
            'cadastral_reference' => null,
        ]);
        $this->makeSnapshot($lead, [
            'is_current' => true,
            'verification_status' => CatastroVerificationStatus::Matched,
            'cadastral_reference' => '9872023VH5797S0001WX',
        ]);

        $payload = app(CatastroLookupService::class)->presentForLead($lead);

        $this->assertNull($payload['current']);
        $this->assertFalse($payload['has_cadastral_reference']);
    }

    #[Test]
    public function registered_lead_show_loads_without_catastro_snapshot(): void
    {
        $admin = User::factory()->create(['approval_status' => ApprovalStatus::Approved]);
        $admin->assignRole(UserRole::SuperAdmin);
        $lead = Lead::factory()->create(['status' => LeadStatus::Listed]);

        $this->actingAs($admin)
            ->get(route('admin.leads-bought.show', $lead))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/LeadsBought/Show')
                ->where('catastro.current', null));
    }

    #[Test]
    public function registered_lead_show_loads_with_current_snapshot(): void
    {
        $admin = User::factory()->create(['approval_status' => ApprovalStatus::Approved]);
        $admin->assignRole(UserRole::SuperAdmin);
        $lead = Lead::factory()->create([
            'status' => LeadStatus::Listed,
            'cadastral_reference' => '9872023VH5797S0001WX',
        ]);
        $this->makeSnapshot($lead, [
            'is_current' => true,
            'verification_status' => CatastroVerificationStatus::Matched,
            'cadastral_reference' => '9872023VH5797S0001WX',
            'search_input' => ['cadastral_reference' => '9872023VH5797S0001WX'],
        ]);

        $this->actingAs($admin)
            ->get(route('admin.leads-bought.show', $lead))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/LeadsBought/Show')
                ->where('catastro.current.cadastral_reference', '9872023VH5797S0001WX'));
    }

    #[Test]
    public function sold_lead_show_loads_with_catastro_payload(): void
    {
        $admin = User::factory()->create(['approval_status' => ApprovalStatus::Approved]);
        $admin->assignRole(UserRole::SuperAdmin);
        $lead = Lead::factory()->create([
            'status' => LeadStatus::Sold,
            'cadastral_reference' => '9872023VH5797S0001WX',
        ]);
        $this->makeSnapshot($lead, [
            'is_current' => true,
            'verification_status' => CatastroVerificationStatus::LookupFailed,
            'cadastral_reference' => '9872023VH5797S0001WX',
            'search_input' => ['cadastral_reference' => '9872023VH5797S0001WX'],
        ]);

        $this->actingAs($admin)
            ->get(route('admin.leads-sold.show', $lead))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/LeadsSold/Show')
                ->where('catastro.current.verification_status', CatastroVerificationStatus::LookupFailed->value));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeSnapshot(Lead $lead, array $overrides = []): LeadCatastroSnapshot
    {
        return LeadCatastroSnapshot::query()->create([
            'lead_id' => $lead->id,
            'provider' => CatastroProvider::National,
            'verification_status' => CatastroVerificationStatus::NotChecked,
            'lookup_at' => now(),
            'is_current' => false,
            'is_selected' => false,
            ...$overrides,
        ]);
    }
}
