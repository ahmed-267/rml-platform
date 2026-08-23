<?php

namespace Database\Seeders;

use App\Enums\ApprovalStatus;
use App\Enums\AuditDecisionStatus;
use App\Enums\CommissionStatus;
use App\Enums\CompanyType;
use App\Enums\EvidenceFileType;
use App\Enums\EvidenceStatus;
use App\Enums\EvidenceVisibility;
use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Enums\LeadStatus;
use App\Enums\MessageThreadCategory;
use App\Enums\MessageThreadStatus;
use App\Enums\PackageStatus;
use App\Enums\PackageType;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Enums\PayoutStatus;
use App\Enums\PurchaseItemType;
use App\Enums\PurchaseStatus;
use App\Enums\SellerType;
use App\Enums\UserRole;
use App\Models\BuyerProfile;
use App\Models\Commission;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Lead;
use App\Models\LeadAudit;
use App\Models\LeadEvidenceFile;
use App\Models\LeadMetricValue;
use App\Models\LeadPackage;
use App\Models\Message;
use App\Models\MessageThread;
use App\Models\Payment;
use App\Models\Payout;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Scheme;
use App\Models\SellerProfile;
use App\Models\User;
use App\Models\Zone;
use App\Services\AuditLogService;
use App\Services\LeadPricingService;
use App\Support\SpanishLocations;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Volume demo dataset layered on DomainDemoSeeder.
 * Keeps core references (LD-1041, PKG-003, PAY-00091, etc.) intact.
 */
class ExpandedDemoDataSeeder extends Seeder
{
    /** @var list<string> */
    private array $cities = [
        'Madrid', 'Barcelona', 'Valencia', 'Sevilla', 'Málaga',
        'Alicante', 'Zaragoza', 'Murcia', 'Granada', 'Córdoba',
    ];

    /** @var list<array{0: string, 1: string}> */
    private array $names = [
        ['Lucia', 'Fernandez'], ['Miguel', 'Ortega'], ['Sofia', 'Vargas'],
        ['Diego', 'Castro'], ['Paula', 'Moreno'], ['Hugo', 'Iglesias'],
        ['Carmen', 'Delgado'], ['Alvaro', 'Romero'], ['Nuria', 'Serrano'],
        ['Raquel', 'Blanco'], ['Tomas', 'Gil'], ['Irene', 'Molina'],
        ['Pablo', 'Suarez'], ['Laura', 'Campos'], ['Andres', 'Vega'],
        ['Marina', 'Reyes'], ['Oscar', 'Herrera'], ['Beatriz', 'Nieto'],
        ['Ruben', 'Pascual'], ['Clara', 'Marin'], ['Ivan', 'Leon'],
        ['Elena', 'Perez'], ['Jorge', 'Santos'], ['Marta', 'Cruz'],
        ['Sergio', 'Diaz'], ['Ana', 'Jimenez'], ['David', 'Ruiz'],
        ['Patricia', 'Alonso'], ['Francisco', 'Gutierrez'], ['Isabel', 'Navarro'],
        ['Daniel', 'Dominguez'], ['Cristina', 'Ramos'], ['Alejandro', 'Gil'],
        ['Monica', 'Soto'], ['Manuel', 'Cano'], ['Silvia', 'Prieto'],
    ];

    /** @var list<float> */
    private array $sizes = [45, 72, 96, 112, 125, 150, 180, 210];

