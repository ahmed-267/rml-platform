<?php

use App\Enums\UserRole;
use App\Http\Controllers\Admin\ApprovalController;
use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\BuyerController as AdminBuyerController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\DomainFoundationController;
use App\Http\Controllers\Admin\LeadAuditController;
use App\Http\Controllers\Admin\LeadBoughtController;
use App\Http\Controllers\Admin\LeadEvidenceController;
use App\Http\Controllers\Admin\LeadSoldController;
use App\Http\Controllers\Admin\MessageController as AdminMessageController;
use App\Http\Controllers\Admin\PaymentController as AdminPaymentController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\SellerController as AdminSellerController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\Auditor\AssignedAuditController;
use App\Http\Controllers\Auditor\AuditController as AuditorAuditController;
use App\Http\Controllers\Auditor\CompletedAuditController;
use App\Http\Controllers\Auditor\DashboardController as AuditorDashboardController;
use App\Http\Controllers\Auditor\MessageController as AuditorMessageController;
use App\Http\Controllers\Auditor\ProfileController as AuditorProfileController;
use App\Http\Controllers\Buyer\DashboardController as BuyerDashboardController;
use App\Http\Controllers\Buyer\LeadController as BuyerLeadController;
use App\Http\Controllers\Buyer\MessageController as BuyerMessageController;
use App\Http\Controllers\Buyer\PackageController as BuyerPackageController;
use App\Http\Controllers\Buyer\PaymentController as BuyerPaymentController;
use App\Http\Controllers\Buyer\ProfileController as BuyerProfileController;
use App\Http\Controllers\Buyer\PurchaseController as BuyerPurchaseController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InvoiceDownloadController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\PendingApprovalController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PublicEnquiryController;
use App\Http\Controllers\Seller\AcceptInvitationController;
use App\Http\Controllers\Seller\AuditedLeadController;
use App\Http\Controllers\Seller\DashboardController as SellerDashboardController;
use App\Http\Controllers\Seller\LeadController as SellerLeadController;
use App\Http\Controllers\Seller\MessageController as SellerMessageController;
use App\Http\Controllers\Seller\PaymentController as SellerPaymentController;
use App\Http\Controllers\Seller\ProfileController as SellerProfileController;
use App\Http\Controllers\Seller\StaffController;
use App\Http\Controllers\Seller\StaffInvitationController;
use App\Http\Controllers\Webhooks\MollieWebhookController;
use App\Http\Controllers\Webhooks\StripeWebhookController;
use App\Support\Permissions;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
    ]);
})->name('home');

Route::post('/webhooks/mollie', MollieWebhookController::class)->name('webhooks.mollie');
Route::post('/stripe/webhook', StripeWebhookController::class)->name('webhooks.stripe');

Route::post('/locale', LocaleController::class)->name('locale.update');

Route::post('/enquiries/homeowner', [PublicEnquiryController::class, 'homeowner'])
    ->name('enquiries.homeowner');

Route::post('/enquiries/contact', [PublicEnquiryController::class, 'contact'])
    ->name('enquiries.contact');

Route::middleware('guest')->group(function () {
    Route::get('/seller/staff/invitations/{token}/accept', [AcceptInvitationController::class, 'show'])
        ->name('seller.staff.invitations.accept');
    Route::post('/seller/staff/invitations/{token}/accept', [AcceptInvitationController::class, 'store'])
        ->name('seller.staff.invitations.accept.store');
});

