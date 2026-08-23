<?php

namespace Database\Seeders;

use App\Enums\CadastralLookupStatus;
use App\Enums\GeocodingStatus;
use App\Enums\LeadStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Enums\PurchaseItemType;
use App\Enums\PurchaseStatus;
use App\Models\Company;
use App\Models\Lead;
use App\Models\Payment;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Scheme;
use App\Models\User;
use App\Models\Zone;
use App\Services\Catastro\CatastroReferenceNormalizer;
use App\Services\LeadPricingService;
use App\Support\ReferenceGenerator;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Idempotent Catastro demo leads for live + fixture UI testing.
 * Does NOT call the external Catastro provider.
 *
 * Identity: lead_source=catastro_demo + notes=label (scenario label).
 * Lead references use ReferenceGenerator::lead() (LD-NNNN); legacy LD-CT-*
 * rows are migrated in place on reseed (no deletes).
 */
class CatastroDemoSeeder extends Seeder
{
    /**
     * Legacy user-facing refs from earlier demo seeds (local/demo cleanup only).
     *
     * @var array<string, string>
     */
    private const LEGACY_LEAD_REFERENCES = [
        'live_expected_match' => 'LD-CT-LIVE-MATCH',
        'live_mismatch_review' => 'LD-CT-LIVE-AREA',
        'live_sold_lead' => 'LD-CT-LIVE-SOLD',
        'fixture_successful_match' => 'LD-CT-DEMO-MATCH',
        'fixture_partial_match' => 'LD-CT-DEMO-PARTIAL',
        'fixture_mismatch' => 'LD-CT-DEMO-MISMATCH',
        'fixture_multiple_results' => 'LD-CT-DEMO-MULTI',
        'fixture_service_unavailable' => 'LD-CT-DEMO-UNAVAIL',
        'fixture_regional_provider' => 'LD-CT-DEMO-REGIONAL',
    ];

    /**
     * Previous scenario labels (notes) so reseed does not create duplicates
     * when a demo label is renamed.
     *
     * @var array<string, list<string>>
     */
    private const LABEL_ALIASES = [
        'Catastro Live — Mismatch Review' => [
            'Catastro Live — Area Review',
        ],
    ];

    private const LEGACY_PAYMENT_REFERENCE = 'PAY-CT-SOLD-01';

    private const LEGACY_PURCHASE_REFERENCE = 'PUR-CT-SOLD-01';

