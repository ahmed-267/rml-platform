<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateLeadCoordinatesRequest;
use App\Models\Lead;
use App\Services\LocationService;
use App\Support\Permissions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LeadLocationController extends Controller
{
    public function __construct(
        private readonly LocationService $locationService,
    ) {}

    public function geocode(Request $request, Lead $lead): RedirectResponse
    {
        $this->authorizeLeadLocation($request);

        $this->locationService->geocodeLead($lead);

        return back()->with('success', __('rml.location.geocode_retry_flash'));
    }

    public function updateCoordinates(UpdateLeadCoordinatesRequest $request, Lead $lead): RedirectResponse
    {
        $this->locationService->applyManualLeadCoordinates(
            $lead,
            (float) $request->input('latitude'),
            (float) $request->input('longitude'),
        );

        if ($request->filled('cadastral_reference')) {
            $lead->forceFill([
                'cadastral_reference' => $request->string('cadastral_reference')->toString(),
            ])->save();
        }

        return back()->with('success', __('rml.location.coordinates_updated_flash'));
    }

    private function authorizeLeadLocation(Request $request): void
    {
        $user = $request->user();
        abort_unless(
            $user?->hasRole('super_admin')
                || $user?->can(Permissions::VIEW_LEADS)
                || $user?->can(Permissions::ACCEPT_REJECT_LEADS),
            403,
        );
    }
}