    public function run(): void
    {
        $admin = User::query()->where('email', 'admin@rml.test')->firstOrFail();
        $auditor = User::query()->where('email', 'auditor@rml.test')->firstOrFail();
        $sellerAdmin = User::query()->where('email', 'seller.admin@rml.test')->firstOrFail();
        $sellerStaff = User::query()->where('email', 'seller.staff@rml.test')->firstOrFail();
        $agent = User::query()->where('email', 'agent@rml.test')->firstOrFail();
        $buyer = User::query()->where('email', 'buyer@rml.test')->firstOrFail();

        $insulation = Scheme::query()->where('slug', 'insulation')->firstOrFail();
        $glazing = Scheme::query()->where('slug', 'double-glazing')->firstOrFail();
        $heatPumps = Scheme::query()->where('slug', 'heat-pumps')->firstOrFail();
        $zonesByScheme = [
            'insulation' => Zone::query()->where('scheme_id', $insulation->id)->get()->keyBy('code'),
            'double-glazing' => Zone::query()->where('scheme_id', $glazing->id)->get()->keyBy('code'),
            'heat-pumps' => Zone::query()->where('scheme_id', $heatPumps->id)->get()->keyBy('code'),
        ];
        $zones = $zonesByScheme['insulation'];

        $pricing = new LeadPricingService;
        $auditLog = new AuditLogService;

        $greenEnergy = Company::query()->where('name', 'Verde Energía Madrid SL')->firstOrFail();
        $warmHomes = Company::query()->where('name', 'CalorHogar Instalaciones SL')->firstOrFail();

        $iberiaSurveys = $this->company('Iberia Home Surveys', CompanyType::Seller, 'Barcelona', '08002', $admin);
        $ecoCasa = $this->company('EcoCasa Leads', CompanyType::Seller, 'Valencia', '46002', $admin);
        $andalusia = $this->company('Andalusia Retrofit Partners', CompanyType::Seller, 'Sevilla', '41002', $admin);
        $norte = $this->company('Norte Energy Audits', CompanyType::Seller, 'Zaragoza', '50001', $admin);

        $solis = $this->company('Solis Installers', CompanyType::Buyer, 'Madrid', '28010', $admin);
        $iberiaRetrofit = $this->company('Iberia Retrofit Group', CompanyType::Buyer, 'Barcelona', '08010', $admin);
        $verde = $this->company('Verde Home Solutions', CompanyType::Buyer, 'Valencia', '46010', $admin);
        $ecoTherm = $this->company('EcoTherm Spain', CompanyType::Buyer, 'Málaga', '29010', $admin);
        $casaPlus = $this->company('CasaPlus Installations', CompanyType::Buyer, 'Alicante', '03001', $admin);

        $extraSellers = [
            $this->seedSellerUser('Javier Ruiz', 'iberia.surveys@rml.test', UserRole::SellerCompanyAdmin, $iberiaSurveys, SellerType::CompanyAdmin, $admin),
            $this->seedSellerUser('Sofía Navarro', 'ecocasa.admin@rml.test', UserRole::SellerCompanyAdmin, $ecoCasa, SellerType::CompanyAdmin, $admin),
            $this->seedSellerUser('Andrés Vega', 'andalusia.admin@rml.test', UserRole::SellerCompanyAdmin, $andalusia, SellerType::CompanyAdmin, $admin),
            $this->seedSellerUser('Daniel Torres', 'norte.admin@rml.test', UserRole::SellerCompanyAdmin, $norte, SellerType::CompanyAdmin, $admin),
            $this->seedSellerUser('Irene Soto', 'ecocasa.staff@rml.test', UserRole::SellerStaff, $ecoCasa, SellerType::SellerStaff, $admin, 12.00),
            $this->seedSellerUser('Marta López', 'iberia.staff@rml.test', UserRole::SellerStaff, $iberiaSurveys, SellerType::SellerStaff, $admin, 10.00),
        ];

        $extraBuyers = [
            $this->seedBuyerUser('Laura Sánchez', 'solis.buyer@rml.test', $solis, $admin),
            $this->seedBuyerUser('Lucía Fernández', 'iberia.retrofit@rml.test', $iberiaRetrofit, $admin),
            $this->seedBuyerUser('Diego Molina', 'verde.buyer@rml.test', $verde, $admin),
            $this->seedBuyerUser('Ana Romero', 'ecotherm.buyer@rml.test', $ecoTherm, $admin),
            $this->seedBuyerUser('Miguel Ortega', 'casaplus.buyer@rml.test', $casaPlus, $admin),
        ];

        $this->seedPendingApprovals($admin);

        $sellerPool = [
            ['user' => $sellerAdmin, 'company' => $greenEnergy],
            ['user' => $sellerStaff, 'company' => $greenEnergy],
            ['user' => $agent, 'company' => Company::query()->where('name', 'Javier Morales EPC Surveys')->firstOrFail()],
            ...array_map(fn (array $row) => [
                'user' => $row['user'],
                'company' => $row['company'],
            ], $extraSellers),
        ];

        $schemes = [
            ['scheme' => $insulation, 'slug' => 'insulation'],
            ['scheme' => $glazing, 'slug' => 'double-glazing'],
            ['scheme' => $heatPumps, 'slug' => 'heat-pumps'],
        ];

        $zoneCodes = ['D1', 'D2', 'E1', 'E2'];
        $listedLeads = [];
        $soldLeads = [];
        $pipelineLeads = [];

        // 36 marketplace-listed leads (LD-1101+) across schemes/zones/sizes
        for ($i = 0; $i < 36; $i++) {
            $schemeRow = $schemes[$i % 3];
            $zoneCode = $zoneCodes[$i % 4];
            $seller = $sellerPool[$i % count($sellerPool)];
            $name = $this->names[$i % count($this->names)];
            $city = $this->cities[$i % count($this->cities)];
            $size = $this->sizes[$i % count($this->sizes)];
            $ref = 'LD-'.str_pad((string) (1101 + $i), 4, '0', STR_PAD_LEFT);
            $schemeZones = $zonesByScheme[$schemeRow['slug']];

            $lead = $this->seedLead(
                reference: $ref,
                submitter: $seller['user'],
                company: $seller['company'],
                scheme: $schemeRow['scheme'],
                zone: $schemeZones[$zoneCode],
                status: LeadStatus::Listed,
                first: $name[0],
                last: $name[1],
                city: $city,
                size: $size,
                distance: 8 + ($i % 12) * 5.5,
                pricing: $pricing,
                auditor: $auditor,
                admin: $admin,
                daysAgo: 2 + ($i % 14),
            );

            $listedLeads[] = $lead;
        }

        // Pipeline / status variety for seller + auditor portals (simplified visible set)
        $pipelineDefs = [
            [LeadStatus::Submitted, AuditDecisionStatus::Pending],
            [LeadStatus::PendingValidation, AuditDecisionStatus::InReview],
            [LeadStatus::NeedsMoreInformation, AuditDecisionStatus::NeedsMoreInformation],
            [LeadStatus::PendingEvidence, AuditDecisionStatus::Pending],
            [LeadStatus::Listed, AuditDecisionStatus::Accepted],
            [LeadStatus::Rejected, AuditDecisionStatus::Rejected],
            [LeadStatus::Submitted, AuditDecisionStatus::Pending],
            [LeadStatus::NeedsMoreInformation, AuditDecisionStatus::NeedsMoreInformation],
            [LeadStatus::PendingValidation, AuditDecisionStatus::InReview],
            [LeadStatus::Listed, AuditDecisionStatus::Accepted],
            [LeadStatus::Rejected, AuditDecisionStatus::Rejected],
            [LeadStatus::Validating, AuditDecisionStatus::InReview],
        ];

        foreach ($pipelineDefs as $index => [$status, $auditStatus]) {
            $seller = $sellerPool[$index % count($sellerPool)];
            $name = $this->names[($index + 10) % count($this->names)];
            $city = $this->cities[($index + 3) % count($this->cities)];
            $zoneCode = $zoneCodes[$index % 4];
            $schemeRow = $schemes[$index % 3];
            $ref = 'LD-'.str_pad((string) (1201 + $index), 4, '0', STR_PAD_LEFT);
            $schemeZones = $zonesByScheme[$schemeRow['slug']];

            $pipelineLeads[] = $this->seedLead(
                reference: $ref,
                submitter: $seller['user'],
                company: $seller['company'],
                scheme: $schemeRow['scheme'],
                zone: $schemeZones[$zoneCode],
                status: $status,
                first: $name[0],
                last: $name[1],
                city: $city,
                size: $this->sizes[$index % count($this->sizes)],
                distance: 15 + $index * 3,
                pricing: $pricing,
                auditor: $auditor,
                admin: $admin,
                daysAgo: 1 + $index,
                auditStatus: $auditStatus,
            );
        }

        // Sold leads for purchases / reports (beyond core LD-1043)
        for ($i = 0; $i < 10; $i++) {
            $seller = $sellerPool[$i % count($sellerPool)];
            $name = $this->names[($i + 20) % count($this->names)];
            $city = $this->cities[($i + 5) % count($this->cities)];
            $zoneCode = $zoneCodes[$i % 4];
            $schemeRow = $schemes[$i % 3];
            $ref = 'LD-'.str_pad((string) (1301 + $i), 4, '0', STR_PAD_LEFT);
            $schemeZones = $zonesByScheme[$schemeRow['slug']];

            $soldLeads[] = $this->seedLead(
                reference: $ref,
                submitter: $seller['user'],
                company: $seller['company'],
                scheme: $schemeRow['scheme'],
                zone: $schemeZones[$zoneCode],
                status: LeadStatus::Sold,
                first: $name[0],
                last: $name[1],
                city: $city,
                size: $this->sizes[$i % count($this->sizes)],
                distance: 10 + $i * 4,
                pricing: $pricing,
                auditor: $auditor,
                admin: $admin,
                daysAgo: 5 + $i,
            );
        }

        $packages = $this->seedPackages($listedLeads, $insulation, $glazing, $heatPumps, $admin);
        $this->seedPurchasesAndFinance(
            listedLeads: $listedLeads,
            soldLeads: $soldLeads,
            packages: $packages,
            warmHomes: $warmHomes,
            buyer: $buyer,
            extraBuyers: $extraBuyers,
            greenEnergy: $greenEnergy,
            sellerAdmin: $sellerAdmin,
            sellerStaff: $sellerStaff,
            admin: $admin,
        );

        $this->seedMessages(
            admin: $admin,
            auditor: $auditor,
            sellerAdmin: $sellerAdmin,
            buyer: $buyer,
            pipelineLeads: $pipelineLeads,
            soldLeads: $soldLeads,
        );

        $this->seedAuditLogs(
            auditLog: $auditLog,
            admin: $admin,
            auditor: $auditor,
            listedLeads: $listedLeads,
            soldLeads: $soldLeads,
            pipelineLeads: $pipelineLeads,
        );
    }

