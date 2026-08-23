<?php

namespace Tests\Feature;

use App\Enums\AuditDecisionStatus;
use App\Enums\LeadStatus;
use App\Models\AuditChecklistItem;
use App\Models\Lead;
use App\Models\LeadAudit;
use App\Models\MessageThread;
use App\Models\User;
use Database\Seeders\DemoUserSeeder;
use Database\Seeders\DomainDemoSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SchemeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditorPortalTest extends TestCase
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

    public function test_internal_auditor_can_access_dashboard_and_assigned_audits(): void
    {
        $auditor = User::query()->where('email', 'auditor@rml.test')->firstOrFail();

        $this->actingAs($auditor)
            ->get(route('auditor.dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Auditor/Dashboard')
                ->has('kpis')
                ->has('assigned_audits')
                ->where('translations.auditor.dashboard.title', 'Dashboard'));

        $this->actingAs($auditor)
            ->get(route('auditor.assigned-audits.index'))
            ->assertRedirect(route('auditor.audits.index', ['tab' => 'my-audits']));

        $this->actingAs($auditor)
            ->get(route('auditor.audits.index', ['tab' => 'my-audits']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Auditor/Audits/Index')
                ->where('tab', 'my-audits')
                ->where('translations.auditor.audits_hub.index_title', 'Pre-Installation Audits'));

        $this->actingAs($auditor)
            ->get(route('auditor.audits.index', ['tab' => 'audit-queue']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Auditor/Audits/Index')
                ->where('tab', 'audit-queue'));

        $this->actingAs($auditor)
            ->get(route('auditor.completed-audits.index'))
            ->assertRedirect(route('auditor.audits.index', ['tab' => 'completed']));
    }

    public function test_auditor_cannot_access_seller_buyer_or_restricted_admin_routes(): void
    {
        $auditor = User::query()->where('email', 'auditor@rml.test')->firstOrFail();

        $this->actingAs($auditor)->get(route('seller.dashboard'))->assertForbidden();
        $this->actingAs($auditor)->get(route('buyer.dashboard'))->assertForbidden();
        $this->actingAs($auditor)->get(route('admin.settings.index'))->assertForbidden();
        $this->actingAs($auditor)->get(route('admin.payments.index'))->assertForbidden();
    }

    public function test_guest_cannot_access_auditor_routes(): void
    {
        $this->get(route('auditor.dashboard'))->assertRedirect(route('login'));
    }

    public function test_auditor_sees_only_assigned_audits_and_cannot_open_unassigned(): void
    {
        $auditor = User::query()->where('email', 'auditor@rml.test')->firstOrFail();
        $other = User::factory()->approved()->create();
        $other->assignRole('internal_auditor');

        $lead = Lead::query()->where('lead_reference', 'LD-1044')->firstOrFail();
        $audit = LeadAudit::query()->where('lead_id', $lead->id)->firstOrFail();
        $this->assertSame($auditor->id, $audit->auditor_user_id);

        $this->actingAs($auditor)
            ->get(route('auditor.audits.show', $lead))
            ->assertOk();

        $this->actingAs($other)
            ->get(route('auditor.audits.show', $lead))
            ->assertForbidden();
    }

    public function test_auditor_can_save_checklist_and_recommend_accept(): void
    {
        $auditor = User::query()->where('email', 'auditor@rml.test')->firstOrFail();
        $lead = Lead::query()->where('lead_reference', 'LD-1044')->firstOrFail();

        $items = AuditChecklistItem::query()
            ->where('active', true)
            ->where('scheme_id', $lead->scheme_id)
            ->get();
        $checklist = $items->mapWithKeys(fn ($item) => [
            $item->id => ['id' => $item->id, 'checked' => true],
        ])->all();

        $this->actingAs($auditor)
            ->post(route('auditor.audits.checklist', $lead), [
                'audit_notes' => 'Looks solid',
                'checklist' => $checklist,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('audit_logs', ['action' => 'lead.audit_checklist_saved']);

        $this->actingAs($auditor)
            ->post(route('auditor.audits.recommend-accept', $lead), [
                'audit_notes' => 'Recommend accept',
                'checklist' => $checklist,
            ])
            ->assertRedirect(route('auditor.audits.index', ['tab' => 'completed']));

        $audit = LeadAudit::query()->where('lead_id', $lead->id)->latest('id')->firstOrFail();
        $this->assertSame(AuditDecisionStatus::RecommendedAccept, $audit->status);
        $this->assertSame(LeadStatus::Validating, $lead->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'lead.audit_recommended_accept']);
    }

    public function test_recommend_reject_requires_reason_and_request_info_requires_text(): void
    {
        $auditor = User::query()->where('email', 'auditor@rml.test')->firstOrFail();
        $lead = Lead::query()->where('lead_reference', 'LD-1047')->firstOrFail();

        $this->actingAs($auditor)
            ->post(route('auditor.audits.recommend-reject', $lead), [
                'reason_code' => '',
            ])
            ->assertSessionHasErrors('reason_code');

        $this->actingAs($auditor)
            ->post(route('auditor.audits.request-info', $lead), [
                'requested_info' => '',
            ])
            ->assertSessionHasErrors('requested_info');

        $this->actingAs($auditor)
            ->post(route('auditor.audits.recommend-reject', $lead), [
                'reason_code' => 'photos_unclear',
            ])
            ->assertRedirect();

        $this->assertSame(
            AuditDecisionStatus::RecommendedReject,
            LeadAudit::query()->where('lead_id', $lead->id)->latest('id')->firstOrFail()->status,
        );
        $this->assertNotSame(LeadStatus::Rejected, $lead->fresh()->status);
    }

    public function test_auditor_cannot_override_final_admin_decision(): void
    {
        $auditor = User::query()->where('email', 'auditor@rml.test')->firstOrFail();
        $lead = Lead::query()->where('lead_reference', 'LD-1041')->firstOrFail();

        $this->actingAs($auditor)
            ->post(route('auditor.audits.recommend-accept', $lead), [
                'audit_notes' => 'Should fail',
                'checklist' => [],
            ])
            ->assertSessionHasErrors('status');
    }

    public function test_auditor_messages_and_profile_load(): void
    {
        $auditor = User::query()->where('email', 'auditor@rml.test')->firstOrFail();

        $this->actingAs($auditor)
            ->get(route('auditor.messages.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Auditor/Messages/Index'));

        $thread = MessageThread::query()->where('thread_reference', 'THR-00017')->firstOrFail();

        $this->actingAs($auditor)
            ->get(route('auditor.messages.show', $thread))
            ->assertOk();

        $this->actingAs($auditor)
            ->post(route('auditor.messages.reply', $thread), [
                'body' => 'Thanks — I will request a clearer photo.',
            ])
            ->assertRedirect();

        $this->actingAs($auditor)
            ->get(route('auditor.profile'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Auditor/Profile')->has('stats'));
    }

    public function test_seller_and_buyer_cannot_access_auditor_audit_page(): void
    {
        $seller = User::query()->where('email', 'seller.admin@rml.test')->firstOrFail();
        $buyer = User::query()->where('email', 'buyer@rml.test')->firstOrFail();
        $lead = Lead::query()->where('lead_reference', 'LD-1044')->firstOrFail();

        $this->actingAs($seller)->get(route('auditor.audits.show', $lead))->assertForbidden();
        $this->actingAs($buyer)->get(route('auditor.audits.show', $lead))->assertForbidden();
    }

    public function test_super_admin_can_assign_auditor(): void
    {
        $admin = User::query()->where('email', 'admin@rml.test')->firstOrFail();
        $auditor = User::query()->where('email', 'auditor@rml.test')->firstOrFail();
        $lead = Lead::query()->where('lead_reference', 'LD-1046')->firstOrFail();

        $this->actingAs($admin)
            ->post(route('admin.leads.audit.assign', $lead), [
                'auditor_user_id' => $auditor->id,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('lead_audits', [
            'lead_id' => $lead->id,
            'auditor_user_id' => $auditor->id,
        ]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'lead.auditor_assigned']);
    }

    public function test_auditor_can_request_re_survey_with_reason(): void
    {
        $auditor = User::query()->where('email', 'auditor@rml.test')->firstOrFail();
        $lead = Lead::query()->where('lead_reference', 'LD-1044')->firstOrFail();

        $this->actingAs($auditor)
            ->post(route('auditor.audits.request-re-survey', $lead), [
                'requested_info' => '',
            ])
            ->assertSessionHasErrors('requested_info');

        $this->actingAs($auditor)
            ->post(route('auditor.audits.request-re-survey', $lead), [
                'requested_info' => 'Re-measure loft area and retake front exterior photos.',
            ])
            ->assertRedirect(route('auditor.audits.index', ['tab' => 'my-audits']));

        $audit = LeadAudit::query()->where('lead_id', $lead->id)->latest('id')->firstOrFail();
        $this->assertSame(AuditDecisionStatus::ReSurveyRequired, $audit->status);
        $this->assertSame(LeadStatus::NeedsMoreInformation, $lead->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'lead.pre_installation_re_survey_required']);
    }

    public function test_auditor_can_request_manual_verification_with_reason(): void
    {
        $auditor = User::query()->where('email', 'auditor@rml.test')->firstOrFail();
        $lead = Lead::query()->where('lead_reference', 'LD-1047')->firstOrFail();

        $this->actingAs($auditor)
            ->post(route('auditor.audits.request-manual-verification', $lead), [
                'requested_info' => 'Catastro municipality mismatch needs offline check.',
            ])
            ->assertRedirect(route('auditor.audits.index', ['tab' => 'my-audits']));

        $audit = LeadAudit::query()->where('lead_id', $lead->id)->latest('id')->firstOrFail();
        $this->assertSame(AuditDecisionStatus::ManualVerificationRequired, $audit->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'lead.pre_installation_manual_verification_required']);
    }

    public function test_auditor_show_includes_pre_installation_comparison(): void
    {
        $auditor = User::query()->where('email', 'auditor@rml.test')->firstOrFail();
        $lead = Lead::query()->where('lead_reference', 'LD-1044')->firstOrFail();

        $this->actingAs($auditor)
            ->get(route('auditor.audits.show', $lead))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Auditor/Audits/Show')
                ->has('pre_installation.rows')
                ->has('pre_installation.warnings')
                ->where('audit_outcome', fn ($value) => is_string($value) || $value === null));
    }
}
