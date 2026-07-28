<?php

namespace Tests\Feature;

use App\Enums\ApprovalStatus;
use App\Enums\CompanyType;
use App\Enums\SellerType;
use App\Enums\UserRole;
use App\Models\BuyerProfile;
use App\Models\Company;
use App\Models\HomeownerEnquiry;
use App\Models\SellerProfile;
use App\Models\User;
use Database\Seeders\DemoUserSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class RegistrationApprovalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            RolePermissionSeeder::class,
            DemoUserSeeder::class,
        ]);

        Mail::fake();
    }

    public function test_seller_and_buyer_registration_pages_render(): void
    {
        $this->get('/register/seller')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Auth/RegisterSeller'));

        $this->get('/register/buyer')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Auth/RegisterBuyer'));
    }

    public function test_company_seller_registration_creates_pending_records(): void
    {
        $response = $this->post('/register/seller', [
            'account_type' => 'company',
            'company_name' => 'Solar Norte SL',
            'name' => 'Ana Garcia',
            'email' => 'ana.seller@example.com',
            'phone' => '+34 600 111 222',
            'whatsapp' => '+34 600 111 222',
            'postcode' => '28001',
            'address' => 'Calle Serrano 10',
            'city' => 'Madrid',
            'country' => 'ES',
            'password' => 'password',
            'password_confirmation' => 'password',
            'agreement' => '1',
            'gdpr' => '1',
        ]);

        $response->assertRedirect(route('pending-approval'));
        $this->assertAuthenticated();

        $user = User::query()->where('email', 'ana.seller@example.com')->firstOrFail();
        $this->assertSame(ApprovalStatus::Pending, $user->approval_status);
        $this->assertTrue($user->hasRole(UserRole::SellerCompanyAdmin->value));

        $company = Company::query()->where('name', 'Solar Norte SL')->firstOrFail();
        $this->assertSame(CompanyType::Seller, $company->type);
        $this->assertSame(ApprovalStatus::Pending, $company->approval_status);

        $profile = SellerProfile::query()->where('user_id', $user->id)->firstOrFail();
        $this->assertSame(SellerType::CompanyAdmin, $profile->seller_type);
        $this->assertSame($company->id, $profile->company_id);
    }

    public function test_individual_agent_registration_creates_profile_without_company(): void
    {
        $this->post('/register/seller', [
            'account_type' => 'individual',
            'name' => 'Luis Agent',
            'email' => 'luis.agent@example.com',
            'phone' => '+34 600 333 444',
            'postcode' => '08001',
            'address' => 'Carrer Mallorca 5',
            'city' => 'Barcelona',
            'country' => 'ES',
            'password' => 'password',
            'password_confirmation' => 'password',
            'agreement' => '1',
            'gdpr' => '1',
        ])->assertRedirect(route('pending-approval'));

        $user = User::query()->where('email', 'luis.agent@example.com')->firstOrFail();
        $this->assertTrue($user->hasRole(UserRole::IndividualSellerAgent->value));

        $profile = SellerProfile::query()->where('user_id', $user->id)->firstOrFail();
        $this->assertSame(SellerType::IndividualAgent, $profile->seller_type);
        $this->assertNull($profile->company_id);
    }

    public function test_buyer_registration_creates_pending_company_and_profile(): void
    {
        $this->post('/register/buyer', [
            'company_name' => 'Install Iberia SL',
            'name' => 'Marta Buyer',
            'email' => 'marta.buyer@example.com',
            'phone' => '+34 600 555 666',
            'whatsapp' => '+34 600 555 666',
            'address' => 'Avenida de America 20',
            'city' => 'Madrid',
            'postcode' => '28028',
            'country' => 'ES',
            'password' => 'password',
            'password_confirmation' => 'password',
            'services_offered' => ['insulation', 'heat_pumps'],
            'preferred_zones' => ['D1', 'E1'],
            'max_distance_km' => 50,
            'agreement' => '1',
            'gdpr' => '1',
        ])->assertRedirect(route('pending-approval'));

        $user = User::query()->where('email', 'marta.buyer@example.com')->firstOrFail();
        $this->assertTrue($user->hasRole(UserRole::BuyerAdmin->value));
        $this->assertSame(ApprovalStatus::Pending, $user->approval_status);

        $company = Company::query()->where('name', 'Install Iberia SL')->firstOrFail();
        $this->assertSame(CompanyType::Buyer, $company->type);

        $profile = BuyerProfile::query()->where('user_id', $user->id)->firstOrFail();
        $this->assertSame(['insulation', 'heat_pumps'], $profile->services_offered);
        $this->assertSame(['D1', 'E1'], $profile->preferred_zones);
    }

    public function test_pending_seller_is_redirected_away_from_dashboard(): void
    {
        $pending = User::query()->where('email', 'pending.seller@rml.test')->firstOrFail();

        $this->post('/login', [
            'email' => 'pending.seller@rml.test',
            'password' => 'password',
        ])->assertRedirect('/pending-approval');

        $this->actingAs($pending)
            ->get('/seller/dashboard')
            ->assertRedirect(route('pending-approval'));

        $this->actingAs($pending)
            ->get('/pending-approval')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Auth/PendingApproval'));
    }

    public function test_super_admin_can_approve_seller_and_audit_log_is_written(): void
    {
        $admin = User::query()->where('email', 'admin@rml.test')->firstOrFail();
        $pending = User::query()->where('email', 'pending.seller@rml.test')->firstOrFail();

        $this->actingAs($admin)
            ->post(route('admin.approvals.approve', $pending))
            ->assertRedirect();

        $pending->refresh();
        $this->assertSame(ApprovalStatus::Approved, $pending->approval_status);
        $this->assertNotNull($pending->approved_at);
        $this->assertSame($admin->id, $pending->approved_by);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'registration.approved',
            'user_id' => $admin->id,
        ]);

        $this->actingAs($pending)
            ->get('/seller/dashboard')
            ->assertOk();
    }

    public function test_rejected_user_cannot_access_portal(): void
    {
        $admin = User::query()->where('email', 'admin@rml.test')->firstOrFail();
        $pending = User::query()->where('email', 'pending.seller@rml.test')->firstOrFail();

        $this->actingAs($admin)
            ->post(route('admin.approvals.reject', $pending), [
                'reason_code' => 'missing_required_documents',
                'comment' => 'Incomplete company documentation provided',
            ])
            ->assertRedirect();

        $pending->refresh();
        $this->assertSame(ApprovalStatus::Rejected, $pending->approval_status);

        $this->actingAs($pending)
            ->get('/seller/dashboard')
            ->assertRedirect(route('pending-approval'));
    }

    public function test_suspended_user_cannot_access_portal(): void
    {
        $admin = User::query()->where('email', 'admin@rml.test')->firstOrFail();
        $seller = User::query()->where('email', 'seller.admin@rml.test')->firstOrFail();

        $this->actingAs($admin)
            ->post(route('admin.approvals.suspend', $seller))
            ->assertRedirect();

        $seller->refresh();
        $this->assertSame(ApprovalStatus::Suspended, $seller->approval_status);

        $this->actingAs($seller)
            ->get('/seller/dashboard')
            ->assertRedirect(route('pending-approval'));
    }

    public function test_approved_buyer_can_access_buyer_dashboard(): void
    {
        $buyer = User::query()->where('email', 'buyer@rml.test')->firstOrFail();

        $this->actingAs($buyer)
            ->get('/buyer/dashboard')
            ->assertOk();
    }

    public function test_homeowner_enquiry_persists(): void
    {
        $this->from('/')
            ->post(route('enquiries.homeowner'), [
                'full_name' => 'Maria Lopez',
                'phone' => '+34 600 000 000',
                'email' => 'maria@example.com',
                'property_address' => 'Calle Mayor 12, Madrid',
                'postcode' => '28013',
                'service' => 'insulation',
                'message' => 'Interested in loft insulation',
                'consent' => '1',
            ])
            ->assertRedirect('/')
            ->assertSessionHas('success');

        $this->assertDatabaseHas('homeowner_enquiries', [
            'name' => 'Maria Lopez',
            'email' => 'maria@example.com',
            'postcode' => '28013',
        ]);

        $this->assertSame(1, HomeownerEnquiry::query()->count());
    }

    public function test_locale_switch_shares_translated_auth_labels(): void
    {
        $this->from('/login')
            ->post('/locale', ['locale' => 'es'])
            ->assertRedirect('/login');

        $this->get('/login')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('app.locale', 'es')
                ->where('translations.auth.sign_in', 'Entrar'));
    }
}