    /**
     * @param  list<Lead>  $listedLeads
     * @return list<LeadPackage>
     */
    private function seedPackages(
        array $listedLeads,
        Scheme $insulation,
        Scheme $glazing,
        Scheme $heatPumps,
        User $admin,
    ): array {
        $chunks = array_chunk($listedLeads, 3);
        $defs = [
            ['PKG-101', 'Premium E-Zone Insulation', PackageType::MixedZone, $insulation, PackageStatus::Available, ['E1' => 2, 'E2' => 1], 0, 2],
            ['PKG-102', 'Budget D-Zone Bundle', PackageType::MixedZone, $insulation, PackageStatus::Available, ['D1' => 2, 'D2' => 1], 3, 5],
            ['PKG-103', 'Madrid Mixed Quality Pack', PackageType::MixedZone, $insulation, PackageStatus::Available, ['D1' => 1, 'E1' => 2], 6, 8],
            ['PKG-104', 'Near Distance Under 30km', PackageType::Prebuilt, $insulation, PackageStatus::Available, null, 9, 11],
            ['PKG-105', 'Double Glazing — 8–14 Window Bundle', PackageType::Prebuilt, $glazing, PackageStatus::Available, null, 1, 3],
            ['PKG-106', 'Heat Pump Feasibility / kW Set', PackageType::Prebuilt, $heatPumps, PackageStatus::Available, null, 2, 4],
            ['PKG-107', 'Large Homes 150m²+', PackageType::Custom, $insulation, PackageStatus::Available, ['D2' => 1, 'E1' => 2], 12, 14],
            ['PKG-108', 'Coastal Mixed Scheme', PackageType::MixedZone, $insulation, PackageStatus::Available, ['D1' => 1, 'D2' => 1, 'E2' => 1], 15, 17],
            ['PKG-109', 'Sevilla Insulation Pack', PackageType::Prebuilt, $insulation, PackageStatus::Locked, null, 18, 20],
            ['PKG-110', 'Sold Demo Package', PackageType::Prebuilt, $insulation, PackageStatus::Sold, null, 21, 23],
            ['PKG-111', 'Valencia Mid-Size Bundle', PackageType::MixedZone, $insulation, PackageStatus::Available, ['D2' => 2, 'E1' => 1], 24, 26],
            ['PKG-112', 'Heat Pump Technical Quality Pack', PackageType::Custom, $heatPumps, PackageStatus::Available, null, 27, 29],
        ];

        $packages = [];
        foreach ($defs as $def) {
            [$ref, $name, $type, $scheme, $status, $zoneMix, $from, $to] = $def;
            $leads = array_values(array_filter(
                array_slice($listedLeads, $from, max(1, $to - $from + 1)),
            ));
            if ($leads === [] && isset($chunks[0])) {
                $leads = $chunks[0];
            }

            $estimated = collect($leads)->sum(fn (Lead $lead) => (float) ($lead->selling_price ?? 0));
            $package = LeadPackage::query()->updateOrCreate(
                ['package_reference' => $ref],
                [
                    'name' => $name,
                    'package_type' => $type,
                    'scheme_id' => $scheme->id,
                    'requested_leads_count' => count($leads),
                    'size_range_min' => 45,
                    'size_range_max' => 220,
                    'distance_range_min' => 0,
                    'distance_range_max' => $ref === 'PKG-104' ? 30 : 90,
                    'zone_mix' => $zoneMix,
                    'avg_price_per_m2' => $scheme->slug === 'insulation' ? 3.75 : ($scheme->slug === 'heat-pumps' ? 8.00 : 6.50),
                    'estimated_total' => $estimated,
                    'status' => $status,
                    'created_by_user_id' => $admin->id,
                ],
            );
            $package->leads()->sync(collect($leads)->pluck('id')->all());
            $packages[] = $package;
        }

        return $packages;
    }

