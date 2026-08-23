<?php

namespace Tests\Feature;

use App\Enums\CatastroVerificationStatus;
use App\Enums\LeadStatus;
use App\Enums\SurveyStatus;
use App\Models\Lead;
use App\Models\LeadSurvey;
use App\Models\Scheme;
use App\Models\User;
use App\Services\Survey\LeadSurveyService;
use Database\Seeders\DemoUserSeeder;
use Database\Seeders\DomainDemoSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SchemeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LeadSurveyTest extends TestCase
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

    public function test_starting_survey_does_not_create_duplicates(): void
    {
        $seller = User::query()->where('email', 'seller.admin@rml.test')->firstOrFail();
        $lead = $this->leadForSeller($seller);

        $this->actingAs($seller)
            ->post(route('seller.surveys.start', $lead))
            ->assertRedirect(route('seller.surveys.show', $lead));

        $this->actingAs($seller)
            ->post(route('seller.surveys.start', $lead))
            ->assertRedirect(route('seller.surveys.show', $lead));

        $this->assertSame(1, LeadSurvey::query()->where('lead_id', $lead->id)->count());
    }

    public function test_draft_save_keeps_incomplete_step_and_preserves_catastro_not_checked(): void
    {
        $seller = User::query()->where('email', 'seller.admin@rml.test')->firstOrFail();
        $lead = $this->leadForSeller($seller);
        $service = app(LeadSurveyService::class);
        $survey = $service->ensureForLead($lead, $seller);

        $this->actingAs($seller)
            ->post(route('seller.surveys.draft', $lead), [
                'intent' => 'draft',
                'current_step' => 'measurements',
                'confirmed_address' => 'Calle Mayor 1, Madrid',
                'surveyed_floor_area_m2' => 95,
                'location_confirmed' => true,
            ])
            ->assertRedirect();

        $survey->refresh();
        $this->assertSame(SurveyStatus::InProgress, $survey->status);
        $this->assertSame('Calle Mayor 1, Madrid', $survey->confirmed_address);
        // Incomplete property step must keep user on earliest incomplete step.
        $this->assertSame('property', $survey->current_step);
        $this->assertSame(95.0, (float) $survey->surveyed_floor_area_m2);
        $this->assertSame([], $service->completedSteps($survey));

        $snapshot = $lead->fresh()->latestCatastroSnapshot;
        $this->assertNotNull($snapshot);
        $this->assertSame(CatastroVerificationStatus::NotChecked, $snapshot->verification_status);
        $this->assertNull($snapshot->constructed_area_m2);
        $this->assertNull($snapshot->lookup_at);
    }

    public function test_continue_is_blocked_until_property_step_is_valid(): void
    {
        $seller = User::query()->where('email', 'seller.admin@rml.test')->firstOrFail();
        $lead = $this->leadForSeller($seller);
        app(LeadSurveyService::class)->ensureForLead($lead, $seller);

        $this->actingAs($seller)
            ->from(route('seller.surveys.show', $lead))
            ->post(route('seller.surveys.draft', $lead), [
                'intent' => 'continue',
                'current_step' => 'property',
                'advance_to' => 'measurements',
                'confirmed_address' => 'Calle Mayor 1',
            ])
            ->assertSessionHasErrors();

        $this->assertSame('property', $lead->fresh()->survey->current_step);
    }

    public function test_continue_from_measurements_requires_measured_sections(): void
    {
        $seller = User::query()->where('email', 'seller.admin@rml.test')->firstOrFail();
        $lead = $this->leadForSeller($seller, 'insulation');
        $service = app(LeadSurveyService::class);
        $survey = $service->ensureForLead($lead, $seller);

        $survey->forceFill([
            'survey_date' => now()->toDateString(),
            'confirmed_address' => 'Calle Mayor 1, Madrid',
            'property_type' => 'detached',
            'occupancy_type' => 'owner_occupied',
            'occupied' => true,
            'homeowner_present' => true,
            'general_condition' => 'good',
            'location_confirmed' => true,
            'access' => [
                'internal_access' => true,
                'external_access' => true,
                'loft_access' => true,
                'access_safe' => true,
                'keys_unavailable' => false,
                'return_visit_required' => false,
            ],
            'loft_hatch' => [
                'existing_hatch' => 'yes',
                'new_hatch_required' => false,
            ],
            'current_step' => 'measurements',
        ])->save();

        $this->actingAs($seller)
            ->from(route('seller.surveys.show', $lead))
            ->post(route('seller.surveys.draft', $lead), [
                'intent' => 'continue',
                'current_step' => 'measurements',
                'advance_to' => 'scheme',
                'surveyed_installation_area_m2' => 80,
                'measurement_method' => 'laser',
                'measurement_confidence' => 'high',
                'measurement_date' => now()->toDateString(),
                'measurement_sections' => [],
            ])
            ->assertSessionHasErrors(['measurement_sections']);

        $this->assertSame('measurements', $lead->fresh()->survey->current_step);
    }

    public function test_required_fields_payload_includes_measurement_sections_and_scheme_keys(): void
    {
        $seller = User::query()->where('email', 'seller.admin@rml.test')->firstOrFail();
        $lead = $this->leadForSeller($seller, 'insulation');
        app(LeadSurveyService::class)->ensureForLead($lead, $seller);

        $this->actingAs($seller)
            ->get(route('seller.surveys.show', $lead))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('options.required_fields.measurements', function ($fields) {
                    return in_array('measurement_sections', collect($fields)->all(), true);
                })
                ->where('options.required_fields.scheme', function ($fields) {
                    return in_array('scheme_inspection.existing_insulation_present', collect($fields)->all(), true);
                })
                ->where('options.required_fields.property', function ($fields) {
                    return in_array('loft_hatch.existing_hatch', collect($fields)->all(), true);
                }));
    }

    public function test_submit_requires_mandatory_fields_and_evidence(): void
    {
        $seller = User::query()->where('email', 'seller.admin@rml.test')->firstOrFail();
        $lead = $this->leadForSeller($seller);
        app(LeadSurveyService::class)->ensureForLead($lead, $seller);

        $this->actingAs($seller)
            ->post(route('seller.surveys.submit', $lead), [
                'current_step' => 'review',
                'confirmed_address' => '',
            ])
            ->assertSessionHasErrors();

        $this->assertSame(SurveyStatus::InProgress, $lead->fresh()->survey->status);
    }

    public function test_full_submit_correction_and_auditor_approval_flow(): void
    {
        Storage::fake('local');

        $seller = User::query()->where('email', 'seller.admin@rml.test')->firstOrFail();
        $admin = User::query()->where('email', 'admin@rml.test')->firstOrFail();
        $lead = $this->leadForSeller($seller, 'insulation');
        $service = app(LeadSurveyService::class);
        $survey = $service->ensureForLead($lead, $seller);

        $this->actingAs($seller)
            ->post(route('seller.surveys.evidence', $lead), [
                'file' => UploadedFile::fake()->image('front.jpg'),
                'category' => 'front_exterior',
            ])
            ->assertRedirect();

        $this->actingAs($seller)
            ->post(route('seller.surveys.evidence', $lead), [
                'file' => UploadedFile::fake()->image('install.jpg'),
                'category' => 'installation_area',
            ])
            ->assertRedirect();

        $this->actingAs($seller)
            ->post(route('seller.surveys.evidence', $lead), [
                'file' => UploadedFile::fake()->image('hatch.jpg'),
                'category' => 'loft_hatch',
            ])
            ->assertRedirect();

        $payload = $this->validSurveyPayload();

        $this->actingAs($seller)
            ->post(route('seller.surveys.submit', $lead), $payload)
            ->assertRedirect(route('seller.leads.show', $lead));

        $survey->refresh();
        $this->assertSame(SurveyStatus::Submitted, $survey->status);
        $this->assertSame('survey_pending_review', $lead->fresh()->survey_eligibility_status);
        $this->assertCount(1, $survey->measurementSections);
        $this->assertSame(80.0, (float) $survey->measurementSections->first()->calculated_area_m2);

        $this->actingAs($admin)
            ->post(route('admin.surveys.correction', $lead), [
                'correction_request' => 'Please re-measure loft area.',
                'correction_sections' => ['measurements'],
            ])
            ->assertRedirect();

        $this->assertSame(SurveyStatus::NeedsCorrection, $survey->fresh()->status);

        $payload['surveyed_floor_area_m2'] = 105;
        $payload['surveyed_installation_area_m2'] = 85;

        $this->actingAs($seller)
            ->post(route('seller.surveys.submit', $lead), $payload)
            ->assertRedirect(route('seller.leads.show', $lead));

        $this->assertSame(SurveyStatus::Resubmitted, $survey->fresh()->status);

        $this->actingAs($admin)
            ->post(route('admin.surveys.approve', $lead), [
                'auditor_approved_area_m2' => 90,
                'auditor_approved_installation_area_m2' => 85,
                'auditor_decision_notes' => 'Approved after correction.',
            ])
            ->assertRedirect();

        $survey->refresh();
        $this->assertSame(SurveyStatus::Approved, $survey->status);
        $this->assertSame(90.0, (float) $survey->auditor_approved_area_m2);
        $this->assertSame(85.0, (float) $survey->auditor_approved_installation_area_m2);
        $this->assertSame('survey_approved', $lead->fresh()->survey_eligibility_status);

        $this->assertSame(120.0, (float) $lead->fresh()->size_m2);
        $this->assertSame(120.0, (float) $lead->fresh()->submitted_property_area_m2);
    }

    public function test_buyer_cannot_open_seller_survey_routes(): void
    {
        $buyer = User::query()->where('email', 'buyer@rml.test')->firstOrFail();
        $seller = User::query()->where('email', 'seller.admin@rml.test')->firstOrFail();
        $lead = $this->leadForSeller($seller);

        $this->actingAs($buyer)
            ->get(route('seller.surveys.show', $lead))
            ->assertForbidden();
    }

    public function test_survey_wizard_exposes_scheme_slug_step_progress_and_read_only_catastro(): void
    {
        $seller = User::query()->where('email', 'seller.admin@rml.test')->firstOrFail();
        $lead = $this->leadForSeller($seller, 'heat-pumps');
        app(LeadSurveyService::class)->ensureForLead($lead, $seller);

        $this->actingAs($seller)
            ->get(route('seller.surveys.show', $lead))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Surveys/Wizard')
                ->where('options.scheme_slug', 'heat-pumps')
                ->where('survey.catastro.seller_status.key', 'not_checked')
                ->where('survey.catastro.read_only', true)
                ->where('survey.catastro.is_official_ownership_proof', false)
                ->missing('survey.catastro.provider')
                ->missing('survey.catastro.verification_status')
                ->missing('survey.catastro.constructed_area_m2')
                ->missing('survey.catastro.geometry')
                ->missing('survey.catastro.warnings')
                ->where('survey.comparison.cadastral_constructed_area_m2', null)
                ->where('survey.step_progress.current', 'property')
                ->where('survey.step_progress.completed', [])
                ->where('survey.step_progress.max_reachable_index', 0)
                ->has('breadcrumbs', 3)
                ->where('return_href', route('seller.leads.show', $lead)));
    }

    public function test_seller_survey_hides_matched_catastro_internals(): void
    {
        $seller = User::query()->where('email', 'seller.admin@rml.test')->firstOrFail();
        $admin = User::query()->where('email', 'admin@rml.test')->firstOrFail();
        $lead = $this->leadForSeller($seller);
        $lead->forceFill(['cadastral_reference' => '9872023VH5797S0001WX'])->save();

        \App\Models\LeadCatastroSnapshot::query()->create([
            'lead_id' => $lead->id,
            'provider' => \App\Enums\CatastroProvider::National,
            'verification_status' => CatastroVerificationStatus::Matched,
            'cadastral_reference' => '9872023VH5797S0001WX',
            'cadastral_address' => 'Secret street 9',
            'constructed_area_m2' => 142.5,
            'parcel_area_m2' => 200,
            'geometry' => ['type' => 'Point', 'coordinates' => [-3.7, 40.4]],
            'warnings' => ['area_mismatch'],
            'match_summary' => 'Internal match detail',
            'is_current' => true,
            'lookup_at' => now(),
        ]);

        app(LeadSurveyService::class)->ensureForLead($lead, $seller);

        $this->actingAs($seller)
            ->get(route('seller.surveys.show', $lead))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('survey.catastro.seller_status.key', 'verified')
                ->where('survey.catastro.cadastral_reference', '9872023VH5797S0001WX')
                ->missing('survey.catastro.provider')
                ->missing('survey.catastro.cadastral_address')
                ->missing('survey.catastro.constructed_area_m2')
                ->missing('survey.catastro.geometry')
                ->missing('survey.catastro.warnings')
                ->missing('survey.catastro.match_summary')
                ->where('survey.comparison.cadastral_constructed_area_m2', null));

        $this->actingAs($admin)
            ->get(route('admin.surveys.show', $lead))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('survey.catastro.provider', 'national_catastro')
                ->where('survey.catastro.verification_status', 'matched')
                ->where('survey.catastro.constructed_area_m2', 142.5)
                ->where('survey.catastro.cadastral_address', 'Secret street 9')
                ->where('can_review', true));
    }

    public function test_seller_lead_show_catastro_panel_is_simplified(): void
    {
        $seller = User::query()->where('email', 'seller.admin@rml.test')->firstOrFail();
        $lead = $this->leadForSeller($seller);
        $lead->forceFill(['cadastral_reference' => '9872023VH5797S0001WX'])->save();

        \App\Models\LeadCatastroSnapshot::query()->create([
            'lead_id' => $lead->id,
            'provider' => \App\Enums\CatastroProvider::National,
            'verification_status' => CatastroVerificationStatus::Matched,
            'cadastral_reference' => '9872023VH5797S0001WX',
            'cadastral_address' => 'Secret street 9',
            'constructed_area_m2' => 142.5,
            'warnings' => ['area_mismatch'],
            'match_summary' => 'Internal match detail',
            'coordinate_distance_m' => 12.5,
            'is_current' => true,
            'lookup_at' => now(),
        ]);

        $this->actingAs($seller)
            ->get(route('seller.leads.show', $lead))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('catastro.current', null)
                ->where('catastro.candidates', [])
                ->where('catastro.history', [])
                ->where('catastro.seller_status.key', 'verified')
                ->where('catastro.lead_submitted.cadastral_reference', '9872023VH5797S0001WX')
                ->where('catastro.lead_submitted.address_line_1', null)
                ->where('catastro.lead_submitted.city', null)
                ->where('catastro.lead_submitted.submitted_property_area_m2', null));
    }

    public function test_admin_survey_return_href_points_to_lead_details(): void
    {
        $admin = User::query()->where('email', 'admin@rml.test')->firstOrFail();
        $seller = User::query()->where('email', 'seller.admin@rml.test')->firstOrFail();
        $lead = $this->leadForSeller($seller);
        app(LeadSurveyService::class)->ensureForLead($lead, $admin);

        $this->actingAs($admin)
            ->get(route('admin.surveys.show', $lead))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('return_href', route('admin.leads-bought.show', $lead))
                ->where('breadcrumbs.0.href', route('admin.leads.index', ['tab' => 'registered']))
                ->where('breadcrumbs.1.href', route('admin.leads-bought.show', $lead))
                ->where('breadcrumbs.2.label', __('rml.survey.title')));
    }

    public function test_continue_requires_prior_steps_still_complete(): void
    {
        $seller = User::query()->where('email', 'seller.admin@rml.test')->firstOrFail();
        $lead = $this->leadForSeller($seller);
        $service = app(LeadSurveyService::class);
        $survey = $service->ensureForLead($lead, $seller);

        $payload = $this->validSurveyPayload();
        $survey = $service->saveDraft($survey, $seller, array_merge($payload, [
            'intent' => 'continue',
            'current_step' => 'property',
            'advance_to' => 'measurements',
        ]));

        $this->assertSame('measurements', $survey->current_step);
        $this->assertContains('property', $service->completedSteps($survey));

        $survey = $service->saveDraft($survey, $seller, [
            'intent' => 'draft',
            'current_step' => 'measurements',
            'confirmed_address' => '',
        ]);
        $this->assertNotContains('property', $service->completedSteps($survey));
        // Draft snaps back to earliest incomplete step when later steps are locked.
        $this->assertSame('property', $survey->current_step);

        try {
            $service->saveDraft($survey, $seller, [
                'intent' => 'continue',
                'current_step' => 'property',
                'advance_to' => 'measurements',
                'confirmed_address' => '',
                'survey_date' => now()->toDateString(),
                'property_type' => 'detached',
                'occupancy_type' => 'owner_occupied',
                'occupied' => true,
                'homeowner_present' => true,
                'general_condition' => 'good',
                'location_confirmed' => true,
                'access' => $payload['access'],
                'loft_hatch' => $payload['loft_hatch'],
            ]);
            $this->fail('Expected ValidationException when prior step incomplete');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->assertArrayHasKey('confirmed_address', $e->errors());
        }

        $survey->refresh();
        $this->assertSame('property', $survey->current_step);
    }

    public function test_empty_scheme_step_is_not_marked_complete(): void
    {
        $seller = User::query()->where('email', 'seller.admin@rml.test')->firstOrFail();
        $lead = $this->leadForSeller($seller, 'heat-pumps');
        $service = app(LeadSurveyService::class);
        $survey = $service->ensureForLead($lead, $seller);
        $survey->forceFill([
            'scheme_inspection' => [],
        ])->save();

        $errors = $service->validateStep($survey->fresh(), 'scheme');
        $this->assertNotEmpty($errors);
        $this->assertNotContains('scheme', $service->completedSteps($survey->fresh()));
    }

    /**
     * @return array<string, mixed>
     */
    private function validSurveyPayload(): array
    {
        return [
            'current_step' => 'review',
            'survey_date' => now()->toDateString(),
            'confirmed_address' => 'Calle Mayor 1, Madrid',
            'property_type' => 'detached',
            'occupancy_type' => 'owner_occupied',
            'occupied' => 1,
            'homeowner_present' => 1,
            'general_condition' => 'good',
            'location_confirmed' => 1,
            'surveyed_floor_area_m2' => 100,
            'surveyed_installation_area_m2' => 80,
            'measurement_method' => 'laser',
            'measurement_confidence' => 'high',
            'measurement_date' => now()->toDateString(),
            'measurement_sections' => [
                [
                    'name' => 'Main loft',
                    'section_type' => 'main_loft',
                    'length_m' => 10,
                    'width_m' => 8,
                    'measurement_method' => 'laser',
                    'confidence' => 'high',
                    'is_estimate' => 0,
                    'area_not_accessed' => 0,
                ],
            ],
            'scheme_inspection' => [
                'proposed_insulation_type' => 'loft',
                'existing_insulation_present' => 'yes',
                'suitable_for_installation' => 'suitable',
            ],
            'loft_hatch' => [
                'existing_hatch' => 'yes',
                'new_hatch_required' => 0,
            ],
            'homeowner_confirmation' => [
                'name' => 'Maria Garcia',
                'relationship' => 'owner',
                'permission_to_inspect' => 1,
                'permission_evidence' => 1,
                'confirmation_date' => now()->toDateString(),
            ],
            'access' => [
                'internal_access' => 1,
                'external_access' => 1,
                'loft_access' => 1,
                'access_safe' => 1,
                'keys_unavailable' => 0,
                'return_visit_required' => 0,
            ],
            'risks' => [
                'codes' => [],
            ],
            'surveyor_recommendation' => 'Proceed with loft insulation.',
        ];
    }

    private function leadForSeller(User $seller, string $schemeSlug = 'insulation'): Lead
    {
        $scheme = Scheme::query()->where('slug', $schemeSlug)->firstOrFail();
        $seller->loadMissing('sellerProfile');

        return Lead::factory()->create([
            'submitted_by_user_id' => $seller->id,
            'seller_company_id' => $seller->sellerProfile?->company_id,
            'scheme_id' => $scheme->id,
            'status' => LeadStatus::Accepted,
            'size_m2' => 120,
            'submitted_property_area_m2' => 120,
            'address_line_1' => 'Calle Mayor 1',
            'city' => 'Madrid',
            'postcode' => '28013',
            'country' => 'ES',
        ]);
    }
}