    public function run(): void
    {
        if (! Schema::hasTable('leads') || ! Schema::hasColumn('leads', 'cadastral_reference')) {
            $this->command?->warn('CatastroDemoSeeder skipped: leads / cadastral columns missing. Run migrations first.');

            return;
        }

        $definitions = require database_path('seeders/data/catastro-demo-properties.php');
        $seller = User::query()->where('email', 'seller.admin@rml.test')->first()
            ?? User::query()->where('email', 'seller.staff@rml.test')->first();
        $buyer = User::query()->where('email', 'buyer@rml.test')->first();
        $sellerCompany = Company::query()->where('name', 'Verde Energía Madrid SL')->first()
            ?? $seller?->sellerProfile?->company;
        $buyerCompany = Company::query()->where('name', 'CalorHogar Instalaciones SL')->first()
            ?? $buyer?->buyerProfile?->company;

        $scheme = Scheme::query()->where('slug', 'insulation')->first() ?? Scheme::query()->first();
        $zone = $scheme
            ? Zone::query()->where('scheme_id', $scheme->id)->where('code', 'D1')->first()
                ?? Zone::query()->where('scheme_id', $scheme->id)->first()
            : null;

        if (! $seller || ! $sellerCompany || ! $scheme || ! $zone) {
            $this->command?->warn('CatastroDemoSeeder skipped: demo seller/scheme/zone missing. Run DomainDemoSeeder first.');

            return;
        }

        $this->migrateAllLegacyLeadReferences();
        $this->backfillDomainDemoCadastralReferences();

        $pricing = app(LeadPricingService::class);
        $created = [];
        $updated = [];
        $warnings = [];

        $this->command?->info('Catastro provider mode: '.config('services.catastro.provider_mode', 'live'));
        $this->command?->info('Configured live demo references:');
        $this->command?->line('  MATCH: '.((string) config('services.catastro.demo.match_reference') ?: '(missing)'));
        $this->command?->line('  AREA:  '.((string) config('services.catastro.demo.area_review_reference') ?: '(missing)'));
        $this->command?->line('  SOLD:  '.((string) config('services.catastro.demo.sold_reference') ?: '(missing)'));

        foreach (array_merge($definitions['live'] ?? [], $definitions['fixtures'] ?? []) as $def) {
            $scenario = (string) ($def['scenario'] ?? '');
            $label = (string) ($def['label'] ?? '');
            $isLive = str_starts_with($scenario, 'live_');
            $cadastralReference = $this->resolveReference($def, $isLive, $warnings);

            if ($isLive && $cadastralReference === null) {
                $this->command?->warn("Skipping {$label}: no verified live cadastral reference configured.");

                continue;
            }

            $existing = $this->findExistingDemoLead($def);
            $leadReference = $this->resolveLeadReference($existing);

            $status = LeadStatus::from((string) $def['status']);
            $attrs = [
                'lead_reference' => $leadReference,
                'submitted_by_user_id' => $seller->id,
                'seller_company_id' => $sellerCompany->id,
                'scheme_id' => $scheme->id,
                'zone_id' => $zone->id,
                'status' => $status,
                'lead_source' => 'catastro_demo',
                'notes' => $label,
                'customer_first_name' => 'Demo',
                'customer_last_name' => 'Catastro',
                'customer_phone' => '+34600990001',
                'customer_whatsapp' => '+34600990001',
                'customer_email' => 'catastro.'.Str::slug($scenario).'@example.es',
                'address_line_1' => $def['address_line_1'],
                'city' => $def['city'],
                'postcode' => $def['postcode'],
                'country' => $def['country_code'] ?? 'ES',
                'latitude' => $def['latitude'],
                'longitude' => $def['longitude'],
                'formatted_address' => trim(implode(', ', array_filter([
                    $def['address_line_1'],
                    $def['postcode'],
                    $def['city'],
                    $def['province'] ?? null,
                    'Spain',
                ]))),
                'geocoding_status' => GeocodingStatus::Successful,
                'geocoded_at' => now()->subDay(),
                'geocoding_error' => null,
                'property_type' => $def['property_type'] ?? 'detached',
                'epc_rating' => 'E',
                'size_m2' => $def['size_m2'] ?? $def['submitted_area_m2'] ?? null,
                'submitted_property_area_m2' => $def['submitted_area_m2'] ?? null,
                'cadastral_reference' => $cadastralReference,
                'cadastral_lookup_status' => CadastralLookupStatus::NotLookedUp,
                'cadastral_verified_at' => null,
                'catastro_status' => 'not_checked',
                'catastro_provider' => null,
                'catastro_checked_at' => null,
                'catastro_matched_address' => null,
                'catastro_municipality' => null,
                'catastro_province' => null,
                'catastro_postcode' => null,
                'catastro_property_type' => null,
                'catastro_built_area' => null,
                'catastro_construction_year' => null,
                'catastro_raw_response_json' => null,
                'catastro_warnings_json' => null,
                'catastro_error_message' => null,
                'accepted_at' => now()->subDays(5),
                'listed_at' => now()->subDays(2),
                'sold_at' => $status === LeadStatus::Sold ? now()->subDay() : null,
            ];

            if ($existing) {
                $existing->forceFill($attrs)->save();
                $lead = $existing->fresh();
            } else {
                $lead = Lead::query()->create($attrs);
            }

            // Preserve sold commercial fields when re-seeding an already-sold demo lead.
            if ($status === LeadStatus::Sold && $existing && $existing->selling_price !== null) {
                $lead->forceFill([
                    'selling_price' => $existing->selling_price,
                    'buying_price' => $existing->buying_price,
                    'expected_margin' => $existing->expected_margin,
                ])->save();
            } else {
                $calc = $pricing->calculate($lead);
                if ($calc['selling_price'] !== null) {
                    $selling = $calc['selling_price'];
                    $buying = round($selling * 0.65, 2);
                    $lead->forceFill([
                        'selling_price' => $selling,
                        'buying_price' => $buying,
                        'expected_margin' => round($selling - $buying, 2),
                    ])->save();
                }
            }

            // Live / fixture "Not checked" scenarios must not keep stale current snapshots.
            if (! ($def['create_snapshot'] ?? false)) {
                $lead->catastroSnapshots()->delete();
            }

            if ($status === LeadStatus::Sold && $buyer && $buyerCompany) {
                $this->ensureSoldPurchase($lead, $buyer, $buyerCompany);
            }

            $line = $lead->lead_reference.' ('.$label.')';
            if ($existing) {
                $updated[] = $line;
            } else {
                $created[] = $line;
            }
        }

        foreach ($warnings as $warning) {
            $this->command?->warn($warning);
        }

        $this->command?->info('Catastro demo leads created: '.count($created));
        foreach ($created as $line) {
            $this->command?->line('  + '.$line);
        }
        $this->command?->info('Catastro demo leads updated: '.count($updated));
        foreach ($updated as $line) {
            $this->command?->line('  ~ '.$line);
        }
        $this->command?->info('Fixture scenarios require CATASTRO_PROVIDER_MODE=fixture for deterministic lookups.');
        $this->command?->info('Live scenarios use Check Catastro against the real provider when mode=live.');
    }