    /**
     * @param  list<Lead>  $listedLeads
     * @param  list<Lead>  $soldLeads
     * @param  list<LeadPackage>  $packages
     * @param  list<array{user: User, company: Company}>  $extraBuyers
     */
    private function seedPurchasesAndFinance(
        array $listedLeads,
        array $soldLeads,
        array $packages,
        Company $warmHomes,
        User $buyer,
        array $extraBuyers,
        Company $greenEnergy,
        User $sellerAdmin,
        User $sellerStaff,
        User $admin,
    ): void {
        $buyerPool = [
            ['user' => $buyer, 'company' => $warmHomes],
            ...$extraBuyers,
        ];

        foreach ($soldLeads as $index => $lead) {
            $buyerRow = $buyerPool[$index % count($buyerPool)];
            $payRef = 'PAY-'.str_pad((string) (101 + $index), 5, '0', STR_PAD_LEFT);
            $purRef = 'PUR-'.str_pad((string) (101 + $index), 5, '0', STR_PAD_LEFT);
            $invRef = 'INV-'.str_pad((string) (101 + $index), 5, '0', STR_PAD_LEFT);
            $poRef = 'PO-'.str_pad((string) (101 + $index), 5, '0', STR_PAD_LEFT);
            $comRef = 'COM-'.str_pad((string) (101 + $index), 5, '0', STR_PAD_LEFT);

            $isPaid = $index % 4 !== 3;
            $paymentStatus = match ($index % 5) {
                0, 1 => PaymentStatus::Paid,
                2 => PaymentStatus::Pending,
                3 => PaymentStatus::Failed,
                default => PaymentStatus::Cancelled,
            };
            $purchaseStatus = match ($paymentStatus) {
                PaymentStatus::Paid => PurchaseStatus::Paid,
                PaymentStatus::Pending => PurchaseStatus::Pending,
                PaymentStatus::Failed => PurchaseStatus::Failed,
                default => PurchaseStatus::Cancelled,
            };

            $payment = Payment::query()->updateOrCreate(
                ['payment_reference' => $payRef],
                [
                    'payer_user_id' => $buyerRow['user']->id,
                    'payer_company_id' => $buyerRow['company']->id,
                    'type' => PaymentType::BuyerPayment,
                    'method' => $index % 2 === 0 ? PaymentMethod::Card : PaymentMethod::ManualBankTransfer,
                    'provider' => 'demo',
                    'provider_payment_id' => $isPaid ? 'demo_txn_'.$lead->lead_reference : null,
                    'status' => $paymentStatus,
                    'amount' => $lead->selling_price,
                    'currency' => 'EUR',
                    'paid_at' => $paymentStatus === PaymentStatus::Paid ? now()->subDays(2 + $index) : null,
                    'due_date' => $paymentStatus === PaymentStatus::Pending ? now()->addDays(3)->toDateString() : null,
                    'metadata' => ['note' => 'Expanded demo payment for '.$lead->lead_reference],
                ],
            );

            $purchase = Purchase::query()->updateOrCreate(
                ['purchase_reference' => $purRef],
                [
                    'buyer_company_id' => $buyerRow['company']->id,
                    'buyer_user_id' => $buyerRow['user']->id,
                    'status' => $purchaseStatus,
                    'total_amount' => $lead->selling_price,
                    'total_size_m2' => $lead->size_m2,
                    'payment_id' => $payment->id,
                    'purchased_at' => $purchaseStatus === PurchaseStatus::Paid ? now()->subDays(2 + $index) : null,
                ],
            );

            PurchaseItem::query()->updateOrCreate(
                ['purchase_id' => $purchase->id, 'lead_id' => $lead->id],
                [
                    'lead_package_id' => null,
                    'item_type' => PurchaseItemType::Lead,
                    'quantity' => 1,
                    'unit_price' => $lead->selling_price,
                    'total_price' => $lead->selling_price,
                ],
            );

            if ($paymentStatus === PaymentStatus::Paid) {
                Invoice::query()->updateOrCreate(
                    ['invoice_reference' => $invRef],
                    [
                        'payment_id' => $payment->id,
                        'purchase_id' => $purchase->id,
                        'company_id' => $buyerRow['company']->id,
                        'user_id' => $buyerRow['user']->id,
                        'type' => InvoiceType::BuyerInvoice,
                        'status' => InvoiceStatus::Paid,
                        'subtotal' => $lead->selling_price,
                        'tax_amount' => 0,
                        'total' => $lead->selling_price,
                        'currency' => 'EUR',
                        'issued_at' => now()->subDays(2 + $index),
                    ],
                );
            }

            $payoutStatus = $index % 3 === 0 ? PayoutStatus::Paid : PayoutStatus::Pending;
            Payout::query()->updateOrCreate(
                ['payout_reference' => $poRef],
                [
                    'seller_company_id' => $lead->seller_company_id ?? $greenEnergy->id,
                    'seller_user_id' => $lead->submitted_by_user_id ?? $sellerAdmin->id,
                    'payment_id' => null,
                    'status' => $payoutStatus,
                    'amount' => $lead->buying_price ?? round(((float) $lead->selling_price) * 0.65, 2),
                    'currency' => 'EUR',
                    'due_date' => now()->addDays(7 + $index)->toDateString(),
                    'paid_at' => $payoutStatus === PayoutStatus::Paid ? now()->subDay() : null,
                    'notes' => 'Demo payout for '.$lead->lead_reference,
                ],
            );

            $commissionStatus = match ($index % 3) {
                0 => CommissionStatus::Paid,
                1 => CommissionStatus::Due,
                default => CommissionStatus::Pending,
            };
            $base = (float) ($lead->selling_price ?? 400);
            Commission::query()->updateOrCreate(
                ['commission_reference' => $comRef],
                [
                    'lead_id' => $lead->id,
                    'seller_user_id' => $sellerStaff->id,
                    'seller_company_id' => $greenEnergy->id,
                    'percentage' => 10.00,
                    'base_amount' => $base,
                    'commission_amount' => round($base * 0.10, 2),
                    'status' => $commissionStatus,
                    'paid_at' => $commissionStatus === CommissionStatus::Paid ? now()->subDays($index) : null,
                ],
            );
        }

        // Extra pending bank-transfer purchase on a listed lead (locks it from marketplace)
        if (isset($listedLeads[30])) {
            $locked = $listedLeads[30];
            $payment = Payment::query()->updateOrCreate(
                ['payment_reference' => 'PAY-00201'],
                [
                    'payer_user_id' => $buyer->id,
                    'payer_company_id' => $warmHomes->id,
                    'type' => PaymentType::BuyerPayment,
                    'method' => PaymentMethod::ManualBankTransfer,
                    'status' => PaymentStatus::Pending,
                    'amount' => $locked->selling_price,
                    'currency' => 'EUR',
                    'due_date' => now()->addDays(5)->toDateString(),
                ],
            );
            $purchase = Purchase::query()->updateOrCreate(
                ['purchase_reference' => 'PUR-00201'],
                [
                    'buyer_company_id' => $warmHomes->id,
                    'buyer_user_id' => $buyer->id,
                    'status' => PurchaseStatus::Pending,
                    'total_amount' => $locked->selling_price,
                    'total_size_m2' => $locked->size_m2,
                    'payment_id' => $payment->id,
                ],
            );
            PurchaseItem::query()->updateOrCreate(
                ['purchase_id' => $purchase->id, 'lead_id' => $locked->id],
                [
                    'item_type' => PurchaseItemType::Lead,
                    'quantity' => 1,
                    'unit_price' => $locked->selling_price,
                    'total_price' => $locked->selling_price,
                ],
            );
        }

        // Package purchase demo
        $availablePackage = collect($packages)->first(
            fn (LeadPackage $package) => $package->package_reference === 'PKG-110',
        );
        if ($availablePackage) {
            $total = (float) $availablePackage->estimated_total;
            $payment = Payment::query()->updateOrCreate(
                ['payment_reference' => 'PAY-00210'],
                [
                    'payer_user_id' => $buyer->id,
                    'payer_company_id' => $warmHomes->id,
                    'type' => PaymentType::BuyerPayment,
                    'method' => PaymentMethod::Card,
                    'provider' => 'demo',
                    'provider_payment_id' => 'demo_pkg_110',
                    'status' => PaymentStatus::Paid,
                    'amount' => $total,
                    'currency' => 'EUR',
                    'paid_at' => now()->subDays(4),
                ],
            );
            $purchase = Purchase::query()->updateOrCreate(
                ['purchase_reference' => 'PUR-00210'],
                [
                    'buyer_company_id' => $warmHomes->id,
                    'buyer_user_id' => $buyer->id,
                    'status' => PurchaseStatus::Paid,
                    'total_amount' => $total,
                    'total_size_m2' => $availablePackage->leads()->sum('size_m2'),
                    'payment_id' => $payment->id,
                    'purchased_at' => now()->subDays(4),
                ],
            );
            PurchaseItem::query()->updateOrCreate(
                ['purchase_id' => $purchase->id, 'lead_package_id' => $availablePackage->id],
                [
                    'lead_id' => null,
                    'item_type' => PurchaseItemType::Package,
                    'quantity' => 1,
                    'unit_price' => $total,
                    'total_price' => $total,
                ],
            );
        }

        unset($admin);
    }

