<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Permissions;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LeadHubController extends Controller
{
    public function __construct(
        private readonly LeadBoughtController $registeredLeads,
        private readonly LeadSoldController $soldLeads,
    ) {}

    public function index(Request $request): Response
    {
        abort_unless(
            $request->user()?->can(Permissions::VIEW_LEADS)
                || $request->user()?->hasRole('super_admin'),
            403,
        );

        $tab = $request->string('tab')->toString();
        if (! in_array($tab, ['registered', 'sold'], true)) {
            $tab = 'registered';
        }

        $props = $tab === 'sold'
            ? $this->soldLeads->indexProps($request)
            : $this->registeredLeads->indexProps($request);

        return Inertia::render('Admin/Leads/Index', [
            'tab' => $tab,
            ...$props,
        ]);
    }
}