Route::middleware('auth')->group(function () {
    Route::get('/pending-approval', PendingApprovalController::class)->name('pending-approval');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::middleware(['auth', 'verified', 'approved'])->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::get('/invoices/{invoice}/download', InvoiceDownloadController::class)->name('invoices.download');

    Route::middleware('role:'.UserRole::SuperAdmin->value.'|'.UserRole::AdminStaff->value)
        ->prefix('admin')
        ->name('admin.')
        ->group(function () {
            Route::get('/dashboard', AdminDashboardController::class)->name('dashboard');
            Route::get('/foundation', DomainFoundationController::class)
                ->name('foundation');

            Route::get('/approvals', [ApprovalController::class, 'index'])->name('approvals');
            Route::post('/approvals/{user}/approve', [ApprovalController::class, 'approve'])->name('approvals.approve');
            Route::post('/approvals/{user}/reject', [ApprovalController::class, 'reject'])->name('approvals.reject');
            Route::post('/approvals/{user}/suspend', [ApprovalController::class, 'suspend'])->name('approvals.suspend');

            Route::get('/sellers', [AdminSellerController::class, 'index'])->name('sellers.index');
            Route::get('/sellers/{user}', [AdminSellerController::class, 'show'])->name('sellers.show');
            Route::put('/sellers/{user}', [AdminSellerController::class, 'update'])->name('sellers.update');
            Route::post('/sellers/{user}/approve', [AdminSellerController::class, 'approve'])->name('sellers.approve');
            Route::post('/sellers/{user}/reject', [AdminSellerController::class, 'reject'])->name('sellers.reject');
            Route::post('/sellers/{user}/suspend', [AdminSellerController::class, 'suspend'])->name('sellers.suspend');
            Route::post('/sellers/{user}/reinstate', [AdminSellerController::class, 'reinstate'])->name('sellers.reinstate');
            Route::post('/sellers/{user}/deactivate', [AdminSellerController::class, 'deactivate'])->name('sellers.deactivate');

            Route::get('/buyers', [AdminBuyerController::class, 'index'])->name('buyers.index');
            Route::get('/buyers/{user}', [AdminBuyerController::class, 'show'])->name('buyers.show');
            Route::put('/buyers/{user}', [AdminBuyerController::class, 'update'])->name('buyers.update');
            Route::post('/buyers/{user}/approve', [AdminBuyerController::class, 'approve'])->name('buyers.approve');
            Route::post('/buyers/{user}/reject', [AdminBuyerController::class, 'reject'])->name('buyers.reject');
            Route::post('/buyers/{user}/suspend', [AdminBuyerController::class, 'suspend'])->name('buyers.suspend');
            Route::post('/buyers/{user}/reinstate', [AdminBuyerController::class, 'reinstate'])->name('buyers.reinstate');
            Route::post('/buyers/{user}/deactivate', [AdminBuyerController::class, 'deactivate'])->name('buyers.deactivate');

            Route::get('/users', [AdminUserController::class, 'index'])->name('users.index');
            Route::post('/users', [AdminUserController::class, 'store'])->name('users.store');
            Route::get('/users/{user}', [AdminUserController::class, 'show'])->name('users.show');
            Route::put('/users/{user}', [AdminUserController::class, 'update'])->name('users.update');
            Route::post('/users/{user}/approve', [AdminUserController::class, 'approve'])->name('users.approve');
            Route::post('/users/{user}/reject', [AdminUserController::class, 'reject'])->name('users.reject');
            Route::post('/users/{user}/suspend', [AdminUserController::class, 'suspend'])->name('users.suspend');
            Route::post('/users/{user}/reinstate', [AdminUserController::class, 'reinstate'])->name('users.reinstate');

            Route::get('/leads-bought', [LeadBoughtController::class, 'index'])->name('leads-bought.index');
            Route::get('/leads-bought/{lead}', [LeadBoughtController::class, 'show'])->name('leads-bought.show');

            Route::get('/leads-sold', [LeadSoldController::class, 'index'])->name('leads-sold.index');
            Route::get('/leads-sold/{lead}', [LeadSoldController::class, 'show'])->name('leads-sold.show');

            Route::get('/leads/{lead}/audit', [LeadAuditController::class, 'show'])->name('leads.audit.show');
            Route::get('/leads/{lead}/audit/data', [LeadAuditController::class, 'data'])->name('leads.audit.data');
            Route::post('/leads/{lead}/audit/accept', [LeadAuditController::class, 'accept'])->name('leads.audit.accept');
            Route::post('/leads/{lead}/audit/reject', [LeadAuditController::class, 'reject'])->name('leads.audit.reject');
            Route::post('/leads/{lead}/audit/request-info', [LeadAuditController::class, 'requestInfo'])->name('leads.audit.request-info');
            Route::post('/leads/{lead}/audit/assign', [LeadAuditController::class, 'assign'])->name('leads.audit.assign');
            Route::get('/leads/evidence/{evidence}/view', [LeadEvidenceController::class, 'view'])->name('leads.evidence.view');
            Route::get('/leads/evidence/{evidence}/download', [LeadEvidenceController::class, 'download'])->name('leads.evidence.download');

            Route::get('/payments', [AdminPaymentController::class, 'index'])->name('payments.index');
            Route::post('/payments/{payment}/mark-paid', [AdminPaymentController::class, 'markPaid'])->name('payments.mark-paid');
            Route::post('/payments/{payment}/mark-failed', [AdminPaymentController::class, 'markFailed'])->name('payments.mark-failed');
            Route::post('/payouts/{payout}/mark-paid', [AdminPaymentController::class, 'markPayoutPaid'])->name('payouts.mark-paid');
            Route::post('/commissions/{commission}/mark-paid', [AdminPaymentController::class, 'markCommissionPaid'])->name('commissions.mark-paid');
            Route::post('/payments/{payment}/cancel', [AdminPaymentController::class, 'cancel'])->name('payments.cancel');
            Route::post('/payments/{payment}/resend-email', [AdminPaymentController::class, 'resendEmail'])->name('payments.resend-email');
            Route::post('/payments/{payment}/resend-whatsapp', [AdminPaymentController::class, 'resendWhatsApp'])->name('payments.resend-whatsapp');
            Route::post('/invoices/{invoice}/regenerate', [AdminPaymentController::class, 'regenerateInvoice'])->name('invoices.regenerate');

            Route::get('/messages', [AdminMessageController::class, 'index'])->name('messages.index');
            Route::get('/messages/{thread}', [AdminMessageController::class, 'show'])->name('messages.show');
            Route::post('/messages/{thread}/reply', [AdminMessageController::class, 'reply'])->name('messages.reply');
            Route::patch('/messages/{thread}/status', [AdminMessageController::class, 'updateStatus'])->name('messages.status');

            Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
            Route::get('/reports/export/csv', [ReportController::class, 'exportCsv'])->name('reports.export.csv');
            Route::get('/reports/export/pdf', [ReportController::class, 'exportPdf'])->name('reports.export.pdf');

            Route::middleware('permission:'.Permissions::MANAGE_SETTINGS)->group(function () {
                Route::get('/settings', [SettingController::class, 'index'])->name('settings.index');
                Route::post('/settings/schemes', [SettingController::class, 'storeScheme'])->name('settings.schemes.store');
                Route::put('/settings/schemes/{scheme}', [SettingController::class, 'updateScheme'])->name('settings.schemes.update');
                Route::delete('/settings/schemes/{scheme}', [SettingController::class, 'destroyScheme'])->name('settings.schemes.destroy');
                Route::put('/settings/zones/{zone}', [SettingController::class, 'updateZone'])->name('settings.zones.update');
                Route::put('/settings/pricing/{pricingRule}', [SettingController::class, 'updatePricing'])->name('settings.pricing.update');
                Route::post('/settings/commissions', [SettingController::class, 'storeCommission'])->name('settings.commissions.store');
                Route::put('/settings/commissions/{commissionRule}', [SettingController::class, 'updateCommission'])->name('settings.commissions.update');
                Route::delete('/settings/commissions/{commissionRule}', [SettingController::class, 'destroyCommission'])->name('settings.commissions.destroy');
                Route::put('/settings/templates/{template}', [SettingController::class, 'updateTemplate'])->name('settings.templates.update');
                Route::put('/settings/general', [SettingController::class, 'updateGeneral'])->name('settings.general.update');
            });

            Route::middleware('permission:'.Permissions::VIEW_AUDIT_LOGS)->group(function () {
                Route::get('/audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');
                Route::get('/audit-logs/{auditLog}', [AuditLogController::class, 'show'])->name('audit-logs.show');
            });
        });

    Route::middleware('role:'.UserRole::SellerCompanyAdmin->value.'|'.UserRole::SellerStaff->value.'|'.UserRole::IndividualSellerAgent->value)
        ->prefix('seller')
        ->name('seller.')
        ->group(function () {
            Route::get('/dashboard', SellerDashboardController::class)->name('dashboard');

            Route::get('/leads', [SellerLeadController::class, 'index'])->name('leads.index');
            Route::get('/leads/create', [SellerLeadController::class, 'create'])->name('leads.create');
            Route::post('/leads', [SellerLeadController::class, 'store'])->name('leads.store');
            Route::get('/leads/{lead}/edit', [SellerLeadController::class, 'edit'])->name('leads.edit');
            Route::get('/leads/{lead}', [SellerLeadController::class, 'show'])->name('leads.show');
            Route::post('/leads/{lead}/evidence', [SellerLeadController::class, 'updateEvidence'])->name('leads.evidence');
            Route::get('/leads/evidence/{evidence}/view', [LeadEvidenceController::class, 'view'])->name('leads.evidence.view');
            Route::get('/leads/evidence/{evidence}/download', [LeadEvidenceController::class, 'download'])->name('leads.evidence.download');

            Route::get('/audited-leads', [AuditedLeadController::class, 'index'])->name('audited-leads');

            Route::get('/payments', [SellerPaymentController::class, 'index'])->name('payments');
            Route::put('/payments/bank-details', [SellerPaymentController::class, 'updateBankDetails'])->name('payments.bank-details');
            Route::patch('/payments/bank-details', [SellerPaymentController::class, 'updateBankDetails']);

            Route::get('/staff', [StaffController::class, 'index'])
                ->middleware('permission:'.Permissions::MANAGE_SELLER_STAFF.'|'.Permissions::MANAGE_STAFF_COMMISSIONS)
                ->name('staff.index');
            Route::patch('/staff/{user}/commission', [StaffController::class, 'updateCommission'])
                ->middleware('permission:'.Permissions::MANAGE_STAFF_COMMISSIONS)
                ->name('staff.commission.update');

            Route::get('/staff-commissions', function () {
                return redirect()->route('seller.staff.index', ['tab' => 'commissions']);
            })
                ->middleware('permission:'.Permissions::MANAGE_STAFF_COMMISSIONS.'|'.Permissions::MANAGE_SELLER_STAFF)
                ->name('staff-commissions');

            Route::get('/staff/invite', function () {
                return redirect()->route('seller.staff.index', ['tab' => 'invite']);
            })
                ->middleware('permission:'.Permissions::MANAGE_SELLER_STAFF)
                ->name('staff.invite');
            Route::post('/staff/invite', [StaffInvitationController::class, 'store'])
                ->middleware('permission:'.Permissions::MANAGE_SELLER_STAFF)
                ->name('staff.invite.store');
            Route::delete('/staff/invitations/{invitation}', [StaffInvitationController::class, 'destroy'])
                ->middleware('permission:'.Permissions::MANAGE_SELLER_STAFF)
                ->name('staff.invitations.destroy');

            Route::get('/messages', [SellerMessageController::class, 'index'])->name('messages.index');
            Route::post('/messages', [SellerMessageController::class, 'store'])->name('messages.store');
            Route::get('/messages/{thread}', [SellerMessageController::class, 'show'])->name('messages.show');
            Route::post('/messages/{thread}/reply', [SellerMessageController::class, 'reply'])->name('messages.reply');

            Route::get('/profile', [SellerProfileController::class, 'show'])->name('profile');
            Route::put('/profile', [SellerProfileController::class, 'update'])->name('profile.update');
        });

    Route::middleware('role:'.UserRole::BuyerAdmin->value)
        ->prefix('buyer')
        ->name('buyer.')
        ->group(function () {
            Route::get('/dashboard', BuyerDashboardController::class)->name('dashboard');

            Route::get('/leads', [BuyerLeadController::class, 'index'])->name('leads.index');
            Route::post('/leads/purchase', [BuyerLeadController::class, 'storePurchase'])->name('leads.purchase');

            Route::get('/packages', [BuyerPackageController::class, 'index'])->name('packages.index');
            Route::post('/packages/preview', [BuyerPackageController::class, 'preview'])->name('packages.preview');
            Route::post('/packages/purchase', [BuyerPackageController::class, 'storePurchase'])->name('packages.purchase');

            Route::get('/purchases', [BuyerPurchaseController::class, 'index'])->name('purchases.index');
            Route::get('/purchases/{purchase}', [BuyerPurchaseController::class, 'show'])->name('purchases.show');
            Route::post('/purchases/{purchase}/whatsapp', [BuyerPurchaseController::class, 'sendWhatsApp'])->name('purchases.whatsapp');

            Route::get('/payments', [BuyerPaymentController::class, 'index'])->name('payments');
            Route::post('/payments/{payment}/pay', [BuyerPaymentController::class, 'pay'])->name('payments.pay');
            Route::get('/payments/{payment}/return', [BuyerPaymentController::class, 'returnFromProvider'])->name('payments.return');
            Route::get('/payments/{payment}/success', [BuyerPaymentController::class, 'success'])->name('payments.success');
            Route::get('/payments/{payment}/cancelled', [BuyerPaymentController::class, 'cancelled'])->name('payments.cancelled');
            Route::post('/payments/{payment}/cancel', [BuyerPaymentController::class, 'cancel'])->name('payments.cancel');

            Route::get('/messages', [BuyerMessageController::class, 'index'])->name('messages.index');
            Route::post('/messages', [BuyerMessageController::class, 'store'])->name('messages.store');
            Route::get('/messages/{thread}', [BuyerMessageController::class, 'show'])->name('messages.show');
            Route::post('/messages/{thread}/reply', [BuyerMessageController::class, 'reply'])->name('messages.reply');

            Route::get('/profile', [BuyerProfileController::class, 'show'])->name('profile');
            Route::put('/profile', [BuyerProfileController::class, 'update'])->name('profile.update');
        });

    Route::middleware('auditor')
        ->prefix('auditor')
        ->name('auditor.')
        ->group(function () {
            Route::get('/dashboard', AuditorDashboardController::class)->name('dashboard');

            Route::get('/assigned-audits', [AssignedAuditController::class, 'index'])->name('assigned-audits.index');
            Route::get('/completed-audits', [CompletedAuditController::class, 'index'])->name('completed-audits.index');

            Route::get('/audits', [AuditorAuditController::class, 'index'])->name('audits.index');
            Route::get('/audits/{lead}', [AuditorAuditController::class, 'show'])->name('audits.show');
            Route::post('/audits/{lead}/checklist', [AuditorAuditController::class, 'saveChecklist'])->name('audits.checklist');
            Route::post('/audits/{lead}/recommend-accept', [AuditorAuditController::class, 'recommendAccept'])->name('audits.recommend-accept');
            Route::post('/audits/{lead}/recommend-reject', [AuditorAuditController::class, 'recommendReject'])->name('audits.recommend-reject');
            Route::post('/audits/{lead}/request-info', [AuditorAuditController::class, 'requestInfo'])->name('audits.request-info');

            Route::get('/messages', [AuditorMessageController::class, 'index'])->name('messages.index');
            Route::post('/messages', [AuditorMessageController::class, 'store'])->name('messages.store');
            Route::get('/messages/{thread}', [AuditorMessageController::class, 'show'])->name('messages.show');
            Route::post('/messages/{thread}/reply', [AuditorMessageController::class, 'reply'])->name('messages.reply');

            Route::get('/profile', [AuditorProfileController::class, 'show'])->name('profile');
            Route::put('/profile', [AuditorProfileController::class, 'update'])->name('profile.update');
        });
});

require __DIR__.'/auth.php';