    /**
     * @param  list<Lead>  $pipelineLeads
     * @param  list<Lead>  $soldLeads
     */
    private function seedMessages(
        User $admin,
        User $auditor,
        User $sellerAdmin,
        User $buyer,
        array $pipelineLeads,
        array $soldLeads,
    ): void {
        $threads = [
            ['THR-00101', 'Seller information request — photos', MessageThreadCategory::InformationRequest, MessageThreadStatus::Open, $sellerAdmin, $pipelineLeads[4] ?? null, null],
            ['THR-00102', 'Buyer payment timing question', MessageThreadCategory::PaymentQuery, MessageThreadStatus::Pending, $buyer, null, $soldLeads[0] ?? null],
            ['THR-00103', 'Auditor evidence quality follow-up', MessageThreadCategory::EvidenceIssue, MessageThreadStatus::Open, $auditor, $pipelineLeads[2] ?? null, null],
            ['THR-00104', 'Admin internal ops note', MessageThreadCategory::Internal, MessageThreadStatus::Closed, $admin, null, null],
            ['THR-00105', 'Lead review discussion', MessageThreadCategory::LeadReview, MessageThreadStatus::Open, $auditor, $pipelineLeads[1] ?? null, null],
            ['THR-00106', 'Buyer complaint (demo)', MessageThreadCategory::Complaint, MessageThreadStatus::Pending, $buyer, null, $soldLeads[1] ?? null],
            ['THR-00107', 'Seller issue — rejected lead', MessageThreadCategory::SellerIssue, MessageThreadStatus::Open, $sellerAdmin, $pipelineLeads[7] ?? null, null],
            ['THR-00108', 'Audit question checklist', MessageThreadCategory::AuditQuestion, MessageThreadStatus::Closed, $auditor, $pipelineLeads[3] ?? null, null],
        ];

        foreach ($threads as $index => $row) {
            [$ref, $subject, $category, $status, $creator, $lead, $sold] = $row;
            $thread = MessageThread::query()->updateOrCreate(
                ['thread_reference' => $ref],
                [
                    'subject' => $subject,
                    'category' => $category,
                    'status' => $status,
                    'created_by_user_id' => $creator->id,
                    'assigned_to_user_id' => $admin->id,
                    'related_lead_id' => $lead?->id,
                    'related_purchase_id' => null,
                ],
            );

            Message::query()->updateOrCreate(
                [
                    'message_thread_id' => $thread->id,
                    'sender_user_id' => $creator->id,
                    'body' => 'Demo message '.$index.' — '.$subject,
                ],
                ['read_at' => $status === MessageThreadStatus::Closed ? now() : null],
            );

            if ($index % 2 === 0) {
                Message::query()->updateOrCreate(
                    [
                        'message_thread_id' => $thread->id,
                        'sender_user_id' => $admin->id,
                        'body' => 'Admin reply for '.$ref,
                    ],
                    ['read_at' => null],
                );
            }

            unset($sold);
        }
    }

