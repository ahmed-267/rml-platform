<?php

namespace App\Providers;

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Lead;
use App\Models\LeadAudit;
use App\Models\LeadPackage;
use App\Models\LeadSurvey;
use App\Models\MessageThread;
use App\Models\Payment;
use App\Models\Purchase;
use App\Models\TemplateDocument;
use App\Policies\AuditLogPolicy;
use App\Policies\CompanyPolicy;
use App\Policies\LeadAuditPolicy;
use App\Policies\LeadPackagePolicy;
use App\Policies\LeadPolicy;
use App\Policies\LeadSurveyPolicy;
use App\Policies\MessageThreadPolicy;
use App\Policies\PaymentPolicy;
use App\Policies\PurchasePolicy;
use App\Policies\TemplateDocumentPolicy;
use App\Services\Catastro\Contracts\CatastroProviderInterface;
use App\Services\Catastro\Providers\FixtureCatastroProvider;
use App\Services\Catastro\Providers\NationalCatastroProvider;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(CatastroProviderInterface::class, function ($app) {
            $mode = strtolower((string) config('services.catastro.provider_mode', 'live'));

            return $mode === 'fixture'
                ? $app->make(FixtureCatastroProvider::class)
                : $app->make(NationalCatastroProvider::class);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        Gate::policy(Lead::class, LeadPolicy::class);
        Gate::policy(LeadSurvey::class, LeadSurveyPolicy::class);
        Gate::policy(LeadAudit::class, LeadAuditPolicy::class);
        Gate::policy(LeadPackage::class, LeadPackagePolicy::class);
        Gate::policy(Company::class, CompanyPolicy::class);
        Gate::policy(Purchase::class, PurchasePolicy::class);
        Gate::policy(Payment::class, PaymentPolicy::class);
        Gate::policy(MessageThread::class, MessageThreadPolicy::class);
        Gate::policy(AuditLog::class, AuditLogPolicy::class);
        Gate::policy(TemplateDocument::class, TemplateDocumentPolicy::class);
    }
}
