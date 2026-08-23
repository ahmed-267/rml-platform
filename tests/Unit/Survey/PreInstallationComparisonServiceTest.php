<?php

namespace Tests\Unit\Survey;

use App\Enums\CatastroVerificationStatus;
use App\Models\Lead;
use App\Models\LeadCatastroSnapshot;
use App\Models\LeadSurvey;
use App\Services\Survey\PreInstallationComparisonService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PreInstallationComparisonServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_comparison_keeps_sources_separate_and_warns_on_area_gap(): void
    {
        $lead = Lead::factory()->create([
            'property_type' => 'detached',
            'size_m2' => 100,
            'submitted_property_area_m2' => 100,
            'postcode' => '46388',
            'city' => 'Godelleta',
            'address_line_1' => 'Calle Demo 1',
            'cadastral_reference' => null,
        ]);

        LeadSurvey::query()->create([
            'lead_id' => $lead->id,
            'status' => 'in_progress',
            'current_step' => 'property',
            'version' => 1,
            'property_type' => 'detached',
            'surveyed_installation_area_m2' => 70,
            'confirmed_address' => 'Calle Demo 1, 46388 Godelleta',
            'access' => ['access_safe' => false],
        ]);

        LeadCatastroSnapshot::query()->create([
            'lead_id' => $lead->id,
            'provider' => 'national_catastro',
            'verification_status' => CatastroVerificationStatus::Matched,
            'cadastral_reference' => '2749704YJ0624N0001DI',
            'cadastral_address' => 'Calle Catastro 9',
            'municipality' => 'Godelleta',
            'postcode' => '46388',
            'property_use' => 'Residential',
            'constructed_area_m2' => 94,
            'is_current' => true,
            'lookup_at' => now(),
        ]);

        $payload = app(PreInstallationComparisonService::class)->forLead($lead->fresh());

        $area = collect($payload['rows'])->firstWhere('field', 'area_m2');
        $this->assertSame('100.00 m²', $area['submitted']);
        $this->assertSame('94.00 m²', $area['catastro']);
        $this->assertSame('70.00 m²', $area['survey']);

        $codes = collect($payload['warnings'])->pluck('code')->all();
        $this->assertContains('cadastral_reference_missing', $codes);
        $this->assertContains('survey_area_vs_submitted', $codes);
        $this->assertContains('access_not_confirmed', $codes);

        $lead->refresh();
        $this->assertNull($lead->cadastral_reference);
        $this->assertSame(100.0, (float) $lead->submitted_property_area_m2);
    }
}
