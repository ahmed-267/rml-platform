<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'email_verified_at' => $user->email_verified_at,
                    'approval_status' => $user->approval_status?->value,
                    'locale' => $user->locale,
                    'phone' => $user->phone,
                    'roles' => $user->getRoleNames()->values()->all(),
                    'permissions' => $user->getAllPermissions()->pluck('name')->values()->all(),
                    'portal' => $user->portal(),
                    'primary_role' => $user->primaryRole()?->value,
                ] : null,
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'warning' => fn () => $request->session()->get('warning'),
                'info' => fn () => $request->session()->get('info'),
            ],
            'app' => [
                'name' => config('app.name', 'RML Platform'),
                'locale' => app()->getLocale(),
                'locales' => ['en', 'es', 'fr'],
            ],
            'translations' => [
                'brand' => __('rml.brand'),
                'nav' => __('rml.nav'),
                'common' => __('rml.common'),
                'register' => __('rml.register'),
                'auth' => __('rml.auth'),
                'pending' => __('rml.pending'),
                'enquiries' => __('rml.enquiries'),
                'landing' => __('rml.landing'),
                'approvals' => __('rml.approvals'),
                'roles' => __('rml.roles'),
                'statuses' => __('rml.statuses'),
                'seller' => __('rml.seller'),
                'buyer' => __('rml.buyer'),
                'admin' => __('rml.admin'),
                'auditor' => __('rml.auditor'),
                'lead_statuses' => __('rml.lead_statuses'),
                'audit_statuses' => __('rml.audit_statuses'),
                'purchase_statuses' => __('rml.purchase_statuses'),
                'payment_statuses' => __('rml.payment_statuses'),
                'payment_methods' => __('rml.payment_methods'),
                'payments' => __('rml.payments'),
                'whatsapp' => __('rml.whatsapp'),
                'documents' => __('rml.documents'),
            ],
        ];
    }
}
