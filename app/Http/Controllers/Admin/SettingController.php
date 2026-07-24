<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCommissionRequest;
use App\Http\Requests\Admin\StoreSchemeRequest;
use App\Http\Requests\Admin\UpdateCommissionRequest;
use App\Http\Requests\Admin\UpdateGeneralSettingsRequest;
use App\Http\Requests\Admin\UpdatePricingRequest;
use App\Http\Requests\Admin\UpdateSchemeRequest;
use App\Http\Requests\Admin\UpdateTemplateRequest;
use App\Http\Requests\Admin\UpdateZoneRequest;
use App\Models\AuditLog;
use App\Models\CommissionRule;
use App\Models\PricingRule;
use App\Models\Scheme;
use App\Models\TemplateDocument;
use App\Models\TemplateVersion;
use App\Models\User;
use App\Models\Zone;
use App\Services\AuditLogService;
use App\Support\ListPagination;
use App\Support\ListSort;
use App\Support\Permissions;
use App\Support\SchemeConfig;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class SettingController extends Controller
{
    private const GENERAL_CACHE_KEY = 'rml.platform_settings';

    public function __construct(
        private readonly AuditLogService $auditLogService,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', TemplateDocument::class);

        $tab = $request->string('tab')->toString();
        if (! in_array($tab, ['schemes', 'commissions', 'general', 'logs'], true)) {
            $tab = 'schemes';
        }

        $payload = [
            'tab' => $tab,
            'schemes' => Scheme::query()
                ->with([
                    'zones:id,scheme_id,code,name,active,sort_order',
                    'pricingRules:id,scheme_id,zone_id,price_per_m2,basic_price,zone_factor,size_factor,distance_factor,active',
                ])
                ->withCount(['zones', 'leads', 'pricingRules'])
                ->orderBy('sort_order')
                ->get()
                ->map(fn (Scheme $scheme) => $this->transformScheme($scheme)),
            'zones' => Zone::query()
                ->with('scheme:id,name')
                ->orderBy('sort_order')
                ->get()
                ->map(fn (Zone $zone) => [
                    'id' => $zone->id,
                    'scheme_id' => $zone->scheme_id,
                    'scheme_name' => $zone->scheme?->name,
                    'code' => $zone->code,
                    'name' => $zone->name,
                    'description' => $zone->description,
                    'active' => $zone->active,
                    'sort_order' => $zone->sort_order,
                ]),
            'pricing_rules' => PricingRule::query()
                ->with(['scheme:id,name', 'zone:id,code'])
                ->latest('id')
                ->get()
                ->map(fn (PricingRule $rule) => [
                    'id' => $rule->id,
                    'scheme_id' => $rule->scheme_id,
                    'scheme_name' => $rule->scheme?->name,
                    'zone_id' => $rule->zone_id,
                    'zone_code' => $rule->zone?->code,
                    'price_per_m2' => $rule->price_per_m2 !== null ? (float) $rule->price_per_m2 : null,
                    'basic_price' => $rule->basic_price !== null ? (float) $rule->basic_price : null,
                    'zone_factor' => $rule->zone_factor !== null ? (float) $rule->zone_factor : null,
                    'size_factor' => $rule->size_factor !== null ? (float) $rule->size_factor : null,
                    'distance_factor' => $rule->distance_factor !== null ? (float) $rule->distance_factor : null,
                    'active' => $rule->active,
                    'effective_from' => $rule->effective_from?->toDateString(),
                    'effective_to' => $rule->effective_to?->toDateString(),
                ]),
            'commission_rules' => CommissionRule::query()
                ->orderByDesc('id')
                ->get()
                ->map(fn (CommissionRule $rule) => [
                    'id' => $rule->id,
                    'name' => $rule->name,
                    'applies_to' => $rule->applies_to?->value,
                    'percentage' => $rule->percentage !== null ? (float) $rule->percentage : null,
                    'active' => $rule->active,
                    'notes' => $rule->notes,
                ]),
            'templates' => TemplateDocument::query()
                ->with([
                    'activeVersion:id,template_document_id,version,effective_from,content',
                ])
                ->orderBy('type')
                ->get()
                ->map(fn (TemplateDocument $doc) => [
                    'id' => $doc->id,
                    'type' => $doc->type?->value,
                    'name' => $doc->name,
                    'description' => $doc->description,
                    'active_version' => $doc->activeVersion?->version,
                    'active_version_id' => $doc->active_version_id,
                    'content' => $doc->activeVersion?->content,
                ]),
            'general' => $this->generalSettings(),
            'logs' => null,
            'log_filters' => [
                'action' => $request->input('action'),
                'search' => $request->input('search'),
                'user_id' => $request->input('user_id'),
                'sort' => 'date',
                'direction' => 'desc',
                'per_page' => ListPagination::perPage($request),
            ],
            'log_users' => [],
            'log_actions' => [],
        ];

        if ($tab === 'logs' && $request->user()?->can('viewAny', AuditLog::class)) {
            $logsResult = $this->paginatedLogs($request);
            $payload['logs'] = $logsResult['logs'];
            $payload['log_filters']['sort'] = $logsResult['sort'];
            $payload['log_filters']['direction'] = $logsResult['direction'];
            $payload['log_filters']['per_page'] = $logsResult['per_page'];
            $payload['log_users'] = $this->logFilterUsers();
            $payload['log_actions'] = $this->logFilterActions();
        }

        return Inertia::render('Admin/Settings/Index', $payload);
    }

    public function storeScheme(StoreSchemeRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $scheme = DB::transaction(function () use ($data, $request) {
            $slug = $data['slug'] ?? Str::slug($data['name']);
            if (Scheme::query()->where('slug', $slug)->exists()) {
                $slug .= '-'.Str::lower(Str::random(4));
            }

            $scheme = Scheme::query()->create([
                'name' => $data['name'],
                'slug' => $slug,
                'description' => $data['description'] ?? null,
                'metadata' => $data['metadata'] ?? [],
                'active' => $data['active'] ?? true,
                'sort_order' => $data['sort_order'] ?? ((int) Scheme::query()->max('sort_order') + 1),
            ]);

            $createZones = (($data['metadata']['pricing_basis'] ?? null) === 'zone_m2')
                && ($data['create_default_zones'] ?? true);
            $zonePrices = $data['zone_prices'] ?? [];
            $basePrice = $data['base_price'] ?? $data['price_per_m2'] ?? null;
            $pricePerM2 = $data['price_per_m2'] ?? $basePrice;
            $zoneFactor = $data['zone_factor'] ?? 1.0;
            $sizeFactor = $data['size_factor'] ?? 1.0;
            $distanceFactor = $data['distance_factor'] ?? 1.0;

            if ($createZones) {
                $defaults = [
                    ['code' => 'D1', 'name' => 'Zone D1', 'sort' => 1],
                    ['code' => 'D2', 'name' => 'Zone D2', 'sort' => 2],
                    ['code' => 'E1', 'name' => 'Zone E1', 'sort' => 3],
                    ['code' => 'E2', 'name' => 'Zone E2', 'sort' => 4],
                ];

                foreach ($defaults as $zoneData) {
                    $zone = Zone::query()->create([
                        'scheme_id' => $scheme->id,
                        'code' => $zoneData['code'],
                        'name' => $zoneData['name'],
                        'active' => true,
                        'sort_order' => $zoneData['sort'],
                    ]);

                    $zonePrice = $zonePrices[$zoneData['code']] ?? $pricePerM2;

                    PricingRule::query()->create([
                        'scheme_id' => $scheme->id,
                        'zone_id' => $zone->id,
                        'price_per_m2' => $zonePrice ?? 0,
                        'basic_price' => $basePrice ?? $zonePrice ?? 0,
                        'zone_factor' => $zoneFactor,
                        'size_factor' => $sizeFactor,
                        'distance_factor' => $distanceFactor,
                        'active' => true,
                        'effective_from' => now()->toDateString(),
                    ]);
                }
            } elseif ($basePrice !== null || $pricePerM2 !== null) {
                PricingRule::query()->create([
                    'scheme_id' => $scheme->id,
                    'zone_id' => null,
                    'price_per_m2' => $pricePerM2 ?? 0,
                    'basic_price' => $basePrice ?? $pricePerM2 ?? 0,
                    'zone_factor' => $zoneFactor,
                    'size_factor' => $sizeFactor,
                    'distance_factor' => $distanceFactor,
                    'active' => true,
                    'effective_from' => now()->toDateString(),
                ]);
            }

            $this->auditLogService->log(
                'settings.scheme_created',
                $scheme,
                null,
                $scheme->only(['name', 'slug', 'description', 'active', 'metadata']),
                $request->user(),
            );

            return $scheme;
        });

        return redirect()
            ->route('admin.settings.index', ['tab' => 'schemes'])
            ->with('success', __('rml.admin.settings.scheme_created_flash'));
    }

    public function updateScheme(UpdateSchemeRequest $request, Scheme $scheme): RedirectResponse
    {
        $data = $request->validated();
        $old = $scheme->only(['name', 'description', 'active', 'sort_order', 'metadata']);

        DB::transaction(function () use ($scheme, $data) {
            $scheme->update(collect($data)->only([
                'name',
                'slug',
                'description',
                'active',
                'sort_order',
                'metadata',
            ])->filter(fn ($v, $k) => array_key_exists($k, $data))->all());

            $this->syncSchemePricing($scheme, $data);
        });

        $scheme->refresh();

        $this->auditLogService->log(
            'settings.scheme_updated',
            $scheme,
            $old,
            $scheme->only(['name', 'description', 'active', 'sort_order', 'metadata']),
            $request->user(),
        );

        return back()->with('success', __('rml.admin.settings.scheme_updated_flash'));
    }

    public function destroyScheme(Request $request, Scheme $scheme): RedirectResponse
    {
        $this->authorize('viewAny', TemplateDocument::class);

        if (! ($request->user()?->can(Permissions::MANAGE_SETTINGS) || $request->user()?->hasRole('super_admin'))) {
            abort(403);
        }

        $leadsCount = $scheme->leads()->count();
        $force = $request->boolean('force');

        if ($leadsCount > 0 && ! $force) {
            $scheme->update(['active' => false]);

            $this->auditLogService->log(
                'settings.scheme_deactivated',
                $scheme,
                ['active' => true],
                ['active' => false],
                $request->user(),
            );

            return back()->with('warning', __('rml.admin.settings.scheme_deactivated_flash'));
        }

        if ($leadsCount > 0) {
            return back()->with('error', __('rml.admin.settings.scheme_delete_blocked_flash'));
        }

        $snapshot = $scheme->only(['name', 'slug', 'description', 'active', 'metadata']);

        DB::transaction(function () use ($scheme) {
            $scheme->pricingRules()->delete();
            $scheme->zones()->delete();
            $scheme->delete();
        });

        $this->auditLogService->log(
            'settings.scheme_deleted',
            null,
            $snapshot,
            null,
            $request->user(),
        );

        return back()->with('success', __('rml.admin.settings.scheme_deleted_flash'));
    }

    public function updateZone(UpdateZoneRequest $request, Zone $zone): RedirectResponse
    {
        $old = $zone->only(['name', 'description', 'active', 'sort_order', 'code']);
        $zone->update($request->validated());

        $this->auditLogService->log(
            'settings.zone_updated',
            $zone,
            $old,
            $zone->only(['name', 'description', 'active', 'sort_order', 'code']),
            $request->user(),
        );

        return back()->with('success', __('rml.admin.settings.zone_updated_flash'));
    }

    public function updatePricing(UpdatePricingRequest $request, PricingRule $pricingRule): RedirectResponse
    {
        $old = $pricingRule->only([
            'price_per_m2',
            'basic_price',
            'zone_factor',
            'size_factor',
            'distance_factor',
            'active',
            'effective_from',
            'effective_to',
        ]);
        $pricingRule->update($request->validated());

        $this->auditLogService->log(
            'settings.pricing_updated',
            $pricingRule,
            $old,
            $pricingRule->only([
                'price_per_m2',
                'basic_price',
                'zone_factor',
                'size_factor',
                'distance_factor',
                'active',
                'effective_from',
                'effective_to',
            ]),
            $request->user(),
        );

        return back()->with('success', __('rml.admin.settings.pricing_updated_flash'));
    }

    public function storeCommission(StoreCommissionRequest $request): RedirectResponse
    {
        $rule = CommissionRule::query()->create([
            ...$request->validated(),
            'active' => $request->boolean('active', true),
        ]);

        $this->auditLogService->log(
            'settings.commission_created',
            $rule,
            null,
            $rule->only(['name', 'applies_to', 'percentage', 'active', 'notes']),
            $request->user(),
        );

        return redirect()
            ->route('admin.settings.index', ['tab' => 'commissions'])
            ->with('success', __('rml.admin.settings.commission_created_flash'));
    }

    public function updateCommission(UpdateCommissionRequest $request, CommissionRule $commissionRule): RedirectResponse
    {
        $old = $commissionRule->only(['name', 'applies_to', 'percentage', 'active', 'notes']);
        $commissionRule->update($request->validated());

        $this->auditLogService->log(
            'settings.commission_updated',
            $commissionRule,
            $old,
            $commissionRule->only(['name', 'applies_to', 'percentage', 'active', 'notes']),
            $request->user(),
        );

        return back()->with('success', __('rml.admin.settings.commission_updated_flash'));
    }

    public function destroyCommission(Request $request, CommissionRule $commissionRule): RedirectResponse
    {
        if (! ($request->user()?->can(Permissions::MANAGE_SETTINGS) || $request->user()?->hasRole('super_admin'))) {
            abort(403);
        }

        $old = $commissionRule->only(['name', 'applies_to', 'percentage', 'active', 'notes']);

        if ($request->boolean('deactivate_only', true)) {
            $commissionRule->update(['active' => false]);
            $this->auditLogService->log(
                'settings.commission_deactivated',
                $commissionRule,
                $old,
                $commissionRule->only(['name', 'applies_to', 'percentage', 'active', 'notes']),
                $request->user(),
            );

            return back()->with('success', __('rml.admin.settings.commission_deactivated_flash'));
        }

        $commissionRule->delete();
        $this->auditLogService->log(
            'settings.commission_deleted',
            null,
            $old,
            null,
            $request->user(),
        );

        return back()->with('success', __('rml.admin.settings.commission_deleted_flash'));
    }

    public function updateTemplate(UpdateTemplateRequest $request, TemplateDocument $template): RedirectResponse
    {
        $old = $template->only(['name', 'description', 'active_version_id']);
        $template->fill($request->only(['name', 'description']));
        $template->save();

        if ($request->filled('content')) {
            $version = TemplateVersion::query()->create([
                'template_document_id' => $template->id,
                'version' => $request->input('version', now()->format('Y.m.d.His')),
                'content' => $request->string('content')->toString(),
                'created_by_user_id' => $request->user()->id,
                'active' => true,
                'effective_from' => now()->toDateString(),
            ]);

            if ($request->boolean('activate', true)) {
                $template->update(['active_version_id' => $version->id]);
            }
        }

        $this->auditLogService->log(
            'settings.template_updated',
            $template,
            $old,
            $template->only(['name', 'description', 'active_version_id']),
            $request->user(),
        );

        return back()->with('success', __('rml.admin.settings.template_updated_flash'));
    }

    public function updateGeneral(UpdateGeneralSettingsRequest $request): RedirectResponse
    {
        $current = $this->generalSettings();
        $updated = array_merge($current, $request->validated());
        Cache::forever(self::GENERAL_CACHE_KEY, $updated);

        $this->auditLogService->log(
            'settings.general_updated',
            null,
            $current,
            $updated,
            $request->user(),
        );

        return back()->with('success', __('rml.admin.settings.general_updated_flash'));
    }

    /**
     * @return array<string, mixed>
     */
    private function transformScheme(Scheme $scheme): array
    {
        $zonePrices = [];
        foreach ($scheme->zones as $zone) {
            $rule = $scheme->pricingRules->firstWhere('zone_id', $zone->id);
            $zonePrices[$zone->code] = $rule?->price_per_m2 !== null ? (float) $rule->price_per_m2 : null;
        }

        $primaryRule = $scheme->pricingRules->firstWhere('zone_id', null)
            ?? $scheme->pricingRules->first();

        $metadata = $scheme->metadata ?? [];

        return [
            'id' => $scheme->id,
            'name' => $scheme->name,
            'slug' => $scheme->slug,
            'description' => $scheme->description,
            'metadata' => $metadata,
            'active' => $scheme->active,
            'sort_order' => $scheme->sort_order,
            'zones_count' => $scheme->zones_count,
            'leads_count' => $scheme->leads_count,
            'pricing_rules_count' => $scheme->pricing_rules_count,
            'zones' => $scheme->zones->map(fn (Zone $zone) => [
                'id' => $zone->id,
                'code' => $zone->code,
                'name' => $zone->name,
                'active' => $zone->active,
            ])->values()->all(),
            'zone_prices' => $zonePrices,
            'base_price' => $primaryRule?->basic_price !== null ? (float) $primaryRule->basic_price : null,
            'price_per_m2' => $primaryRule?->price_per_m2 !== null ? (float) $primaryRule->price_per_m2 : null,
            'zone_factor' => $primaryRule?->zone_factor !== null ? (float) $primaryRule->zone_factor : null,
            'size_factor' => $primaryRule?->size_factor !== null ? (float) $primaryRule->size_factor : null,
            'distance_factor' => $primaryRule?->distance_factor !== null ? (float) $primaryRule->distance_factor : null,
            'eligibility_summary' => $metadata['eligibility']['conditions'] ?? null,
            'lead_type' => SchemeConfig::leadType($metadata, $scheme->slug),
            'pricing_basis' => SchemeConfig::pricingBasis($metadata, $scheme->slug),
            'pricing_basis_explanation' => $metadata['pricing_basis_explanation'] ?? null,
            'required_inputs' => SchemeConfig::requiredInputs($metadata),
            'required_inputs_summary' => SchemeConfig::requiredInputsSummary($metadata),
            'requirements_summary' => SchemeConfig::requiredInputsSummary($metadata),
            'pricing_factors' => is_array($metadata['pricing_factors'] ?? null) ? $metadata['pricing_factors'] : [],
            'pricing_summary' => $this->pricingSummary(
                SchemeConfig::pricingBasis($metadata, $scheme->slug),
                $zonePrices,
                $primaryRule,
                is_array($metadata['pricing_factors'] ?? null) ? $metadata['pricing_factors'] : [],
            ),
        ];
    }

    /**
     * @param  array<string, float|null>  $zonePrices
     * @param  array<string, mixed>  $pricingFactors
     */
    private function pricingSummary(
        string $pricingBasis,
        array $zonePrices,
        ?PricingRule $primaryRule,
        array $pricingFactors,
    ): ?string {
        $fromAmount = match ($pricingBasis) {
            'zone_m2' => $this->lowestNumeric(array_values($zonePrices))
                ?? ($primaryRule?->price_per_m2 !== null ? (float) $primaryRule->price_per_m2 : null),
            'window_area_count' => $primaryRule?->price_per_m2 !== null
                ? (float) $primaryRule->price_per_m2
                : (isset($pricingFactors['price_per_window'])
                    ? (float) $pricingFactors['price_per_window']
                    : null),
            'per_lead_kw' => $primaryRule?->price_per_m2 !== null
                ? (float) $primaryRule->price_per_m2
                : ($primaryRule?->basic_price !== null
                    ? (float) $primaryRule->basic_price
                    : (isset($pricingFactors['price_per_kw'])
                        ? (float) $pricingFactors['price_per_kw']
                        : null)),
            'fixed_price' => $primaryRule?->basic_price !== null
                ? (float) $primaryRule->basic_price
                : null,
            default => $primaryRule?->price_per_m2 !== null
                ? (float) $primaryRule->price_per_m2
                : ($primaryRule?->basic_price !== null ? (float) $primaryRule->basic_price : null),
        };

        if ($fromAmount === null) {
            return null;
        }

        $unit = match ($pricingBasis) {
            'window_area_count' => isset($pricingFactors['price_per_window'])
                && $primaryRule?->price_per_m2 === null
                ? '/window'
                : '/m²',
            'fixed_price' => '',
            'per_lead_kw' => $primaryRule?->price_per_m2 !== null
                ? '/m²'
                : (isset($pricingFactors['price_per_kw']) && $primaryRule?->basic_price === null
                    ? '/kW'
                    : ($primaryRule?->basic_price !== null && $primaryRule?->price_per_m2 === null
                        ? ''
                        : '/m²')),
            default => '/m²',
        };

        return 'From €'.number_format($fromAmount, 2).$unit;
    }

    /**
     * @param  list<float|null>  $values
     */
    private function lowestNumeric(array $values): ?float
    {
        $numeric = array_values(array_filter(
            $values,
            fn ($value) => $value !== null && is_numeric($value),
        ));

        return $numeric === [] ? null : (float) min($numeric);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function syncSchemePricing(Scheme $scheme, array $data): void
    {
        $zonePrices = $data['zone_prices'] ?? null;
        $hasFactors = array_key_exists('base_price', $data)
            || array_key_exists('price_per_m2', $data)
            || array_key_exists('zone_factor', $data)
            || array_key_exists('size_factor', $data)
            || array_key_exists('distance_factor', $data);

        if ($zonePrices === null && ! $hasFactors) {
            return;
        }

        $scheme->loadMissing(['zones', 'pricingRules']);

        if (is_array($zonePrices) && $zonePrices !== []) {
            foreach ($scheme->zones as $zone) {
                if (! array_key_exists($zone->code, $zonePrices)) {
                    continue;
                }

                $rule = $scheme->pricingRules->firstWhere('zone_id', $zone->id);
                $payload = array_filter([
                    'price_per_m2' => $zonePrices[$zone->code],
                    'basic_price' => $data['base_price'] ?? null,
                    'zone_factor' => $data['zone_factor'] ?? null,
                    'size_factor' => $data['size_factor'] ?? null,
                    'distance_factor' => $data['distance_factor'] ?? null,
                ], fn ($v) => $v !== null);

                if ($rule) {
                    $rule->update($payload);
                } else {
                    PricingRule::query()->create([
                        'scheme_id' => $scheme->id,
                        'zone_id' => $zone->id,
                        'price_per_m2' => $zonePrices[$zone->code] ?? 0,
                        'basic_price' => $data['base_price'] ?? $zonePrices[$zone->code] ?? 0,
                        'zone_factor' => $data['zone_factor'] ?? 1,
                        'size_factor' => $data['size_factor'] ?? 1,
                        'distance_factor' => $data['distance_factor'] ?? 1,
                        'active' => true,
                        'effective_from' => now()->toDateString(),
                    ]);
                }
            }

            return;
        }

        $rule = $scheme->pricingRules->firstWhere('zone_id', null)
            ?? $scheme->pricingRules->first();

        if (! $rule) {
            return;
        }

        $rule->update(array_filter([
            'price_per_m2' => $data['price_per_m2'] ?? null,
            'basic_price' => $data['base_price'] ?? null,
            'zone_factor' => $data['zone_factor'] ?? null,
            'size_factor' => $data['size_factor'] ?? null,
            'distance_factor' => $data['distance_factor'] ?? null,
        ], fn ($v) => $v !== null));
    }

    /**
     * @return array<string, mixed>
     */
    /**
     * @return array{logs: array<string, mixed>, sort: string, direction: string, per_page: int}
     */
    private function paginatedLogs(Request $request): array
    {
        $query = AuditLog::query()->with('user:id,name,email');

        if ($request->filled('action')) {
            $query->where('action', 'like', '%'.$request->string('action')->toString().'%');
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', (int) $request->input('user_id'));
        }

        if ($request->filled('search')) {
            $search = '%'.$request->string('search')->toString().'%';
            $query->where(function ($q) use ($search) {
                $q->where('action', 'like', $search)
                    ->orWhere('entity_type', 'like', $search)
                    ->orWhereHas('user', fn ($uq) => $uq->where('name', 'like', $search));
            });
        }

        $sortState = ListSort::apply(
            $query,
            $request,
            [
                'date' => 'created_at',
                'action' => 'action',
                'user' => fn (Builder $q, string $direction) => $q->orderBy(
                    DB::table('users')
                        ->select('name')
                        ->whereColumn('users.id', 'audit_logs.user_id')
                        ->limit(1),
                    $direction,
                ),
                'entity' => 'entity_type',
            ],
            'date',
        );

        $perPage = ListPagination::perPage($request);

        $logs = $query
            ->orderBy('id', $sortState['direction'])
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (AuditLog $log) => [
                'id' => $log->id,
                'action' => $log->action,
                'entity_type' => $log->entity_type,
                'entity_label' => $this->entityLabel($log->entity_type),
                'entity_id' => $log->entity_id,
                'user' => $log->user ? [
                    'id' => $log->user->id,
                    'name' => $log->user->name,
                    'email' => $log->user->email,
                ] : null,
                'ip_address' => $log->ip_address,
                'created_at' => $log->created_at?->toIso8601String(),
            ])
            ->toArray();

        return [
            'logs' => $logs,
            'sort' => $sortState['sort'],
            'direction' => $sortState['direction'],
            'per_page' => $perPage,
        ];
    }

    /**
     * @return list<array{id: int, name: string}>
     */
    private function logFilterUsers(): array
    {
        $userIds = AuditLog::query()
            ->whereNotNull('user_id')
            ->distinct()
            ->pluck('user_id');

        return User::query()
            ->whereIn('id', $userIds)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
            ])
            ->all();
    }

    /**
     * @return list<string>
     */
    private function logFilterActions(): array
    {
        return AuditLog::query()
            ->whereNotNull('action')
            ->distinct()
            ->orderBy('action')
            ->pluck('action')
            ->filter()
            ->values()
            ->all();
    }

    private function entityLabel(?string $entityType): ?string
    {
        if ($entityType === null || $entityType === '') {
            return null;
        }

        $basename = class_basename($entityType);

        return $basename !== '' ? $basename : $entityType;
    }

    /**
     * @return array<string, mixed>
     */
    private function generalSettings(): array
    {
        $defaults = config('rml.platform', []);

        return array_merge($defaults, Cache::get(self::GENERAL_CACHE_KEY, []));
    }
}
