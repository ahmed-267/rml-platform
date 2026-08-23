<?php

namespace Tests\Feature\Catastro;

use App\Enums\ApprovalStatus;
use App\Enums\CadastralLookupStatus;
use App\Enums\CatastroVerificationStatus;
use App\Enums\LeadStatus;
use App\Enums\UserRole;
use App\Models\Lead;
use App\Models\LeadCatastroSnapshot;
use App\Models\Payment;
use App\Models\Purchase;
use App\Models\User;
use App\Services\Catastro\CatastroLookupService;
use App\Services\Catastro\CatastroReferenceNormalizer;
use Database\Seeders\CatastroDemoSeeder;
use Database\Seeders\DemoUserSeeder;
use Database\Seeders\DomainDemoSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SchemeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CatastroDemoSeederTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @var array{live: list<array<string, mixed>>, fixtures: list<array<string, mixed>>}
     */
    private array $definitions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->definitions = require database_path('seeders/data/catastro-demo-properties.php');
        $this->seed([
            RolePermissionSeeder::class,
            DemoUserSeeder::class,
            SchemeSeeder::class,
            DomainDemoSeeder::class,
            CatastroDemoSeeder::class,
        ]);
        config(['services.catastro.provider_mode' => 'fixture']);
    }

    #[Test]
    public function seeder_is_idempotent(): void
    {
        $before = Lead::query()->where('lead_source', 'catastro_demo')->count();
        $this->assertGreaterThanOrEqual(8, $before);

        $match = $this->demoLead('live_expected_match');
        $preservedReference = $match->lead_reference;
        $this->assertMatchesRegularExpression('/^LD-\d{4,}$/', $preservedReference);

        $this->seed(CatastroDemoSeeder::class);

        $this->assertSame(
            $before,
            Lead::query()->where('lead_source', 'catastro_demo')->count(),
        );
        $this->assertSame($preservedReference, $match->fresh()->lead_reference);
        $this->assertSame(
            1,
            Lead::query()->where('lead_reference', $preservedReference)->count(),
        );
        $this->assertSame(0, Lead::query()->where('lead_reference', 'like', 'LD-CT-%')->count());
    }

    #[Test]
    public function demo_leads_use_normal_reference_format(): void
    {
        $leads = Lead::query()->where('lead_source', 'catastro_demo')->get();
        $this->assertGreaterThanOrEqual(8, $leads->count());

        foreach ($leads as $lead) {
            $this->assertMatchesRegularExpression('/^LD-\d{4,}$/', (string) $lead->lead_reference);
        }

        $this->assertSame(0, Payment::query()->where('payment_reference', 'like', 'PAY-CT-%')->count());
        $this->assertSame(0, Purchase::query()->where('purchase_reference', 'like', 'PUR-CT-%')->count());

        $sold = $this->demoLead('live_sold_lead');
        $item = $sold->purchaseItems()->first();
        $this->assertNotNull($item);
        $this->assertMatchesRegularExpression('/^PAY-\d{5,}$/', (string) $item->purchase->payment->payment_reference);
        $this->assertMatchesRegularExpression('/^PUR-\d{5,}$/', (string) $item->purchase->purchase_reference);
    }

    #[Test]
    public function domain_demo_leads_include_cadastral_references_without_overwriting_existing(): void
    {
        $withReference = Lead::query()->where('lead_reference', 'LD-1041')->firstOrFail();
        $withoutReference = Lead::query()->where('lead_reference', 'LD-1052')->firstOrFail();

        $this->assertSame('9872023VH5797S0001WX', $withReference->cadastral_reference);
        $this->assertNull($withoutReference->cadastral_reference);

        $withReference->forceFill([
            'cadastral_reference' => '4518801VK4720A0002CC',
        ])->save();

        $this->seed(CatastroDemoSeeder::class);

        $this->assertSame(
            '4518801VK4720A0002CC',
            $withReference->fresh()->cadastral_reference,
            'CatastroDemoSeeder must not overwrite an existing cadastral reference',
        );
        $this->assertNull($withoutReference->fresh()->cadastral_reference);
    }

    #[Test]
    public function cadastral_references_are_normalised(): void
    {
        $normalized = CatastroReferenceNormalizer::normalize('2749-704 YJ0624N 0001DI');
        $this->assertSame('2749704YJ0624N0001DI', $normalized);

        $lead = $this->demoLead('live_expected_match');
        $this->assertSame('2749704YJ0624N0001DI', $lead->cadastral_reference);
    }

    #[Test]
    public function live_scenarios_start_not_checked_without_snapshots(): void
    {
        $lead = $this->demoLead('live_expected_match');

        $this->assertSame(LeadStatus::Listed, $lead->status);
        $this->assertSame(CadastralLookupStatus::NotLookedUp, $lead->cadastral_lookup_status);
        $this->assertSame('not_checked', $lead->catastro_status);
        $this->assertSame(0, $lead->catastroSnapshots()->count());
        $this->assertSame('Catastro Live — Expected Match', $lead->notes);
        $this->assertSame('Godelleta', $lead->city);
        $this->assertSame('46388', $lead->postcode);
        $this->assertSame('CL GUAYANA-MOJONERA 3', $lead->address_line_1);
        $this->assertSame(94.0, (float) $lead->submitted_property_area_m2);
        $this->assertSame(94.0, (float) $lead->size_m2);
        $this->assertEqualsWithDelta(39.4253, (float) $lead->latitude, 0.001);
        $this->assertEqualsWithDelta(-0.6886, (float) $lead->longitude, 0.001);
    }

    #[Test]
    public function live_mismatch_review_uses_same_reference_with_divergent_address(): void
    {
        $match = $this->demoLead('live_expected_match');
        $mismatch = $this->demoLead('live_mismatch_review');

        $this->assertSame($match->cadastral_reference, $mismatch->cadastral_reference);
        $this->assertSame('Catastro Live — Mismatch Review', $mismatch->notes);
        $this->assertSame('Madrid', $mismatch->city);
        $this->assertSame('28013', $mismatch->postcode);
        $this->assertSame(150.0, (float) $mismatch->submitted_property_area_m2);
        $this->assertSame(CadastralLookupStatus::NotLookedUp, $mismatch->cadastral_lookup_status);
        $this->assertSame(0, $mismatch->catastroSnapshots()->count());
    }

    #[Test]
    public function fixture_successful_lookup_creates_one_current_snapshot(): void
    {
        $admin = User::query()->where('email', 'admin@rml.test')->firstOrFail();
        $lead = $this->demoLead('fixture_successful_match');

        $result = app(CatastroLookupService::class)->lookupByReference(
            $lead,
            (string) $lead->cadastral_reference,
            $admin,
        );

        $snapshot = $result['snapshot'];
        $this->assertTrue((bool) $snapshot->is_current);
        $this->assertContains(
            $snapshot->verification_status,
            [
                CatastroVerificationStatus::Matched,
                CatastroVerificationStatus::PartiallyMatched,
            ],
        );
        $this->assertSame(
            1,
            LeadCatastroSnapshot::query()->where('lead_id', $lead->id)->where('is_current', true)->count(),
        );
        $this->assertNull($snapshot->raw_payload['owner'] ?? null);
        $this->assertNull($snapshot->raw_payload['valor'] ?? null);
    }

    #[Test]
    public function fixture_partial_match_keeps_area_warning(): void
    {
        $admin = User::query()->where('email', 'admin@rml.test')->firstOrFail();
        $lead = $this->demoLead('fixture_partial_match');

        $snapshot = app(CatastroLookupService::class)->lookupByReference(
            $lead,
            (string) $lead->cadastral_reference,
            $admin,
        )['snapshot'];

        $this->assertSame(CatastroVerificationStatus::PartiallyMatched, $snapshot->verification_status);
        $this->assertSame(240.0, (float) $lead->fresh()->size_m2);
        $this->assertSame(308.0, (float) $snapshot->constructed_area_m2);
        $codes = collect($snapshot->warnings ?? [])->pluck('code')->all();
        $this->assertContains('area_mismatch', $codes);
    }

    #[Test]
    public function fixture_mismatch_requires_manual_review_path(): void
    {
        $admin = User::query()->where('email', 'admin@rml.test')->firstOrFail();
        $lead = $this->demoLead('fixture_mismatch');

        $snapshot = app(CatastroLookupService::class)->lookupByReference(
            $lead,
            (string) $lead->cadastral_reference,
            $admin,
        )['snapshot'];

        $this->assertSame(CatastroVerificationStatus::MismatchDetected, $snapshot->verification_status);
        $this->assertSame(LeadStatus::Listed, $lead->fresh()->status);
    }

    #[Test]
    public function fixture_multiple_results_require_selection(): void
    {
        $admin = User::query()->where('email', 'admin@rml.test')->firstOrFail();
        $lead = $this->demoLead('fixture_multiple_results');

        $result = app(CatastroLookupService::class)->lookupByReference(
            $lead,
            (string) $lead->cadastral_reference,
            $admin,
        );

        $this->assertSame(
            CatastroVerificationStatus::MultiplePropertiesFound,
            $result['snapshot']->verification_status,
        );
        $this->assertCount(3, $result['candidates']);
        $this->assertFalse((bool) $result['snapshot']->is_selected);

        $selected = app(CatastroLookupService::class)->selectResult(
            $lead,
            $result['snapshot'],
            '4518801VK4720A0002CC',
            $admin,
        );

        $this->assertTrue((bool) $selected['snapshot']->is_selected);
        $this->assertSame('4518801VK4720A0002CC', $selected['snapshot']->cadastral_reference);
    }

    #[Test]
    public function fixture_service_unavailable_keeps_lead_usable(): void
    {
        $admin = User::query()->where('email', 'admin@rml.test')->firstOrFail();
        $lead = $this->demoLead('fixture_service_unavailable');

        $snapshot = app(CatastroLookupService::class)->lookupByReference(
            $lead,
            (string) $lead->cadastral_reference,
            $admin,
        )['snapshot'];

        $this->assertSame(CatastroVerificationStatus::ServiceUnavailable, $snapshot->verification_status);
        $this->assertNull($snapshot->cadastral_address);
        $this->assertNull($snapshot->constructed_area_m2);

        $this->actingAs($admin)
            ->get(route('admin.leads-bought.show', $lead))
            ->assertOk();
    }

    #[Test]
    public function fixture_regional_provider_required_for_navarra_address(): void
    {
        $admin = User::query()->where('email', 'admin@rml.test')->firstOrFail();
        $lead = $this->demoLead('fixture_regional_provider');

        $this->assertNull($lead->cadastral_reference);

        $snapshot = app(CatastroLookupService::class)->lookupByAddress(
            $lead,
            [
                'province' => 'Navarra',
                'municipality' => 'Pamplona',
                'street_name' => 'Mayor',
                'street_number' => '1',
            ],
            $admin,
        )['snapshot'];

        $this->assertSame(
            CatastroVerificationStatus::RegionalProviderRequired,
            $snapshot->verification_status,
        );
        $this->assertNotSame(
            CatastroVerificationStatus::PropertyNotFound,
            $snapshot->verification_status,
        );
    }

    #[Test]
    public function registered_and_sold_catastro_demo_pages_load(): void
    {
        $admin = User::factory()->create(['approval_status' => ApprovalStatus::Approved]);
        $admin->assignRole(UserRole::SuperAdmin);

        $listed = $this->demoLead('live_expected_match');
        $sold = $this->demoLead('live_sold_lead');

        $this->actingAs($admin)
            ->get(route('admin.leads-bought.show', $listed))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/LeadsBought/Show')
                ->where('catastro.current', null)
                ->where('lead.cadastral_reference', '2749704YJ0624N0001DI'));

        $this->actingAs($admin)
            ->get(route('admin.leads-sold.show', $sold))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/LeadsSold/Show')
                ->where('lead.status', LeadStatus::Sold->value));
    }

    #[Test]
    public function admin_can_search_catastro_demo_labels(): void
    {
        $admin = User::query()->where('email', 'admin@rml.test')->firstOrFail();
        $lead = $this->demoLead('live_expected_match');

        $this->actingAs($admin)
            ->get(route('admin.leads.index', [
                'tab' => 'registered',
                'search' => 'Catastro Live — Expected Match',
            ]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('leads.data', fn ($leads) => $leads
                    ->where('0.lead_reference', $lead->lead_reference)
                    ->etc()));
    }

    #[Test]
    public function reseed_migrates_legacy_ct_references_in_place(): void
    {
        $legacy = Lead::query()
            ->where('lead_source', 'catastro_demo')
            ->where('notes', 'Catastro Demo — Successful Match')
            ->firstOrFail();

        $legacy->forceFill(['lead_reference' => 'LD-CT-FIX-MATCH'])->save();

        $this->seed(CatastroDemoSeeder::class);

        $migrated = $legacy->fresh();
        $this->assertMatchesRegularExpression('/^LD-\d{4,}$/', (string) $migrated->lead_reference);
        $this->assertSame('catastro_demo', $migrated->lead_source);
        $this->assertSame('Catastro Demo — Successful Match', $migrated->notes);
        $this->assertSame(0, Lead::query()->where('lead_reference', 'LD-CT-FIX-MATCH')->count());
    }

    private function demoLead(string $scenario): Lead
    {
        $def = collect(array_merge(
            $this->definitions['live'] ?? [],
            $this->definitions['fixtures'] ?? [],
        ))->firstWhere('scenario', $scenario);

        $this->assertNotNull($def, "Unknown Catastro demo scenario [{$scenario}]");

        return Lead::query()
            ->where('lead_source', 'catastro_demo')
            ->where('notes', $def['label'])
            ->firstOrFail();
    }
}
