<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ApprovalStatus;
use App\Enums\CompanyType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreAdminLeadRequest;
use App\Models\Company;
use App\Models\Scheme;
use App\Models\User;
use App\Services\Admin\AdminLeadCreationService;
use App\Support\Permissions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminLeadController extends Controller
{
    public function __construct(
        private readonly AdminLeadCreationService $leadCreation,
    ) {}

    public function create(Request $request): Response
    {
        $this->authorizeCreate($request);

        return Inertia::render('Admin/Leads/Create', [
            'schemes' => Scheme::query()
                ->where('active', true)
                ->with([
                    'fields' => fn ($q) => $q->where('active', true)->orderBy('sort_order'),
                    'zones' => fn ($q) => $q->where('active', true)->orderBy('code'),
                ])
                ->orderBy('sort_order')
                ->get(['id', 'name', 'slug'])
                ->map(fn (Scheme $scheme) => [
                    'id' => $scheme->id,
                    'name' => $scheme->name,
                    'slug' => $scheme->slug,
                    'fields' => $scheme->fields->map(fn ($field) => [
                        'id' => $field->id,
                        'key' => $field->key,
                        'label' => $field->label,
                        'type' => $field->type?->value,
                        'options' => $field->options,
                        'required' => $field->required,
                    ])->values()->all(),
                    'zones' => $scheme->zones->map(fn ($z) => [
                        'id' => $z->id,
                        'code' => $z->code,
                        'name' => $z->name,
                    ])->values()->all(),
                ]),
            'seller_companies' => Company::query()
                ->where('type', CompanyType::Seller->value)
                ->where('approval_status', ApprovalStatus::Approved->value)
                ->orderBy('name')
                ->get(['id', 'name']),
            'seller_agents' => User::query()
                ->whereHas('sellerProfile')
                ->with('sellerProfile:id,user_id,company_id')
                ->orderBy('name')
                ->get(['id', 'name', 'email'])
                ->map(fn (User $user) => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'company_id' => $user->sellerProfile?->company_id,
                ]),
            'sources' => [
                AdminLeadCreationService::SOURCE_RML_INTERNAL,
                AdminLeadCreationService::SOURCE_SELLER_COMPANY,
                AdminLeadCreationService::SOURCE_SELLER_AGENT,
            ],
        ]);
    }

    public function store(StoreAdminLeadRequest $request): RedirectResponse
    {
        $this->authorizeCreate($request);

        $lead = $this->leadCreation->create(
            $request->user(),
            $request->validated(),
            $request->boolean('as_draft'),
            [
                'evidence_photos' => $request->file('evidence_photos'),
                'evidence_video' => $request->file('evidence_video'),
                'evidence_agreement' => $request->file('evidence_agreement'),
                'evidence_eligibility' => $request->file('evidence_eligibility'),
            ],
        );

        $flashKey = $request->boolean('as_draft') ? 'draft_flash' : 'created_flash';

        return redirect()
            ->route('admin.leads-bought.show', $lead)
            ->with('success', __('rml.admin.leads.'.$flashKey));
    }

    private function authorizeCreate(Request $request): void
    {
        $user = $request->user();

        abort_unless(
            $user?->hasRole('super_admin')
                || $user?->can(Permissions::CREATE_ADMIN_LEADS),
            403,
        );
    }
}
