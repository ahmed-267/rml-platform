<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
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
        $roleNames = [];
        $primaryRole = null;
        $portal = null;
        $permissions = [];

        if ($user) {
            $user->loadMissing('roles:id,name', 'permissions:id,name');
            $roleNames = $user->getRoleNames()->values()->all();
            $primaryRole = isset($roleNames[0]) ? UserRole::tryFrom($roleNames[0]) : null;
            $portal = $primaryRole?->portal();
            $permissions = $user->getAllPermissions()->pluck('name')->values()->all();
        }

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
                    'roles' => $roleNames,
                    'permissions' => $permissions,
                    'portal' => $portal,
                    'primary_role' => $primaryRole?->value,
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
            'translations' => $this->sharedTranslations($portal),
        ];
    }

    /**
     * Share common + portal-specific translation namespaces only.
     *
     * @return array<string, mixed>
     */
    private function sharedTranslations(?string $portal): array
    {
        $shared = [
            'brand' => __('rml.brand'),
            'nav' => __('rml.nav'),
            'common' => __('rml.common'),
            'register' => __('rml.register'),
            'auth' => __('rml.auth'),
            'pending' => __('rml.pending'),
            'profile' => __('rml.profile'),
            'enquiries' => __('rml.enquiries'),
            'landing' => __('rml.landing'),
            'approvals' => __('rml.approvals'),
            'roles' => __('rml.roles'),
            'statuses' => __('rml.statuses'),
            'lead_statuses' => __('rml.lead_statuses'),
            'audit_statuses' => __('rml.audit_statuses'),
            'purchase_statuses' => __('rml.purchase_statuses'),
            'payment_statuses' => __('rml.payment_statuses'),
            'payment_methods' => __('rml.payment_methods'),
            'payments' => __('rml.payments'),
            'whatsapp' => __('rml.whatsapp'),
            'documents' => __('rml.documents'),
            'validation' => __('rml.validation'),
            'rejection' => __('rml.rejection'),
        ];

        return match ($portal) {
            'seller' => $shared + ['seller' => __('rml.seller')],
            'buyer' => $shared + ['buyer' => __('rml.buyer')],
            'admin' => $shared + ['admin' => __('rml.admin')],
            'auditor' => $shared + ['auditor' => __('rml.auditor')],
            default => $shared + [
                'seller' => __('rml.seller'),
                'buyer' => __('rml.buyer'),
                'admin' => __('rml.admin'),
                'auditor' => __('rml.auditor'),
            ],
        };
    }
}