    /**
     * @param  list<Lead>  $listedLeads
     * @param  list<Lead>  $soldLeads
     * @param  list<Lead>  $pipelineLeads
     */
    private function seedAuditLogs(
        AuditLogService $auditLog,
        User $admin,
        User $auditor,
        array $listedLeads,
        array $soldLeads,
        array $pipelineLeads,
    ): void {
        $actions = [
            'lead.submitted',
            'lead.audited',
            'lead.listed',
            'payment.confirmed',
            'lead.released',
            'payout.marked_due',
            'commission.marked_due',
            'invite.sent',
            'message.created',
        ];

        foreach ($listedLeads as $index => $lead) {
            if ($index % 2 !== 0) {
                continue;
            }
            $auditLog->log(
                $actions[$index % count($actions)],
                $lead,
                ['status' => 'previous'],
                ['status' => $lead->status->value],
                $index % 3 === 0 ? $auditor : $admin,
            );
        }

        foreach ($soldLeads as $index => $lead) {
            $auditLog->log('payment.confirmed', $lead, null, ['paid' => true], $admin);
            $auditLog->log('lead.released', $lead, null, ['released' => true], $admin);
            if ($index % 2 === 0) {
                $auditLog->log('payout.marked_due', $lead, null, ['due' => true], $admin);
                $auditLog->log('commission.marked_due', $lead, null, ['due' => true], $admin);
            }
        }

        foreach ($pipelineLeads as $index => $lead) {
            $auditLog->log(
                $index % 2 === 0 ? 'lead.submitted' : 'lead.audited',
                $lead,
                null,
                ['demo' => true],
                $auditor,
            );
        }

        $auditLog->log(
            'demo.expanded_seed_completed',
            null,
            null,
            [
                'listed_leads' => count($listedLeads),
                'sold_leads' => count($soldLeads),
                'pipeline_leads' => count($pipelineLeads),
            ],
            $admin,
        );
    }