    /**
     * @param  array<string, mixed>  $def
     */
    private function findExistingDemoLead(array $def): ?Lead
    {
        $label = (string) ($def['label'] ?? '');
        $scenario = (string) ($def['scenario'] ?? '');

        if ($label !== '') {
            $byIdentity = Lead::query()
                ->where('lead_source', 'catastro_demo')
                ->where('notes', $label)
                ->first();

            if ($byIdentity) {
                return $byIdentity;
            }

            foreach (self::LABEL_ALIASES[$label] ?? [] as $alias) {
                $byAlias = Lead::query()
                    ->where('lead_source', 'catastro_demo')
                    ->where('notes', $alias)
                    ->first();

                if ($byAlias) {
                    return $byAlias;
                }
            }
        }

        $legacyRef = self::LEGACY_LEAD_REFERENCES[$scenario] ?? null;
        if ($legacyRef) {
            return Lead::query()->where('lead_reference', $legacyRef)->first();
        }

        return null;
    }

    private function resolveLeadReference(?Lead $existing): string
    {
        if ($existing && is_string($existing->lead_reference) && $existing->lead_reference !== '') {
            if (! $this->isLegacyLeadReference($existing->lead_reference)) {
                return $existing->lead_reference;
            }
        }

        return ReferenceGenerator::lead();
    }

    /**
     * Local/demo-only cleanup. Rename every historical Catastro-prefixed lead
     * reference in place; never delete or touch normal production references.
     */
    private function migrateAllLegacyLeadReferences(): void
    {
        Lead::query()
            ->where(function ($query): void {
                $query->where('lead_source', 'catastro_demo')
                    ->orWhere('lead_reference', 'like', 'LD-CT-%');
            })
            ->where('lead_reference', 'like', 'LD-CT-%')
            ->orderBy('id')
            ->each(function (Lead $lead): void {
                $lead->forceFill([
                    'lead_reference' => ReferenceGenerator::lead(),
                ])->save();
            });
    }

    /**
     * Fill empty cadastral references on known domain-demo lead refs only.
     * Never overwrites an existing cadastral_reference (production-safe).
     */
    private function backfillDomainDemoCadastralReferences(): void
    {
        /** @var array<string, string> $map */
        $map = [
            'LD-1041' => '9872023VH5797S0001WX',
            'LD-1042' => '1302801VK4700F0001AA',
            'LD-1043' => '9872023VH5797S0001WX',
            'LD-1048' => '4625001VH2786N0001XX',
            'LD-1049' => '4518801VK4720A0001CC',
            'LD-1051' => '2807906VK4700A9999ZZ',
            'LD-1058' => '1302801VK4700F0002AB',
        ];

        $filled = 0;

        foreach ($map as $leadReference => $cadastralReference) {
            try {
                $normalized = CatastroReferenceNormalizer::normalize($cadastralReference);
            } catch (InvalidArgumentException) {
                continue;
            }

            $attributes = [
                'cadastral_reference' => $normalized,
            ];

            if (Schema::hasColumn('leads', 'cadastral_lookup_status')) {
                $attributes['cadastral_lookup_status'] = CadastralLookupStatus::NotLookedUp->value;
            }

            if (Schema::hasColumn('leads', 'catastro_status')) {
                $attributes['catastro_status'] = 'not_checked';
            }

            $updated = Lead::query()
                ->where('lead_reference', $leadReference)
                ->where(function ($query): void {
                    $query->whereNull('cadastral_reference')
                        ->orWhere('cadastral_reference', '');
                })
                ->where(function ($query): void {
                    $query->whereNull('lead_source')
                        ->orWhere('lead_source', '!=', 'catastro_demo');
                })
                ->update($attributes);

            $filled += $updated;
        }

        if ($filled > 0) {
            $this->command?->info("Backfilled cadastral references on {$filled} domain demo lead(s).");
        }
    }

    private function isLegacyLeadReference(string $reference): bool
    {
        return (bool) preg_match('/^LD-CT-/i', $reference);
    }

