<?php

namespace Tests\Feature;

use App\Enums\ApprovalStatus;
use App\Enums\EvidenceFileType;
use App\Enums\EvidenceStatus;
use App\Enums\EvidenceVisibility;
use App\Enums\LeadStatus;
use App\Enums\MessageThreadCategory;
use App\Enums\MessageThreadStatus;
use App\Models\Lead;
use App\Models\LeadEvidenceFile;
use App\Models\MessageThread;
use App\Models\Scheme;
use App\Models\SellerStaffInvitation;
use App\Models\User;
use App\Models\Zone;
use Database\Seeders\DemoUserSeeder;
use Database\Seeders\DomainDemoSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SchemeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SellerPortalTest extends TestCase
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

    public function test_approved_seller_company_admin_can_access_dashboard(): void
    {
        $admin = User::query()->where('email', 'seller.admin@rml.test')->firstOrFail();

        $this->actingAs($admin)
            ->get(route('seller.dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Seller/Dashboard')
                ->has('kpis')
                ->has('recent_leads'));
    }

    public function test_pending_seller_cannot_access_seller_dashboard(): void
    {
        $pending = User::query()->where('email', 'pending.seller@rml.test')->firstOrFail();

        $this->actingAs($pending)
            ->get(route('seller.dashboard'))
            ->assertRedirect(route('pending-approval'));
    }

    public function test_suspended_seller_cannot_access_seller_dashboard(): void
    {
        $admin = User::query()->where('email', 'seller.admin@rml.test')->firstOrFail();
        $admin->update(['approval_status' => ApprovalStatus::Suspended]);

        $this->actingAs($admin->fresh())
            ->get(route('seller.dashboard'))
            ->assertRedirect(route('pending-approval'));
    }

    public function test_seller_cannot_access_buyer_or_admin_routes(): void
    {
        $seller = User::query()->where('email', 'seller.admin@rml.test')->firstOrFail();

        $this->actingAs($seller)->get(route('buyer.dashboard'))->assertForbidden();
        $this->actingAs($seller)->get(route('admin.dashboard'))->assertForbidden();
        $this->actingAs($seller)->get(route('admin.approvals'))->assertForbidden();
    }

    public function test_seller_staff_and_agent_cannot_access_staff_commissions(): void
    {
        $staff = User::query()->where('email', 'seller.staff@rml.test')->firstOrFail();
        $agent = User::query()->where('email', 'agent@rml.test')->firstOrFail();

        $this->actingAs($staff)->get(route('seller.staff.index'))->assertForbidden();
        $this->actingAs($agent)->get(route('seller.staff.index'))->assertForbidden();
        $this->actingAs($staff)->get(route('seller.staff-commissions'))->assertForbidden();
        $this->actingAs($agent)->get(route('seller.staff-commissions'))->assertForbidden();
    }

    public function test_seller_company_admin_can_access_staff_commissions(): void
    {
        $admin = User::query()->where('email', 'seller.admin@rml.test')->firstOrFail();

        $this->actingAs($admin)
            ->get(route('seller.staff.index', ['tab' => 'commissions']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Seller/Staff/Index')
                ->where('tab', 'commissions'));

        $this->actingAs($admin)
            ->get(route('seller.staff-commissions'))
            ->assertRedirect(route('seller.staff.index', ['tab' => 'commissions']));
    }

    public function test_submit_without_required_evidence_is_rejected(): void
    {
        Storage::fake('local');

        $admin = User::query()->where('email', 'seller.admin@rml.test')->firstOrFail();
        $scheme = Scheme::query()->where('slug', 'insulation')->firstOrFail();
        $zone = Zone::query()->where('scheme_id', $scheme->id)->where('code', 'D1')->firstOrFail();

        $this->actingAs($admin)
            ->from(route('seller.leads.create'))
            ->post(route('seller.leads.store'), $this->leadPayload($scheme->id, $zone, [
                'metrics' => [
                    'zone' => 'D1',
                    'size_m2' => 95,
                    'insulation_type' => 'cavity_wall',
                    'access_constraints' => 'Narrow alley',
                ],
                'evidence_photos' => [UploadedFile::fake()->image('photo1.jpg')],
                'consent' => '1',
                'evidence_genuine' => '1',
                'info_accurate' => '1',
            ]))
            ->assertSessionHasErrors('evidence');
    }

    public function test_submit_with_photos_and_agreement_enters_pending_validation(): void
    {
        Storage::fake('local');

        $admin = User::query()->where('email', 'seller.admin@rml.test')->firstOrFail();
        $scheme = Scheme::query()->where('slug', 'insulation')->firstOrFail();
        $zone = Zone::query()->where('scheme_id', $scheme->id)->where('code', 'D1')->firstOrFail();

        $response = $this->actingAs($admin)->post(route('seller.leads.store'), $this->leadPayload($scheme->id, $zone, [
            'metrics' => [
                'zone' => 'D1',
                'size_m2' => 95,
                'insulation_type' => 'cavity_wall',
                'access_constraints' => 'Narrow alley',
            ],
            'evidence_photos' => [UploadedFile::fake()->image('photo1.jpg')],
            'evidence_agreement' => UploadedFile::fake()->create('agreement.pdf', 100, 'application/pdf'),
            'consent' => '1',
            'evidence_genuine' => '1',
            'info_accurate' => '1',
        ]));

        $lead = Lead::query()->latest('id')->first();
        $this->assertNotNull($lead);
        $response->assertRedirect(route('seller.leads.show', $lead));

        $this->assertMatchesRegularExpression('/^LD-\d{4}$/', $lead->lead_reference);
        $this->assertSame(LeadStatus::PendingValidation, $lead->status);
        $this->assertSame($admin->id, $lead->submitted_by_user_id);
        $this->assertSame($admin->sellerProfile?->company_id, $lead->seller_company_id);
        $this->assertSame(95.0, (float) $lead->size_m2);
        $this->assertDatabaseHas('lead_metric_values', [
            'lead_id' => $lead->id,
            'key' => 'access_constraints',
            'value' => 'Narrow alley',
        ]);
        $this->assertDatabaseHas('lead_evidence_files', [
            'lead_id' => $lead->id,
            'file_type' => 'photo',
        ]);
    }

    public function test_completed_evidence_creates_pending_validation_status(): void
    {
        Storage::fake('local');

        $admin = User::query()->where('email', 'seller.admin@rml.test')->firstOrFail();
        $scheme = Scheme::query()->where('slug', 'insulation')->firstOrFail();
        $zone = Zone::query()->where('scheme_id', $scheme->id)->where('code', 'D1')->firstOrFail();

        $this->actingAs($admin)->post(route('seller.leads.store'), $this->leadPayload($scheme->id, $zone, [
            'evidence_photos' => [UploadedFile::fake()->image('photo1.jpg')],
            'evidence_video' => UploadedFile::fake()->create('clip.mp4', 500, 'video/mp4'),
            'evidence_agreement' => UploadedFile::fake()->create('agreement.pdf', 100, 'application/pdf'),
            'evidence_eligibility' => [UploadedFile::fake()->create('eligibility.pdf', 100, 'application/pdf')],
            'consent' => '1',
            'evidence_genuine' => '1',
            'info_accurate' => '1',
        ]));

        $lead = Lead::query()->latest('id')->firstOrFail();
        $this->assertSame(LeadStatus::PendingValidation, $lead->status);
        $this->assertGreaterThanOrEqual(2, $lead->evidenceFiles->count());
    }

    public function test_seller_can_save_incomplete_draft_and_continue_editing(): void
    {
        $admin = User::query()->where('email', 'seller.admin@rml.test')->firstOrFail();
        $scheme = Scheme::query()->where('slug', 'insulation')->firstOrFail();
        $zone = Zone::query()->where('scheme_id', $scheme->id)->where('code', 'D1')->firstOrFail();

        $response = $this->actingAs($admin)->post(route('seller.leads.store'), [
            'scheme_id' => $scheme->id,
            'zone_id' => $zone->id,
            'customer_first_name' => 'Draft',
            'customer_last_name' => 'Lead',
            'customer_phone' => '',
            'as_draft' => true,
        ]);

        $lead = Lead::query()->latest('id')->firstOrFail();
        $this->assertSame(LeadStatus::Draft, $lead->status);
        $response->assertRedirect(route('seller.leads.edit', $lead));

        $this->actingAs($admin)
            ->get(route('seller.leads.edit', $lead))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Seller/Leads/Create')
                ->where('lead.id', $lead->id));
    }

    public function test_seller_cannot_submit_lead_with_incomplete_scheme_metrics(): void
    {
        $admin = User::query()->where('email', 'seller.admin@rml.test')->firstOrFail();
        $scheme = Scheme::query()->where('slug', 'insulation')->firstOrFail();
        $zone = Zone::query()->where('scheme_id', $scheme->id)->where('code', 'D1')->firstOrFail();

        $this->actingAs($admin)
            ->from(route('seller.leads.create'))
            ->post(route('seller.leads.store'), $this->leadPayload($scheme->id, $zone, [
                'metrics' => [
                    'zone' => 'D1',
                    'size_m2' => 95,
                    // missing required insulation_type
                ],
                'consent' => '1',
                'evidence_genuine' => '1',
                'info_accurate' => '1',
                'as_draft' => false,
            ]))
            ->assertSessionHasErrors('metrics.insulation_type');
    }

    public function test_seller_staff_can_submit_lead_under_company_account(): void
    {
        Storage::fake('local');

        $staff = User::query()->where('email', 'seller.staff@rml.test')->firstOrFail();
        $scheme = Scheme::query()->where('slug', 'insulation')->firstOrFail();
        $zone = Zone::query()->where('scheme_id', $scheme->id)->where('code', 'D1')->firstOrFail();

        $this->actingAs($staff)->post(route('seller.leads.store'), $this->leadPayload($scheme->id, $zone, [
            'evidence_photos' => [UploadedFile::fake()->image('staff-photo.jpg')],
            'evidence_agreement' => UploadedFile::fake()->create('staff-agreement.pdf', 100, 'application/pdf'),
            'consent' => '1',
            'evidence_genuine' => '1',
            'info_accurate' => '1',
        ]))->assertRedirect();

        $lead = Lead::query()->latest('id')->firstOrFail();
        $this->assertSame($staff->id, $lead->submitted_by_user_id);
        $this->assertSame($staff->sellerProfile?->company_id, $lead->seller_company_id);
    }

    public function test_individual_agent_can_submit_own_lead(): void
    {
        Storage::fake('local');

        $agent = User::query()->where('email', 'agent@rml.test')->firstOrFail();
        $scheme = Scheme::query()->where('slug', 'insulation')->firstOrFail();
        $zone = Zone::query()->where('scheme_id', $scheme->id)->where('code', 'D1')->firstOrFail();

        $this->actingAs($agent)->post(route('seller.leads.store'), $this->leadPayload($scheme->id, $zone, [
            'evidence_photos' => [UploadedFile::fake()->image('agent-photo.jpg')],
            'evidence_agreement' => UploadedFile::fake()->create('agent-agreement.pdf', 100, 'application/pdf'),
            'consent' => '1',
            'evidence_genuine' => '1',
            'info_accurate' => '1',
        ]))->assertRedirect();

        $lead = Lead::query()->latest('id')->firstOrFail();
        $this->assertSame($agent->id, $lead->submitted_by_user_id);
    }

    public function test_company_admin_sees_company_leads_staff_sees_only_own(): void
    {
        $admin = User::query()->where('email', 'seller.admin@rml.test')->firstOrFail();
        $staff = User::query()->where('email', 'seller.staff@rml.test')->firstOrFail();
        $scheme = Scheme::query()->where('slug', 'insulation')->firstOrFail();
        $companyId = $admin->sellerProfile?->company_id;

        $adminLead = Lead::factory()->create([
            'submitted_by_user_id' => $admin->id,
            'seller_company_id' => $companyId,
            'scheme_id' => $scheme->id,
            'status' => LeadStatus::PendingValidation,
            'customer_first_name' => 'Admin',
            'customer_last_name' => 'Lead',
        ]);

        $staffLead = Lead::factory()->create([
            'submitted_by_user_id' => $staff->id,
            'seller_company_id' => $companyId,
            'scheme_id' => $scheme->id,
            'status' => LeadStatus::PendingValidation,
            'customer_first_name' => 'Staff',
            'customer_last_name' => 'Lead',
        ]);

        $this->actingAs($admin)
            ->get(route('seller.leads.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Seller/Leads/Index')
                ->where('leads.data', function ($data) use ($adminLead, $staffLead) {
                    $ids = collect($data)->pluck('id');

                    return $ids->contains($adminLead->id) && $ids->contains($staffLead->id);
                }));

        $this->actingAs($staff)
            ->get(route('seller.leads.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Seller/Leads/Index')
                ->where('leads.data', function ($data) use ($adminLead, $staffLead) {
                    $ids = collect($data)->pluck('id');

                    return $ids->contains($staffLead->id) && ! $ids->contains($adminLead->id);
                }));

        $this->actingAs($staff)
            ->get(route('seller.leads.show', $adminLead))
            ->assertForbidden();
    }

    public function test_individual_agent_sees_only_own_leads(): void
    {
        $agent = User::query()->where('email', 'agent@rml.test')->firstOrFail();
        $admin = User::query()->where('email', 'seller.admin@rml.test')->firstOrFail();
        $scheme = Scheme::query()->where('slug', 'insulation')->firstOrFail();

        $other = Lead::factory()->create([
            'submitted_by_user_id' => $admin->id,
            'seller_company_id' => $admin->sellerProfile?->company_id,
            'scheme_id' => $scheme->id,
            'status' => LeadStatus::PendingValidation,
        ]);

        $own = Lead::factory()->create([
            'submitted_by_user_id' => $agent->id,
            'seller_company_id' => $agent->sellerProfile?->company_id,
            'scheme_id' => $scheme->id,
            'status' => LeadStatus::PendingValidation,
        ]);

        $this->actingAs($agent)
            ->get(route('seller.leads.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('leads.data', function ($data) use ($own, $other) {
                    $ids = collect($data)->pluck('id');

                    return $ids->contains($own->id) && ! $ids->contains($other->id);
                }));
    }

    public function test_seller_lead_detail_does_not_expose_buyer_or_margin_fields(): void
    {
        $admin = User::query()->where('email', 'seller.admin@rml.test')->firstOrFail();
        $scheme = Scheme::query()->where('slug', 'insulation')->firstOrFail();

        $lead = Lead::factory()->listed()->create([
            'submitted_by_user_id' => $admin->id,
            'seller_company_id' => $admin->sellerProfile?->company_id,
            'scheme_id' => $scheme->id,
        ]);

        $this->actingAs($admin)
            ->get(route('seller.leads.show', $lead))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Seller/Leads/Show')
                ->has('lead')
                ->has('lead.payout')
                ->where('lead.payout.display', 'estimated')
                ->where('lead.payout.amount', 300)
                ->missing('lead.buying_price')
                ->missing('lead.selling_price')
                ->missing('lead.expected_margin')
                ->missing('lead.buyer')
                ->missing('lead.buyer_company'));
    }

    public function test_seller_lead_index_includes_seller_safe_payout_summary(): void
    {
        $admin = User::query()->where('email', 'seller.admin@rml.test')->firstOrFail();
        $scheme = Scheme::query()->where('slug', 'insulation')->firstOrFail();

        $listed = Lead::factory()->listed()->create([
            'submitted_by_user_id' => $admin->id,
            'seller_company_id' => $admin->sellerProfile?->company_id,
            'scheme_id' => $scheme->id,
            'buying_price' => 300,
        ]);

        $draft = Lead::factory()->create([
            'submitted_by_user_id' => $admin->id,
            'seller_company_id' => $admin->sellerProfile?->company_id,
            'scheme_id' => $scheme->id,
            'status' => LeadStatus::Draft,
            'buying_price' => null,
        ]);

        $this->actingAs($admin)
            ->get(route('seller.leads.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Seller/Leads/Index')
                ->has('leads.data')
                ->where('leads.data', function ($data) use ($listed, $draft) {
                    $rows = collect($data)->keyBy('id');
                    $listedRow = $rows->get($listed->id);
                    $draftRow = $rows->get($draft->id);

                    return $listedRow
                        && data_get($listedRow, 'payout.display') === 'estimated'
                        && data_get($listedRow, 'payout.amount') == 300
                        && ! array_key_exists('buying_price', $listedRow)
                        && ! array_key_exists('selling_price', $listedRow)
                        && $draftRow
                        && data_get($draftRow, 'payout.display') === 'dash';
                }));
    }

    public function test_seller_staff_without_commission_sees_managed_by_admin_payout(): void
    {
        $staff = User::query()->where('email', 'seller.staff@rml.test')->firstOrFail();
        $scheme = Scheme::query()->where('slug', 'insulation')->firstOrFail();

        $staff->loadMissing('sellerProfile');
        $staff->sellerProfile?->update(['commission_rate' => null]);

        $lead = Lead::factory()->listed()->create([
            'submitted_by_user_id' => $staff->id,
            'seller_company_id' => $staff->sellerProfile?->company_id,
            'scheme_id' => $scheme->id,
            'buying_price' => 300,
        ]);

        $this->actingAs($staff->fresh())
            ->get(route('seller.leads.show', $lead))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Seller/Leads/Show')
                ->where('lead.payout.managed_by_admin', true)
                ->where('lead.payout.display', 'managed_by_admin')
                ->missing('lead.buying_price')
                ->missing('lead.selling_price')
                ->missing('lead.expected_margin'));
    }

    public function test_seller_evidence_visibility_follows_company_and_ownership_rules(): void
    {
        Storage::fake('local');

        $admin = User::query()->where('email', 'seller.admin@rml.test')->firstOrFail();
        $staff = User::query()->where('email', 'seller.staff@rml.test')->firstOrFail();
        $scheme = Scheme::query()->where('slug', 'insulation')->firstOrFail();
        $companyId = $admin->sellerProfile?->company_id;

        $adminLead = Lead::factory()->create([
            'submitted_by_user_id' => $admin->id,
            'seller_company_id' => $companyId,
            'scheme_id' => $scheme->id,
            'status' => LeadStatus::PendingValidation,
        ]);

        $staffLead = Lead::factory()->create([
            'submitted_by_user_id' => $staff->id,
            'seller_company_id' => $companyId,
            'scheme_id' => $scheme->id,
            'status' => LeadStatus::PendingValidation,
        ]);

        $adminEvidence = LeadEvidenceFile::query()->create([
            'lead_id' => $adminLead->id,
            'uploaded_by_user_id' => $admin->id,
            'file_type' => EvidenceFileType::Photo,
            'original_name' => 'admin-photo.jpg',
            'path' => 'evidence/admin-photo.jpg',
            'disk' => 'local',
            'mime_type' => 'image/jpeg',
            'size' => 1200,
            'visibility' => EvidenceVisibility::Private,
            'status' => EvidenceStatus::Uploaded,
        ]);

        $staffEvidence = LeadEvidenceFile::query()->create([
            'lead_id' => $staffLead->id,
            'uploaded_by_user_id' => $staff->id,
            'file_type' => EvidenceFileType::Photo,
            'original_name' => 'staff-photo.jpg',
            'path' => 'evidence/staff-photo.jpg',
            'disk' => 'local',
            'mime_type' => 'image/jpeg',
            'size' => 1100,
            'visibility' => EvidenceVisibility::Private,
            'status' => EvidenceStatus::Uploaded,
        ]);

        Storage::disk('local')->put($adminEvidence->path, 'fake-image');
        Storage::disk('local')->put($staffEvidence->path, 'fake-image');

        $this->actingAs($admin)
            ->get(route('seller.leads.evidence.view', $adminEvidence))
            ->assertOk();
        $this->actingAs($admin)
            ->get(route('seller.leads.evidence.view', $staffEvidence))
            ->assertOk();

        $this->actingAs($staff)
            ->get(route('seller.leads.evidence.view', $staffEvidence))
            ->assertOk();
        $this->actingAs($staff)
            ->get(route('seller.leads.evidence.view', $adminEvidence))
            ->assertForbidden();
    }

    public function test_seller_company_admin_can_invite_staff_and_staff_cannot(): void
    {
        $admin = User::query()->where('email', 'seller.admin@rml.test')->firstOrFail();
        $staff = User::query()->where('email', 'seller.staff@rml.test')->firstOrFail();

        $this->actingAs($admin)
            ->get(route('seller.staff.invite'))
            ->assertRedirect(route('seller.staff.index', ['tab' => 'invite']));

        $this->actingAs($admin)
            ->post(route('seller.staff.invite.store'), ['email' => 'new.staff@rml.test'])
            ->assertRedirect();

        $this->assertDatabaseHas('seller_staff_invitations', [
            'email' => 'new.staff@rml.test',
            'status' => 'pending',
            'company_id' => $admin->sellerProfile?->company_id,
        ]);

        $invitation = SellerStaffInvitation::query()->where('email', 'new.staff@rml.test')->firstOrFail();
        $this->assertNotEmpty($invitation->token);

        $this->actingAs($staff)
            ->post(route('seller.staff.invite.store'), ['email' => 'blocked@rml.test'])
            ->assertForbidden();
    }

    public function test_seller_company_admin_can_update_staff_commission_rate(): void
    {
        $admin = User::query()->where('email', 'seller.admin@rml.test')->firstOrFail();
        $staff = User::query()->where('email', 'seller.staff@rml.test')->firstOrFail();

        $this->actingAs($admin)
            ->patch(route('seller.staff.commission.update', $staff), [
                'commission_rate' => 12.5,
            ])
            ->assertRedirect();

        $this->assertSame(12.5, (float) $staff->fresh()->sellerProfile?->commission_rate);

        $this->actingAs($staff)
            ->patch(route('seller.staff.commission.update', $admin), [
                'commission_rate' => 99,
            ])
            ->assertForbidden();
    }

    public function test_seller_can_message_and_cannot_see_unrelated_threads(): void
    {
        $admin = User::query()->where('email', 'seller.admin@rml.test')->firstOrFail();
        $staff = User::query()->where('email', 'seller.staff@rml.test')->firstOrFail();

        $this->actingAs($admin)
            ->post(route('seller.messages.store'), [
                'subject' => 'Lead clarification',
                'body' => 'Please review evidence.',
                'category' => MessageThreadCategory::SellerIssue->value,
            ])
            ->assertRedirect();

        $thread = MessageThread::query()->where('created_by_user_id', $admin->id)->firstOrFail();
        $this->assertSame(MessageThreadStatus::Open, $thread->status);

        $this->actingAs($admin)
            ->post(route('seller.messages.reply', $thread), ['body' => 'Follow-up note.'])
            ->assertRedirect();

        $other = MessageThread::query()->create([
            'thread_reference' => 'THR-9999',
            'subject' => 'Other seller thread',
            'category' => MessageThreadCategory::SellerIssue,
            'status' => MessageThreadStatus::Open,
            'created_by_user_id' => $staff->id,
        ]);

        $this->actingAs($admin)
            ->get(route('seller.messages.show', $other))
            ->assertForbidden();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function leadPayload(int $schemeId, Zone $zone, array $overrides = []): array
    {
        return array_merge([
            'scheme_id' => $schemeId,
            'zone_id' => $zone->id,
            'zone_code' => $zone->code,
            'customer_first_name' => 'Maria',
            'customer_last_name' => 'Garcia',
            'customer_phone' => '+34600111222',
            'customer_whatsapp' => '+34600111222',
            'customer_email' => 'maria@example.com',
            'address_line_1' => 'Calle Mayor 1',
            'city' => 'Madrid',
            'postcode' => '28013',
            'country' => 'ES',
            'property_type' => 'detached',
            'epc_rating' => 'E',
            'size_m2' => 95,
            'notes' => 'Survey notes',
            'metrics' => [
                'zone' => $zone->code,
                'size_m2' => 95,
                'insulation_type' => 'cavity_wall',
                'access_constraints' => 'None',
            ],
            'as_draft' => false,
        ], $overrides);
    }
}