    private function seedLead(
        string $reference,
        User $submitter,
        Company $company,
        Scheme $scheme,
        ?Zone $zone,
        LeadStatus $status,
        string $first,
        string $last,
        string $city,
        float $size,
        float $distance,
        LeadPricingService $pricing,
        User $auditor,
        User $admin,
        int $daysAgo,
        ?AuditDecisionStatus $auditStatus = null,
    ): Lead {
        $lead = Lead::query()->updateOrCreate(
            ['lead_reference' => $reference],
            [
                'submitted_by_user_id' => $submitter->id,
                'seller_company_id' => $company->id,
                'scheme_id' => $scheme->id,
                'zone_id' => $zone?->id,
                'status' => $status,
                'customer_first_name' => $first,
                'customer_last_name' => $last,
                'customer_phone' => '+34600'.substr(preg_replace('/\D/', '', $reference) ?: '0000', -6),
                'customer_whatsapp' => '+34600'.substr(preg_replace('/\D/', '', $reference) ?: '0000', -6),
                'customer_email' => Str::lower($first).'.'.Str::lower($last).'.'.Str::lower($reference).'@example.es',
                ...SpanishLocations::leadAttributes($city, (int) substr(preg_replace('/\D/', '', $reference) ?: '0', -2)),
                'property_type' => ['detached', 'semi_detached', 'terrace', 'flat'][((int) substr($reference, -1)) % 4],
                'epc_rating' => ['D', 'E', 'F', 'G'][((int) substr($reference, -1)) % 4],
                'size_m2' => $size,
                'distance_km' => $distance,
                'rejection_reason' => $status === LeadStatus::Rejected ? 'Demo rejection — incomplete evidence' : null,
                'accepted_at' => in_array($status, [LeadStatus::Accepted, LeadStatus::Priced, LeadStatus::Listed, LeadStatus::Sold], true)
                    ? now()->subDays($daysAgo + 2)
                    : null,
                'rejected_at' => $status === LeadStatus::Rejected ? now()->subDays($daysAgo) : null,
                'listed_at' => in_array($status, [LeadStatus::Listed, LeadStatus::Sold], true)
                    ? now()->subDays($daysAgo)
                    : null,
                'sold_at' => $status === LeadStatus::Sold ? now()->subDays(max(1, $daysAgo - 1)) : null,
            ],
        );

        $calc = $pricing->calculate($lead);
        if ($calc['selling_price'] !== null) {
            $selling = $calc['selling_price'];
            $buying = round($selling * 0.65, 2);
            $lead->update([
                'selling_price' => $selling,
                'buying_price' => $buying,
                'expected_margin' => round($selling - $buying, 2),
            ]);
        } elseif (in_array($status, [LeadStatus::Listed, LeadStatus::Sold, LeadStatus::Priced, LeadStatus::Accepted], true)) {
            $fallback = round($size * ($scheme->slug === 'heat-pumps' ? 8 : 6.5), 2);
            $lead->update([
                'selling_price' => $fallback,
                'buying_price' => round($fallback * 0.65, 2),
                'expected_margin' => round($fallback * 0.35, 2),
            ]);
        }

        LeadEvidenceFile::query()->updateOrCreate(
            [
                'lead_id' => $lead->id,
                'file_type' => EvidenceFileType::Photo,
                'original_name' => $lead->lead_reference.'-photo.jpg',
            ],
            [
                'uploaded_by_user_id' => $submitter->id,
                'path' => 'evidence/'.$lead->lead_reference.'/photo.jpg',
                'disk' => \App\Support\FilesystemDisk::uploads(),
                'mime_type' => 'image/jpeg',
                'size' => 220000 + ((int) substr($reference, -2) * 100),
                'visibility' => EvidenceVisibility::Private,
                'status' => EvidenceStatus::Uploaded,
            ],
        );
        \App\Support\EvidencePlaceholderStorage::ensurePath(
            'evidence/'.$lead->lead_reference.'/photo.jpg',
            null,
            'image/jpeg',
        );

        $this->seedSchemeMetrics($lead, $scheme, $size, $zone?->code);

        $resolvedAudit = $auditStatus ?? match ($status) {
            LeadStatus::NeedsMoreInformation => AuditDecisionStatus::NeedsMoreInformation,
            LeadStatus::PendingValidation, LeadStatus::Validating => AuditDecisionStatus::InReview,
            LeadStatus::Submitted, LeadStatus::PendingEvidence => AuditDecisionStatus::Pending,
            LeadStatus::Rejected => AuditDecisionStatus::Rejected,
            LeadStatus::Accepted, LeadStatus::Priced, LeadStatus::Listed, LeadStatus::Sold => AuditDecisionStatus::Accepted,
            default => null,
        };

        if ($resolvedAudit !== null) {
            LeadAudit::query()->updateOrCreate(
                ['lead_id' => $lead->id],
                [
                    'auditor_user_id' => $auditor->id,
                    'final_decision_by_user_id' => in_array($status, [LeadStatus::Listed, LeadStatus::Sold, LeadStatus::Rejected], true)
                        ? $admin->id
                        : null,
                    'status' => $resolvedAudit,
                    'audit_notes' => 'Expanded demo audit for '.$lead->lead_reference,
                    'buying_price' => $lead->buying_price,
                    'selling_price' => $lead->selling_price,
                    'suggested_price' => $lead->selling_price,
                    'expected_margin' => $lead->expected_margin,
                    'completed_at' => in_array($resolvedAudit, [
                        AuditDecisionStatus::Accepted,
                        AuditDecisionStatus::Rejected,
                    ], true) ? now()->subDays($daysAgo) : null,
                ],
            );
        }

        return $lead->fresh();
    }

    private function seedSchemeMetrics(Lead $lead, Scheme $scheme, float $size, ?string $zoneCode): void
    {
        $metrics = match ($scheme->slug) {
            'insulation' => [
                'insulation_type' => ['cavity_wall', 'loft', 'external_wall'][((int) substr($lead->lead_reference, -1)) % 3],
            ],
            'double-glazing' => [
                'window_count' => (string) (6 + ((int) substr($lead->lead_reference, -1) % 8)),
                'glazing_area_m2' => (string) round(max(8, $size * 0.18), 1),
                'current_glazing_type' => ['single', 'old_double', 'failed_units'][((int) substr($lead->lead_reference, -1)) % 3],
                'frame_type' => ['uPVC', 'aluminium', 'timber', 'mixed'][((int) substr($lead->lead_reference, -1)) % 4],
            ],
            'heat-pumps' => [
                'current_heating_system' => ['gas_boiler', 'oil_boiler', 'electric', 'biomass'][((int) substr($lead->lead_reference, -1)) % 4],
                'proposed_heat_pump_type' => ['aerothermal', 'geothermal', 'hydrothermal'][((int) substr($lead->lead_reference, -1)) % 3],
                'property_size_m2' => (string) $size,
                'estimated_kw' => (string) round(max(4, $size / 20), 1),
                'outdoor_unit_feasibility' => ['good', 'constrained', 'uncertain'][((int) substr($lead->lead_reference, -1)) % 3],
                'electrical_supply_notes' => 'Single-phase supply noted for demo.',
            ],
            default => [],
        };

        foreach ($metrics as $key => $value) {
            LeadMetricValue::query()->updateOrCreate(
                ['lead_id' => $lead->id, 'key' => $key],
                ['value' => (string) $value],
            );
        }

        if ($zoneCode) {
            LeadMetricValue::query()->updateOrCreate(
                ['lead_id' => $lead->id, 'key' => 'zone'],
                ['value' => $zoneCode],
            );
        }
    }

