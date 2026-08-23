<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Scheme;
use App\Services\Admin\AdminMapDataService;
use App\Support\LeadStatusPresentation;
use App\Support\Permissions;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LeadHubController extends Controller
{
    public function __construct(
        private readonly LeadBoughtController $registeredLeads,
        private readonly LeadSoldController $soldLeads,
        private readonly LeadPackageController $packages,
        private readonly AdminMapDataService $mapDataService,
    ) {}

    public function index(Request $request): Response
    {
        abort_unless(
            $request->user()?->can(Permissions::VIEW_LEADS)
                || $request->user()?->hasRole('super_admin'),
            403,
        );

        $tab = $request->string('tab')->toString();
        if (! in_array($tab, ['registered', 'sold', 'packages'], true)) {
            $tab = 'registered';
        }

        $view = $request->string('view')->toString();
        if ($tab === 'packages' || ! in_array($view, ['table', 'map'], true)) {
            $view = 'table';
        }

        $props = match ($tab) {
            'sold' => $this->soldLeads->indexProps($request),
            'packages' => $this->packages->indexProps($request),
            default => $this->registeredLeads->indexProps($request),
        };

        $props['view'] = $view;
        $props['map'] = $view === 'map' && $tab !== 'packages'
            ? $this->mapDataService->forLeadsPage($request, $tab)
            : null;

        if ($view === 'map' && is_array($props['map'] ?? null)) {
            $map = $props['map'];

            if ($tab === 'sold') {
                $mapFilterOptions = [
                    'statuses' => [],
                    'zones' => [],
                    'installers' => $map['installer_options'] ?? [],
                    'payment_statuses' => $map['payment_status_options'] ?? PaymentStatus::values(),
                    'release_statuses' => $map['release_status_options'] ?? ['released', 'pending_release'],
                ];

                if (empty($props['filterOptions']['schemes'] ?? null)) {
                    $mapFilterOptions['schemes'] = Scheme::query()
                        ->where('active', true)
                        ->orderBy('sort_order')
                        ->get(['id', 'name']);
                }
            } else {
                $mapFilterOptions = [
                    'statuses' => $map['status_options']
                        ?? LeadStatusPresentation::visibleValuesForMapTab('registered'),
                    'installers' => [],
                    'match_installers' => $map['installer_options'] ?? [],
                    'radius_options_km' => $map['radius_options_km'] ?? [],
                    'payment_statuses' => [],
                    'release_statuses' => [],
                ];
            }

            $props['filterOptions'] = array_merge(
                $props['filterOptions'] ?? [],
                $mapFilterOptions,
            );
            $props['filters'] = array_merge(
                $props['filters'] ?? [],
                $map['filters'] ?? [],
                ['view' => 'map'],
            );
        }

        return Inertia::render('Admin/Leads/Index', [
            'tab' => $tab,
            ...$props,
        ]);
    }
}
