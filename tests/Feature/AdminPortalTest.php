<?php

namespace Tests\Feature;

use App\Enums\ApprovalStatus;
use App\Enums\LeadStatus;
use App\Enums\PaymentStatus;
use App\Enums\PurchaseStatus;
use App\Mail\SellerLeadStatusMail;
use App\Models\AuditChecklistItem;
use App\Models\Lead;
use App\Models\Payment;
use App\Models\PricingRule;
use App\Models\Purchase;
use App\Models\User;
use App\Models\Zone;
use App\Services\Admin\AdminReportService;
use App\Support\Permissions;
use Database\Seeders\DemoUserSeeder;
use Database\Seeders\DomainDemoSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SchemeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AdminPortalTest extends TestCase
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

    public function test_super_admin_can_access_dashboard(): void
    {
        $admin = User::query()->where('email', 'admin@rml.test')->firstOrFail();

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Dashboard')
                ->has('kpis')
                ->has('pipeline_counts')
                ->has('awaiting_audit_leads')
                ->has('action_queue')
                ->has('financial_snapshot')
                ->has('open_issues_summary'));
    }

    public function test_admin_staff_can_access_dashboard_but_not_settings_or_audit_logs(): void
    {
        $staff = User::query()->where('email', 'staff@rml.test')->firstOrFail();

        $this->actingAs($staff)->get(route('admin.dashboard'))->assertOk();
        $this->actingAs($staff)->get(route('admin.settings.index'))->assertForbidden();
        $this->actingAs($staff)->get(route('admin.audit-logs.index'))->assertForbidden();
    }

    public function test_buyer_seller_auditor_guest_cannot_access_admin(): void
    {
        $buyer = User::query()->where('email', 'buyer@rml.test')->firstOrFail();
        $seller = User::query()->where('email', 'seller.admin@rml.test')->firstOrFail();
        $auditor = User::query()->where('email', 'auditor@rml.test')->firstOrFail();

        $this->actingAs($buyer)->get(route('admin.dashboard'))->assertForbidden();
        $this->actingAs($seller)->get(route('admin.dashboard'))->assertForbidden();
        $this->actingAs($auditor)->get(route('admin.dashboard'))->assertForbidden();

        auth()->logout();
        $this->get(route('admin.dashboard'))->assertRedirect(route('login'));
    }

    public function test_super_admin_can_approve_and_suspend_seller(): void
    {
        $admin = User::query()->where('email', 'admin@rml.test')->firstOrFail();
        $pending = User::query()->where('email', 'pending.seller@rml.test')->firstOrFail();

        $this->actingAs($admin)
            ->post(route('admin.sellers.approve', $pending))
            ->assertRedirect();

        $this->assertSame(ApprovalStatus::Approved, $pending->fresh()->approval_status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'registration.approved']);

        $this->actingAs($admin)
            ->post(route('admin.sellers.suspend', $pending))
            ->assertRedirect();

        $this->assertSame(ApprovalStatus::Suspended, $pending->fresh()->approval_status);
    }

    public function test_admin_staff_cannot_promote_user_to_super_admin(): void
    {
        $staff = User::query()->where('email', 'staff@rml.test')->firstOrFail();
        $this->assertFalse($staff->can(Permissions::MANAGE_USERS));

        $this->actingAs($staff)
            ->get(route('admin.users.index'))
            ->assertForbidden();
    }

    public function test_super_admin_can_accept_lead_audit(): void
    {
        Mail::fake();

        $admin = User::query()->where('email', 'admin@rml.test')->firstOrFail();
        $lead = Lead::query()->where('lead_reference', 'LD-1044')->firstOrFail();

        $this->actingAs($admin)
            ->get(route('admin.leads.audit.show', $lead))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Admin/Leads/Audit')->has('audit'));

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

        $response = $this->actingAs($admin)->post(route('admin.leads.audit.accept', $lead), [
            'buying_price' => 200,
            'selling_price' => 300,
            'override_reason' => 'Manual admin pricing for demo audit',
            'audit_notes' => 'Looks good',
            'checklist' => $checklist,
        ]);

        $response->assertRedirect();
        $lead->refresh();
        $this->assertSame(LeadStatus::Listed, $lead->status);
        $this->assertEqualsWithDelta(300.0, (float) $lead->selling_price, 0.01);
        $this->assertDatabaseHas('audit_logs', ['action' => 'lead.audit_accepted']);
        Mail::assertQueued(SellerLeadStatusMail::class);
    }

    public function test_audit_data_endpoint_returns_json_payload(): void
    {
        $admin = User::query()->where('email', 'admin@rml.test')->firstOrFail();
        $lead = Lead::query()->where('lead_reference', 'LD-1047')->firstOrFail();

        $this->actingAs($admin)
            ->getJson(route('admin.leads.audit.data', $lead))
            ->assertOk()
            ->assertJsonStructure([
                'checklist',
                'pricing' => ['suggested_selling_price', 'buying_price', 'selling_price'],
                'lead' => [
                    'id',
                    'lead_reference',
                    'status',
                    'customer_first_name',
                    'scheme',
                    'zone',
                    'seller',
                    'evidence',
                ],
            ])
            ->assertJsonPath('lead.lead_reference', 'LD-1047');
    }

    public function test_accept_lead_requires_completed_checklist(): void
    {
        $admin = User::query()->where('email', 'admin@rml.test')->firstOrFail();
        $lead = Lead::query()->where('lead_reference', 'LD-1047')->firstOrFail();
        $original = $lead->status;

        $this->actingAs($admin)
            ->from(route('admin.leads.index', ['tab' => 'registered']))
            ->post(route('admin.leads.audit.accept', $lead), [
                'buying_price' => 200,
                'selling_price' => 300,
                'override_reason' => 'Attempt without checklist',
                'checklist' => [],
            ])
            ->assertSessionHasErrors('checklist');

        $this->assertSame($original, $lead->fresh()->status);
    }

    public function test_reject_lead_updates_status_and_emails_seller(): void
    {
        Mail::fake();

        $admin = User::query()->where('email', 'admin@rml.test')->firstOrFail();
        $lead = Lead::query()->where('lead_reference', 'LD-1047')->firstOrFail();

        $this->actingAs($admin)
            ->post(route('admin.leads.audit.reject', $lead), [
                'rejection_reason' => 'Evidence quality is insufficient for listing.',
            ])
            ->assertRedirect();

        $this->assertSame(LeadStatus::Rejected, $lead->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'lead.audit_rejected']);
        Mail::assertQueued(SellerLeadStatusMail::class);
    }

    public function test_request_information_updates_status_and_emails_seller(): void
    {
        Mail::fake();

        $admin = User::query()->where('email', 'admin@rml.test')->firstOrFail();
        $source = Lead::query()->where('lead_reference', 'LD-1047')->firstOrFail();
        $sellerId = $source->submitted_by_user_id
            ?? User::query()->role('seller_company_admin')->value('id');

        $lead = $source->replicate(['lead_reference']);
        $lead->lead_reference = 'LD-AUDIT-INFO';
        $lead->status = LeadStatus::PendingValidation;
        $lead->submitted_by_user_id = $sellerId;
        $lead->save();

        $this->actingAs($admin)
            ->post(route('admin.leads.audit.request-info', $lead), [
                'requested_info' => 'Please upload clearer EPC photos for this property.',
            ])
            ->assertRedirect();

        $this->assertSame(LeadStatus::NeedsMoreInformation, $lead->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'lead.audit_info_requested']);
        Mail::assertQueued(SellerLeadStatusMail::class);
    }

    public function test_leads_bought_sort_and_pagination_preserve_filters(): void
    {
        $admin = User::query()->where('email', 'admin@rml.test')->firstOrFail();

        $this->actingAs($admin)
            ->get(route('admin.leads.index', [
                'tab' => 'registered',
                'search' => 'LD-',
                'sort' => 'reference',
                'direction' => 'asc',
                'per_page' => 25,
                'page' => 1,
            ]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Leads/Index')
                ->where('tab', 'registered')
                ->where('filters.sort', 'reference')
                ->where('filters.direction', 'asc')
                ->where('filters.per_page', 25)
                ->where('filters.search', 'LD-'));
    }

    public function test_leads_sold_sort_and_pagination_work(): void
    {
        $admin = User::query()->where('email', 'admin@rml.test')->firstOrFail();

        $this->actingAs($admin)
            ->get(route('admin.leads.index', [
                'tab' => 'sold',
                'sort' => 'margin',
                'direction' => 'desc',
                'per_page' => 10,
            ]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Leads/Index')
                ->where('tab', 'sold')
                ->where('filters.sort', 'margin')
                ->where('filters.direction', 'desc')
                ->where('filters.per_page', 10));
    }

    public function test_unsafe_sort_field_falls_back_safely(): void
    {
        $admin = User::query()->where('email', 'admin@rml.test')->firstOrFail();

        $this->actingAs($admin)
            ->get(route('admin.leads.index', [
                'tab' => 'registered',
                'sort' => 'customer_email;drop table',
                'direction' => 'asc',
                'per_page' => 10,
            ]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Leads/Index')
                ->where('filters.sort', 'date')
                ->where('filters.direction', 'asc')
                ->where('filters.per_page', 10));
    }

    public function test_reject_lead_requires_reason(): void
    {
        $admin = User::query()->where('email', 'admin@rml.test')->firstOrFail();
        $lead = Lead::query()->where('lead_reference', 'LD-1047')->firstOrFail();

        $this->actingAs($admin)
            ->post(route('admin.leads.audit.reject', $lead), [
                'rejection_reason' => '',
            ])
            ->assertSessionHasErrors('rejection_reason');
    }

    public function test_request_more_information_requires_message(): void
    {
        $admin = User::query()->where('email', 'admin@rml.test')->firstOrFail();
        $lead = Lead::query()->where('lead_reference', 'LD-1047')->firstOrFail();

        $this->actingAs($admin)
            ->post(route('admin.leads.audit.request-info', $lead), [
                'requested_info' => '',
            ])
            ->assertSessionHasErrors('requested_info');
    }

    public function test_mark_buyer_payment_paid_releases_lead_and_marks_sold(): void
    {
        $admin = User::query()->where('email', 'admin@rml.test')->firstOrFail();
        $payment = Payment::query()->where('payment_reference', 'PAY-00092')->firstOrFail();
        $purchase = Purchase::query()->where('purchase_reference', 'PUR-00042')->firstOrFail();
        $lead = Lead::query()->where('lead_reference', 'LD-1041')->firstOrFail();

        $this->assertSame(PaymentStatus::Pending, $payment->status);
        $this->assertSame(LeadStatus::Listed, $lead->status);

        $this->actingAs($admin)
            ->post(route('admin.payments.mark-paid', $payment))
            ->assertRedirect();

        $this->assertSame(PaymentStatus::Paid, $payment->fresh()->status);
        $this->assertSame(PurchaseStatus::Paid, $purchase->fresh()->status);
        $this->assertSame(LeadStatus::Sold, $lead->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'payment.buyer_marked_paid']);

        $buyer = User::query()->where('email', 'buyer@rml.test')->firstOrFail();
        $this->assertTrue($buyer->can('viewCustomerDetails', $lead->fresh()));
    }

    public function test_zone_pricing_update_affects_lead_pricing_service(): void
    {
        $admin = User::query()->where('email', 'admin@rml.test')->firstOrFail();
        $zone = Zone::query()->where('code', 'D1')->firstOrFail();
        $rule = PricingRule::query()
            ->where('zone_id', $zone->id)
            ->where('active', true)
            ->firstOrFail();

        $this->actingAs($admin)
            ->put(route('admin.settings.pricing.update', $rule), [
                'price_per_m2' => 9.5,
                'basic_price' => $rule->basic_price,
                'zone_factor' => $rule->zone_factor,
                'size_factor' => $rule->size_factor,
                'distance_factor' => $rule->distance_factor,
                'active' => true,
            ])
            ->assertRedirect();

        $this->assertEqualsWithDelta(9.5, (float) $rule->fresh()->price_per_m2, 0.01);
        $this->assertDatabaseHas('audit_logs', ['action' => 'settings.pricing_updated']);
    }

    public function test_reports_and_audit_logs_pages_load(): void
    {
        $admin = User::query()->where('email', 'admin@rml.test')->firstOrFail();

        $this->actingAs($admin)
            ->get(route('admin.reports.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Reports/Index')
                ->has('summary')
                ->has('charts.lead_volume')
                ->has('charts.leads_by_zone')
                ->has('charts.leads_by_scheme')
                ->has('charts.revenue_margin')
                ->has('charts.seller_performance')
                ->has('charts.buyer_performance')
                ->has('seller_performance')
                ->has('buyer_performance'));

        $this->actingAs($admin)
            ->get(route('admin.audit-logs.index'))
            ->assertRedirect(route('admin.settings.index', ['tab' => 'logs']));

        $this->actingAs($admin)
            ->get(route('admin.settings.index', ['tab' => 'logs']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Settings/Index')
                ->where('tab', 'logs')
                ->has('logs'));
    }

    public function test_reports_chart_datasets_come_from_seeded_data(): void
    {
        $admin = User::query()->where('email', 'admin@rml.test')->firstOrFail();
        $datasets = app(AdminReportService::class)->build();

        $this->assertGreaterThan(0, $datasets['summary']['total_leads_submitted']);
        $this->assertNotEmpty($datasets['charts']['leads_by_zone']);
        $this->assertNotEmpty($datasets['charts']['leads_by_scheme']);
        $this->assertNotEmpty($datasets['charts']['seller_performance']);
        $this->assertIsArray($datasets['charts']['revenue_margin']);
        $this->assertIsArray($datasets['charts']['buyer_performance']);
        $this->assertIsArray($datasets['charts']['lead_volume']);

        $this->actingAs($admin)
            ->get(route('admin.reports.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Reports/Index')
                ->has('charts.lead_volume')
                ->has('charts.leads_by_zone')
                ->has('charts.leads_by_scheme')
                ->has('charts.revenue_margin')
                ->has('charts.seller_performance')
                ->has('charts.buyer_performance')
                ->where('summary.total_leads_submitted', $datasets['summary']['total_leads_submitted']));
    }

    public function test_non_admin_cannot_access_reports(): void
    {
        $buyer = User::query()->where('email', 'buyer@rml.test')->firstOrFail();
        $seller = User::query()->where('email', 'seller.admin@rml.test')->firstOrFail();

        $this->actingAs($buyer)->get(route('admin.reports.index'))->assertForbidden();
        $this->actingAs($seller)->get(route('admin.reports.index'))->assertForbidden();
    }

    public function test_admin_can_reply_to_message_threads(): void
    {
        $admin = User::query()->where('email', 'admin@rml.test')->firstOrFail();

        $this->actingAs($admin)
            ->get(route('admin.messages.index'))
            ->assertOk();
    }

    public function test_leads_bought_and_sold_pages_load(): void
    {
        $admin = User::query()->where('email', 'admin@rml.test')->firstOrFail();

        $this->actingAs($admin)
            ->get(route('admin.leads.index', ['tab' => 'registered']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Leads/Index')
                ->where('tab', 'registered'));

        $this->actingAs($admin)
            ->get(route('admin.leads.index', ['tab' => 'sold']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Leads/Index')
                ->where('tab', 'sold'));

        $this->actingAs($admin)
            ->get(route('admin.leads-bought.index'))
            ->assertRedirect(route('admin.leads.index', ['tab' => 'registered']));

        $this->actingAs($admin)
            ->get(route('admin.leads-sold.index'))
            ->assertRedirect(route('admin.leads.index', ['tab' => 'sold']));
    }

    public function test_users_hub_tabs_and_legacy_redirects(): void
    {
        $admin = User::query()->where('email', 'admin@rml.test')->firstOrFail();

        $this->actingAs($admin)
            ->get(route('admin.users.index', ['tab' => 'sellers']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Users/Index')
                ->where('tab', 'sellers')
                ->has('sellers'));

        $this->actingAs($admin)
            ->get(route('admin.users.index', ['tab' => 'buyers']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Users/Index')
                ->where('tab', 'buyers')
                ->has('buyers'));

        $this->actingAs($admin)
            ->get(route('admin.sellers.index', ['approval_status' => 'pending']))
            ->assertRedirect(route('admin.users.index', [
                'approval_status' => 'pending',
                'tab' => 'sellers',
            ]));

        $this->actingAs($admin)
            ->get(route('admin.buyers.index'))
            ->assertRedirect(route('admin.users.index', ['tab' => 'buyers']));
    }
}
