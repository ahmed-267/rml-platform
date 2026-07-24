<?php

namespace Tests\Feature;

use App\Models\Lead;
use App\Models\User;
use Database\Seeders\DemoUserSeeder;
use Database\Seeders\DomainDemoSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SchemeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocalisationTest extends TestCase
{
    use RefreshDatabase;

    public function test_landing_page_renders_spanish_after_locale_switch(): void
    {
        $this->from('/')
            ->post('/locale', ['locale' => 'es'])
            ->assertRedirect('/');

        $this->assertSame('es', session('locale'));

        $this->get('/')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Welcome')
                ->where('app.locale', 'es')
                ->where('translations.landing.reveal_title', 'Desde el envío hasta la liberación protegida')
                ->where('translations.nav.buy_leads', 'Comprar leads')
                ->where('translations.landing.cta_buy', 'Comprar leads'));
    }

    public function test_landing_page_renders_french_after_locale_switch(): void
    {
        $this->from('/')
            ->post('/locale', ['locale' => 'fr'])
            ->assertRedirect('/');

        $this->get('/')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('app.locale', 'fr')
                ->where('translations.landing.reveal_title', 'De la soumission à la libération protégée')
                ->where('translations.landing.cta_register', 'S’inscrire'));
    }

    public function test_login_and_register_use_translated_labels(): void
    {
        $this->withSession(['locale' => 'es'])
            ->get('/login')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('app.locale', 'es')
                ->where('translations.auth.sign_in', 'Entrar')
                ->where('translations.auth.login_title', 'Bienvenido de nuevo'));

        $this->withSession(['locale' => 'fr'])
            ->get('/register')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('app.locale', 'fr')
                ->where('translations.register.title', 'Créer un compte'));
    }

    public function test_admin_approvals_route_redirects_to_pending_sellers(): void
    {
        $this->seed([
            RolePermissionSeeder::class,
            DemoUserSeeder::class,
        ]);

        $admin = User::query()->where('email', 'admin@rml.test')->firstOrFail();

        $this->actingAs($admin)
            ->from('/admin/approvals')
            ->post('/locale', ['locale' => 'es'])
            ->assertRedirect('/admin/approvals');

        $this->actingAs($admin->fresh())
            ->get('/admin/approvals')
            ->assertRedirect(route('admin.sellers.index', ['approval_status' => 'pending']));

        $this->actingAs($admin->fresh())
            ->get(route('admin.sellers.index', ['approval_status' => 'pending']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Sellers/Index')
                ->where('app.locale', 'es')
                ->where('translations.admin.sellers.index_title', 'Vendedores')
                ->where('translations.admin.common.approve', 'Aprobar')
                ->where('translations.statuses.pending', 'Pendiente')
                ->where(
                    'translations.roles.seller_company_admin',
                    'Seller Admin',
                ));
    }

    public function test_locale_persists_across_navigation(): void
    {
        $this->from('/')
            ->post('/locale', ['locale' => 'es'])
            ->assertRedirect('/');

        $this->get('/login')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('app.locale', 'es')
                ->where('translations.auth.sign_in', 'Entrar'));

        $this->get('/')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('app.locale', 'es')
                ->where('translations.landing.reveal_title', 'Desde el envío hasta la liberación protegida'));
    }

    public function test_english_is_default_fallback(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('app.locale', 'en')
                ->where('translations.landing.reveal_title', 'From submission to protected release'));
    }

    public function test_seller_portal_translates_in_en_es_fr_and_persists_across_pages(): void
    {
        $this->seed([
            RolePermissionSeeder::class,
            DemoUserSeeder::class,
            SchemeSeeder::class,
            DomainDemoSeeder::class,
        ]);

        $seller = User::query()->where('email', 'seller.admin@rml.test')->firstOrFail();

        $this->actingAs($seller)
            ->get(route('seller.dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Seller/Dashboard')
                ->where('app.locale', 'en')
                ->where('translations.seller.dashboard.title', 'Seller dashboard')
                ->where('translations.seller.nav.submit_lead', 'Submit Lead')
                ->where('translations.lead_statuses.pending_evidence', 'Needs Information'));

        $this->actingAs($seller)
            ->from(route('seller.dashboard'))
            ->post('/locale', ['locale' => 'es'])
            ->assertRedirect(route('seller.dashboard'));

        $this->actingAs($seller->fresh())
            ->get(route('seller.leads.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('app.locale', 'es')
                ->where('translations.seller.leads.index_title', 'Mis leads')
                ->where('translations.seller.dashboard.submit_cta', 'Enviar nuevo lead')
                ->where('translations.lead_statuses.accepted', 'Listado'));

        $this->actingAs($seller)
            ->from(route('seller.leads.index'))
            ->post('/locale', ['locale' => 'fr'])
            ->assertRedirect(route('seller.leads.index'));

        $this->actingAs($seller->fresh())
            ->get(route('seller.payments'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('app.locale', 'fr')
                ->where('translations.seller.payments.title', 'Paiements'));

        $this->actingAs($seller->fresh())
            ->get(route('seller.leads.create'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('app.locale', 'fr')
                ->where('translations.seller.leads.create_title', 'Soumettre un lead'));

        $this->actingAs($seller->fresh())
            ->get(route('seller.audited-leads'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('translations.seller.leads.audited_title', 'Leads audités'));

        $this->actingAs($seller->fresh())
            ->get(route('seller.staff.index', ['tab' => 'commissions']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('translations.seller.staff.commissions_title', 'Commissions du personnel')
                ->where('translations.seller.staff.title', 'Personnel'));

        $this->actingAs($seller->fresh())
            ->get(route('seller.messages.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('translations.seller.messages.title', 'Messages'));

        $this->actingAs($seller->fresh())
            ->get(route('seller.profile'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('app.locale', 'fr')
                ->where('translations.seller.profile.title', 'Profil'));
    }

    public function test_buyer_portal_translates_in_en_es_fr_and_persists_across_pages(): void
    {
        $this->seed([
            RolePermissionSeeder::class,
            DemoUserSeeder::class,
            SchemeSeeder::class,
            DomainDemoSeeder::class,
        ]);

        $buyer = User::query()->where('email', 'buyer@rml.test')->firstOrFail();

        $this->actingAs($buyer)
            ->get(route('buyer.dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Buyer/Dashboard')
                ->where('app.locale', 'en')
                ->where('translations.buyer.dashboard.title', 'Buyer dashboard')
                ->where('translations.buyer.nav.buy_leads', 'Buy Leads')
                ->where('translations.purchase_statuses.pending', 'Pending Payment'));

        $this->actingAs($buyer)
            ->from(route('buyer.dashboard'))
            ->post('/locale', ['locale' => 'es'])
            ->assertRedirect(route('buyer.dashboard'));

        $this->actingAs($buyer->fresh())
            ->get(route('buyer.leads.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('app.locale', 'es')
                ->where('translations.buyer.leads.title', 'Comprar leads')
                ->where('translations.buyer.dashboard.browse_cta', 'Ver leads disponibles'));

        $this->actingAs($buyer)
            ->from(route('buyer.leads.index'))
            ->post('/locale', ['locale' => 'fr'])
            ->assertRedirect(route('buyer.leads.index'));

        $this->actingAs($buyer->fresh())
            ->get(route('buyer.packages.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('app.locale', 'fr')
                ->where('translations.buyer.packages.title', 'Packs de leads'));

        $this->actingAs($buyer->fresh())
            ->get(route('buyer.purchases.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('translations.buyer.purchases.title', 'Leads achetés'));

        $this->actingAs($buyer->fresh())
            ->get(route('buyer.payments'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('translations.buyer.payments.title', 'Paiements'));

        $this->actingAs($buyer->fresh())
            ->get(route('buyer.messages.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('translations.buyer.messages.title', 'Messages'));

        $this->actingAs($buyer->fresh())
            ->get(route('buyer.profile'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('app.locale', 'fr')
                ->where('translations.buyer.profile.title', 'Profil')
                ->where('translations.payment_methods.card', 'Carte'));
    }

    public function test_admin_portal_translates_in_en_es_fr_and_persists_across_pages(): void
    {
        $this->seed([
            RolePermissionSeeder::class,
            DemoUserSeeder::class,
            SchemeSeeder::class,
            DomainDemoSeeder::class,
        ]);

        $admin = User::query()->where('email', 'admin@rml.test')->firstOrFail();

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Dashboard')
                ->where('app.locale', 'en')
                ->where('translations.admin.dashboard.title', 'Admin dashboard')
                ->where('translations.admin.nav.sellers', 'Sellers'));

        $this->actingAs($admin)
            ->from(route('admin.dashboard'))
            ->post('/locale', ['locale' => 'es'])
            ->assertRedirect(route('admin.dashboard'));

        $this->actingAs($admin->fresh())
            ->get(route('admin.sellers.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('app.locale', 'es')
                ->where('translations.admin.sellers.index_title', 'Vendedores')
                ->where('translations.admin.nav.buyers', 'Compradores'));

        $this->actingAs($admin)
            ->from(route('admin.sellers.index'))
            ->post('/locale', ['locale' => 'fr'])
            ->assertRedirect(route('admin.sellers.index'));

        $this->actingAs($admin->fresh())
            ->get(route('admin.leads-bought.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('app.locale', 'fr')
                ->where('translations.admin.nav.leads_bought', 'Leads achetés'));

        $this->actingAs($admin->fresh())
            ->get(route('admin.payments.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('translations.admin.payments.index_title', 'Paiements'));

        $this->actingAs($admin->fresh())
            ->get(route('admin.settings.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('app.locale', 'fr')
                ->where('translations.admin.settings.index_title', 'Paramètres'));

        $this->actingAs($admin->fresh())
            ->get(route('admin.reports.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('app.locale', 'fr')
                ->where('translations.admin.reports.index_title', 'Rapports')
                ->where('translations.admin.reports.chart_lead_volume_title', 'Volume de leads')
                ->where('translations.admin.reports.chart_empty_title', 'Pas encore assez de données')
                ->where('translations.admin.reports.legend_accepted', 'Listés'));

        $this->actingAs($admin)
            ->from(route('admin.reports.index'))
            ->post('/locale', ['locale' => 'es'])
            ->assertRedirect(route('admin.reports.index'));

        $this->actingAs($admin->fresh())
            ->get(route('admin.reports.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('app.locale', 'es')
                ->where('translations.admin.reports.chart_leads_by_zone_title', 'Leads por zona')
                ->where('translations.admin.reports.legend_submitted', 'Pendiente de revisión'));
    }

    public function test_auditor_portal_translates_across_en_es_fr_and_persists(): void
    {
        $this->seed([
            RolePermissionSeeder::class,
            DemoUserSeeder::class,
            SchemeSeeder::class,
            DomainDemoSeeder::class,
        ]);

        $auditor = User::query()->where('email', 'auditor@rml.test')->firstOrFail();

        $this->actingAs($auditor)
            ->get(route('auditor.dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('app.locale', 'en')
                ->where('translations.auditor.dashboard.title', 'Dashboard')
                ->where('translations.auditor.nav.assigned_audits', 'Assigned Audits'));

        $this->actingAs($auditor)
            ->from(route('auditor.dashboard'))
            ->post('/locale', ['locale' => 'es'])
            ->assertRedirect(route('auditor.dashboard'));

        $this->actingAs($auditor->fresh())
            ->get(route('auditor.assigned-audits.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('app.locale', 'es')
                ->where('translations.auditor.assigned.index_title', 'Auditorías asignadas')
                ->where('translations.auditor.nav.messages', 'Mensajes'));

        $this->actingAs($auditor)
            ->from(route('auditor.assigned-audits.index'))
            ->post('/locale', ['locale' => 'fr'])
            ->assertRedirect(route('auditor.assigned-audits.index'));

        $lead = Lead::query()->where('lead_reference', 'LD-1044')->firstOrFail();

        $this->actingAs($auditor->fresh())
            ->get(route('auditor.audits.show', $lead))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('app.locale', 'fr')
                ->where('translations.auditor.audit.title', 'Auditer le lead')
                ->where('translations.auditor.audit.page_title', 'Auditer le lead')
                ->where('translations.auditor.audit.recommend_accept', 'Recommander l’acceptation'));

        $this->actingAs($auditor->fresh())
            ->get(route('auditor.completed-audits.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('translations.auditor.completed.index_title', 'Audits terminés'));

        $this->actingAs($auditor->fresh())
            ->get(route('auditor.messages.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('translations.auditor.messages.index_title', 'Messages'));

        $this->actingAs($auditor->fresh())
            ->get(route('auditor.profile'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('app.locale', 'fr')
                ->where('translations.auditor.profile.title', 'Profil'));
    }
}