    /**
     * @param  array<string, mixed>  $def
     * @param  list<string>  $warnings
     */
    private function resolveReference(array $def, bool $isLive, array &$warnings): ?string
    {
        $raw = null;
        $label = (string) ($def['label'] ?? $def['scenario'] ?? 'unknown');

        if ($isLive) {
            $envKey = (string) ($def['env_key'] ?? '');
            $configMap = [
                'CATASTRO_DEMO_MATCH_REFERENCE' => 'services.catastro.demo.match_reference',
                'CATASTRO_DEMO_AREA_REVIEW_REFERENCE' => 'services.catastro.demo.area_review_reference',
                'CATASTRO_DEMO_SOLD_REFERENCE' => 'services.catastro.demo.sold_reference',
            ];
            if ($envKey !== '' && isset($configMap[$envKey])) {
                $raw = config($configMap[$envKey]);
            }
            if (! is_string($raw) || trim($raw) === '') {
                $raw = $def['cadastral_reference'] ?? null;
            }
        } else {
            $raw = $def['cadastral_reference'] ?? null;
        }

        if ($raw === null || trim((string) $raw) === '') {
            return null;
        }

        try {
            return CatastroReferenceNormalizer::normalize((string) $raw);
        } catch (InvalidArgumentException $e) {
            $warnings[] = "Invalid cadastral reference for {$label}: {$raw}";

            return null;
        }
    }

    private function ensureSoldPurchase(Lead $lead, User $buyer, Company $buyerCompany): void
    {
        $purchaseItem = PurchaseItem::query()->where('lead_id', $lead->id)->first();
        $purchase = $purchaseItem?->purchase
            ?? Purchase::query()->where('purchase_reference', self::LEGACY_PURCHASE_REFERENCE)->first();
        $payment = $purchase?->payment
            ?? Payment::query()->where('payment_reference', self::LEGACY_PAYMENT_REFERENCE)->first();

        $paymentReference = $this->resolveCommerceReference(
            $payment?->payment_reference,
            self::LEGACY_PAYMENT_REFERENCE,
            fn () => ReferenceGenerator::payment(),
        );
        $purchaseReference = $this->resolveCommerceReference(
            $purchase?->purchase_reference,
            self::LEGACY_PURCHASE_REFERENCE,
            fn () => ReferenceGenerator::purchase(),
        );

        if ($payment) {
            $payment->forceFill([
                'payment_reference' => $paymentReference,
                'payer_user_id' => $buyer->id,
                'payer_company_id' => $buyerCompany->id,
                'type' => PaymentType::BuyerPayment,
                'method' => PaymentMethod::Card,
                'provider' => 'demo',
                'provider_payment_id' => 'demo_txn_ct_sold',
                'status' => PaymentStatus::Paid,
                'amount' => $lead->selling_price ?? 100,
                'currency' => 'EUR',
                'paid_at' => $lead->sold_at ?? now()->subDay(),
                'metadata' => ['note' => 'Catastro demo sold lead payment'],
            ])->save();
        } else {
            $payment = Payment::query()->create([
                'payment_reference' => $paymentReference,
                'payer_user_id' => $buyer->id,
                'payer_company_id' => $buyerCompany->id,
                'type' => PaymentType::BuyerPayment,
                'method' => PaymentMethod::Card,
                'provider' => 'demo',
                'provider_payment_id' => 'demo_txn_ct_sold',
                'status' => PaymentStatus::Paid,
                'amount' => $lead->selling_price ?? 100,
                'currency' => 'EUR',
                'paid_at' => $lead->sold_at ?? now()->subDay(),
                'metadata' => ['note' => 'Catastro demo sold lead payment'],
            ]);
        }

        if ($purchase) {
            $purchase->forceFill([
                'purchase_reference' => $purchaseReference,
                'buyer_company_id' => $buyerCompany->id,
                'buyer_user_id' => $buyer->id,
                'status' => PurchaseStatus::Paid,
                'total_amount' => $lead->selling_price ?? 100,
                'total_size_m2' => $lead->size_m2,
                'payment_id' => $payment->id,
                'purchased_at' => $lead->sold_at ?? now()->subDay(),
            ])->save();
        } else {
            $purchase = Purchase::query()->create([
                'purchase_reference' => $purchaseReference,
                'buyer_company_id' => $buyerCompany->id,
                'buyer_user_id' => $buyer->id,
                'status' => PurchaseStatus::Paid,
                'total_amount' => $lead->selling_price ?? 100,
                'total_size_m2' => $lead->size_m2,
                'payment_id' => $payment->id,
                'purchased_at' => $lead->sold_at ?? now()->subDay(),
            ]);
        }

        PurchaseItem::query()->updateOrCreate(
            [
                'purchase_id' => $purchase->id,
                'lead_id' => $lead->id,
            ],
            [
                'lead_package_id' => null,
                'item_type' => PurchaseItemType::Lead,
                'quantity' => 1,
                'unit_price' => $lead->selling_price ?? 100,
                'total_price' => $lead->selling_price ?? 100,
            ],
        );
    }

    /**
     * @param  callable(): string  $generate
     */
    private function resolveCommerceReference(?string $existing, string $legacy, callable $generate): string
    {
        if (is_string($existing) && $existing !== '' && $existing !== $legacy) {
            return $existing;
        }

        return $generate();
    }
}