    /**
     * @return array{user: User, company: Company}
     */
    private function seedSellerUser(
        string $name,
        string $email,
        UserRole $role,
        Company $company,
        SellerType $sellerType,
        User $admin,
        float $commissionRate = 5.00,
    ): array {
        $user = User::query()->updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => 'password',
                'email_verified_at' => now(),
                'approval_status' => ApprovalStatus::Approved->value,
                'locale' => 'en',
                'approved_at' => now(),
            ],
        );
        $user->syncRoles([$role->value]);

        SellerProfile::query()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'company_id' => $company->id,
                'seller_type' => $sellerType,
                'commission_rate' => $commissionRate,
                'approval_status' => ApprovalStatus::Approved,
            ],
        );

        unset($admin);

        return ['user' => $user, 'company' => $company];
    }

    /**
     * @return array{user: User, company: Company}
     */
    private function seedBuyerUser(string $name, string $email, Company $company, User $admin): array
    {
        $user = User::query()->updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => 'password',
                'email_verified_at' => now(),
                'approval_status' => ApprovalStatus::Approved->value,
                'locale' => 'en',
                'approved_at' => now(),
            ],
        );
        $user->syncRoles([UserRole::BuyerAdmin->value]);

        BuyerProfile::query()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'company_id' => $company->id,
                'services_offered' => ['insulation', 'double_glazing', 'heat_pumps'],
                'preferred_zones' => ['D1', 'D2', 'E1', 'E2'],
                'max_distance_km' => 100,
                'billing_status' => 'active',
                'approval_status' => ApprovalStatus::Approved,
            ],
        );

        unset($admin);

        return ['user' => $user, 'company' => $company];
    }

    private function seedPendingApprovals(User $admin): void
    {
        $pendingSeller = User::query()->updateOrCreate(
            ['email' => 'pending.seller2@rml.test'],
            [
                'name' => 'Irene Pascual',
                'password' => 'password',
                'email_verified_at' => now(),
                'approval_status' => ApprovalStatus::Pending->value,
                'locale' => 'es',
                'approved_at' => null,
            ],
        );
        $pendingSeller->syncRoles([UserRole::SellerCompanyAdmin->value]);

        $pendingCompany = $this->company(
            'Pending Solar Partners',
            CompanyType::Seller,
            'Granada',
            '18001',
            $admin,
        );
        $pendingCompany->update([
            'approval_status' => ApprovalStatus::Pending,
            'approved_at' => null,
            'approved_by' => null,
            'contact_name' => 'Irene Pascual',
        ]);

        SellerProfile::query()->updateOrCreate(
            ['user_id' => $pendingSeller->id],
            [
                'company_id' => $pendingCompany->id,
                'seller_type' => SellerType::CompanyAdmin,
                'commission_rate' => 5.00,
                'approval_status' => ApprovalStatus::Pending,
            ],
        );

        $pendingBuyer = User::query()->updateOrCreate(
            ['email' => 'pending.buyer@rml.test'],
            [
                'name' => 'Clara Vidal',
                'password' => 'password',
                'email_verified_at' => now(),
                'approval_status' => ApprovalStatus::Pending->value,
                'locale' => 'fr',
                'approved_at' => null,
            ],
        );
        $pendingBuyer->syncRoles([UserRole::BuyerAdmin->value]);

        $pendingBuyerCompany = $this->company(
            'Pending Buyer Company',
            CompanyType::Buyer,
            'Murcia',
            '30001',
            $admin,
        );
        $pendingBuyerCompany->update([
            'approval_status' => ApprovalStatus::Pending,
            'approved_at' => null,
            'approved_by' => null,
            'contact_name' => 'Clara Vidal',
        ]);

        BuyerProfile::query()->updateOrCreate(
            ['user_id' => $pendingBuyer->id],
            [
                'company_id' => $pendingBuyerCompany->id,
                'services_offered' => ['insulation'],
                'preferred_zones' => ['D1', 'D2'],
                'max_distance_km' => 50,
                'billing_status' => 'pending',
                'approval_status' => ApprovalStatus::Pending,
            ],
        );
    }

    private function company(
        string $name,
        CompanyType $type,
        string $city,
        string $postcode,
        User $approver,
        bool $individual = false,
    ): Company {
        $location = SpanishLocations::companyAttributes($city);

        return Company::query()->updateOrCreate(
            ['name' => $name, 'type' => $type->value],
            [
                'approval_status' => ApprovalStatus::Approved,
                'contact_name' => $individual ? 'Contact' : $name.' Contact',
                'email' => Str::slug($name, '.').'@demo.rml.test',
                'phone' => '+34911'.str_pad((string) abs(crc32($name) % 100000), 5, '0', STR_PAD_LEFT),
                'whatsapp' => '+34610'.str_pad((string) abs(crc32($name) % 100000), 5, '0', STR_PAD_LEFT),
                ...$location,
                'notes' => 'Expanded demo '.$type->value.' company',
                'approved_at' => now(),
                'approved_by' => $approver->id,
            ],
        );
    }
}
