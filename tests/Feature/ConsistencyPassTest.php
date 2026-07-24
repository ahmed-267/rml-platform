<?php

namespace Tests\Feature;

use App\Enums\LeadStatus;
use App\Enums\UserRole;
use App\Models\AuditChecklistItem;
use App\Models\Lead;
use App\Models\PricingRule;
use App\Models\Scheme;
use App\Models\User;
use App\Models\Zone;
use App\Services\Admin\LeadAuditService;
use App\Support\LeadStatusPresentation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConsistencyPassTest extends TestCase
{
    use RefreshDatabase;

    public function test_scheme_specific_audit_checklists_are_seeded(): void
    {
        $this->seed();

        $insulation = Scheme::query()->where('slug', 'insulation')->firstOrFail();
        $glazing = Scheme::query()->where('slug', 'double-glazing')->firstOrFail();
        $heatPumps = Scheme::query()->where('slug', 'heat-pumps')->firstOrFail();

        $service = new LeadAuditService;

        $insulationKeys = collect($service->checklistItems($insulation->id))->pluck('key');
        $glazingKeys = collect($service->checklistItems($glazing->id))->pluck('key');
        $heatKeys = collect($service->checklistItems($heatPumps->id))->pluck('key');

        $this->assertTrue($insulationKeys->contains('area_m2_plausible'));
        $this->assertTrue($insulationKeys->contains('insulation_type_provided'));
        $this->assertFalse($insulationKeys->contains('window_count_provided'));

        $this->assertTrue($glazingKeys->contains('window_count_provided'));
        $this->assertTrue($glazingKeys->contains('glazing_area_provided'));
        $this->assertFalse($glazingKeys->contains('insulation_type_provided'));

        $this->assertTrue($heatKeys->contains('estimated_kw_provided'));
        $this->assertTrue($heatKeys->contains('outdoor_unit_feasibility_checked'));
        $this->assertFalse($heatKeys->contains('window_count_provided'));

        $this->assertSame(
            0,
            AuditChecklistItem::query()->whereNull('scheme_id')->where('active', true)->count(),
        );
    }

    public function test_accept_requires_scheme_checklist_for_insulation_lead(): void
    {
        $this->seed();

        $admin = User::query()->where('email', 'admin@rml.test')->firstOrFail();
        $lead = Lead::query()->where('lead_reference', 'LD-1047')->firstOrFail();

        $this->actingAs($admin)
            ->post(route('admin.leads.audit.accept', $lead), [
                'buying_price' => 200,
                'selling_price' => 300,
                'override_reason' => 'Incomplete checklist test',
                'checklist' => [],
            ])
            ->assertSessionHasErrors('checklist');

        $checklist = AuditChecklistItem::query()
            ->where('active', true)
            ->where('scheme_id', $lead->scheme_id)
            ->get()
            ->map(fn (AuditChecklistItem $item) => [
                'id' => $item->id,
                'checked' => true,
            ])
            ->values()
            ->all();

        $this->actingAs($admin)
            ->post(route('admin.leads.audit.accept', $lead), [
                'buying_price' => 200,
                'selling_price' => 300,
                'override_reason' => 'Complete checklist test',
                'checklist' => $checklist,
            ])
            ->assertRedirect();

        $this->assertSame(LeadStatus::Listed, $lead->fresh()->status);
    }

    public function test_non_insulation_seeded_leads_have_zones(): void
    {
        $this->seed();

        $glazing = Scheme::query()->where('slug', 'double-glazing')->firstOrFail();
        $heatPumps = Scheme::query()->where('slug', 'heat-pumps')->firstOrFail();

        $this->assertGreaterThan(
            0,
            Zone::query()->where('scheme_id', $glazing->id)->count(),
        );
        $this->assertGreaterThan(
            0,
            Zone::query()->where('scheme_id', $heatPumps->id)->count(),
        );

        $blankZoneCount = Lead::query()
            ->whereIn('scheme_id', [$glazing->id, $heatPumps->id])
            ->whereNull('zone_id')
            ->count();

        $this->assertSame(0, $blankZoneCount);
    }

    public function test_zone_pricing_is_d1_3_d2_4_e1_4_e2_5(): void
    {
        $this->seed();

        $scheme = Scheme::query()->where('slug', 'insulation')->firstOrFail();
        $expected = ['D1' => 3.0, 'D2' => 4.0, 'E1' => 4.0, 'E2' => 5.0];

        foreach ($expected as $code => $price) {
            $zone = Zone::query()->where('scheme_id', $scheme->id)->where('code', $code)->firstOrFail();
            $rule = PricingRule::query()
                ->where('scheme_id', $scheme->id)
                ->where('zone_id', $zone->id)
                ->where('active', true)
                ->firstOrFail();

            $this->assertSame($price, (float) $rule->price_per_m2);
        }
    }

    public function test_simplified_status_presentation_mapping(): void
    {
        $this->assertSame(
            LeadStatusPresentation::PENDING_REVIEW,
            LeadStatusPresentation::visibleKey(LeadStatus::Submitted->value),
        );
        $this->assertSame(
            LeadStatusPresentation::PENDING_REVIEW,
            LeadStatusPresentation::visibleKey(LeadStatus::PendingValidation->value),
        );
        $this->assertSame(
            LeadStatusPresentation::NEEDS_INFORMATION,
            LeadStatusPresentation::visibleKey(LeadStatus::NeedsMoreInformation->value),
        );
        $this->assertSame(
            LeadStatusPresentation::LISTED,
            LeadStatusPresentation::visibleKey(LeadStatus::Accepted->value),
        );
        $this->assertSame(
            LeadStatusPresentation::LISTED,
            LeadStatusPresentation::visibleKey(LeadStatus::Priced->value),
        );

        $grouped = LeadStatusPresentation::groupCounts([
            'submitted' => 2,
            'pending_validation' => 3,
            'needs_more_information' => 1,
            'accepted' => 4,
            'listed' => 1,
            'sold' => 5,
        ]);

        $this->assertSame(5, $grouped['pending_review']);
        $this->assertSame(1, $grouped['needs_information']);
        $this->assertSame(5, $grouped['listed']);
        $this->assertSame(5, $grouped['sold']);
    }

    public function test_role_labels_are_standardised(): void
    {
        $this->assertSame('Seller Admin', UserRole::SellerCompanyAdmin->label());
        $this->assertSame('Seller Agent', UserRole::IndividualSellerAgent->label());
        $this->assertSame('Buyer', UserRole::BuyerAdmin->label());
        $this->assertSame('Admin Staff', UserRole::AdminStaff->label());
        $this->assertSame('Internal Auditor', UserRole::InternalAuditor->label());
    }

    public function test_report_csv_and_pdf_exports_download(): void
    {
        $this->seed();

        $admin = User::query()->where('email', 'admin@rml.test')->firstOrFail();

        $csv = $this->actingAs($admin)->get(route('admin.reports.export.csv'));
        $csv->assertOk();
        $this->assertStringContainsString('text/csv', (string) $csv->headers->get('content-type'));
        $this->assertStringContainsString(
            'rml-report-'.now()->format('Y-m-d').'.csv',
            (string) $csv->headers->get('content-disposition'),
        );

        $pdf = $this->actingAs($admin)->get(route('admin.reports.export.pdf'));
        $pdf->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $pdf->headers->get('content-type'));
        $this->assertStringContainsString(
            'rml-report-'.now()->format('Y-m-d').'.pdf',
            (string) $pdf->headers->get('content-disposition'),
        );

        $this->assertDatabaseHas('audit_logs', ['action' => 'report_exported_csv']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'report_exported_pdf']);
    }

    public function test_report_export_forbidden_for_seller(): void
    {
        $this->seed();

        $seller = User::query()->where('email', 'seller.admin@rml.test')->firstOrFail();

        $this->actingAs($seller)
            ->get(route('admin.reports.export.csv'))
            ->assertForbidden();
    }

    public function test_domain_foundation_has_no_phase_banner_copy_expectation(): void
    {
        $this->seed();

        $admin = User::query()->where('email', 'admin@rml.test')->firstOrFail();

        $this->actingAs($admin)
            ->get(route('admin.foundation'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/DomainFoundation')
                ->has('samples.zone_prices', 4)
                ->where('samples.zone_prices.0.code', 'D1')
                ->where('samples.zone_prices.0.price_per_m2', 3)
                ->where('samples.zone_prices.1.code', 'D2')
                ->where('samples.zone_prices.1.price_per_m2', 4)
                ->where('samples.zone_prices.2.code', 'E1')
                ->where('samples.zone_prices.2.price_per_m2', 4)
                ->where('samples.zone_prices.3.code', 'E2')
                ->where('samples.zone_prices.3.price_per_m2', 5));
    }
}
