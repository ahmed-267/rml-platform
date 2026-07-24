<?php

namespace App\Http\Controllers\Auditor;

use App\Enums\AuditDecisionStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auditor\UpdateAuditorProfileRequest;
use App\Services\Auditor\AuditWorkflowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    public function __construct(
        private readonly AuditWorkflowService $workflow,
    ) {}

    public function show(Request $request): Response
    {
        $user = $request->user()->load('roles');
        $base = $this->workflow->assignedAuditsQuery($user);

        return Inertia::render('Auditor/Profile', [
            'profile' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'locale' => $user->locale,
                'approval_status' => $user->approval_status?->value,
                'role' => $user->primaryRole()?->value,
                'permissions' => $user->getAllPermissions()->pluck('name')->values()->all(),
            ],
            'stats' => [
                'assigned' => (clone $base)->count(),
                'in_review' => (clone $base)->where('status', AuditDecisionStatus::InReview->value)->count(),
                'completed' => (clone $base)->whereNotNull('completed_at')->count(),
                'recommended_accept' => (clone $base)->where('status', AuditDecisionStatus::RecommendedAccept->value)->count(),
                'recommended_reject' => (clone $base)->where('status', AuditDecisionStatus::RecommendedReject->value)->count(),
            ],
        ]);
    }

    public function update(UpdateAuditorProfileRequest $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validated();

        $user->fill([
            'name' => $data['name'],
            'phone' => $data['phone'] ?? null,
            'locale' => $data['locale'] ?? $user->locale,
        ])->save();

        if (! empty($data['locale'])) {
            session(['locale' => $data['locale']]);
            app()->setLocale($data['locale']);
        }

        return back()->with('success', __('rml.auditor.profile.updated_flash'));
    }
}
