<?php

namespace Database\Seeders;

use App\Enums\ApprovalStatus;
use App\Enums\AuditDecisionStatus;
use App\Enums\CommissionStatus;
use App\Enums\CompanyType;
use App\Enums\DisputeStatus;
use App\Enums\EvidenceFileType;
use App\Enums\EvidenceStatus;
use App\Enums\EvidenceVisibility;
use App\Enums\HomeownerEnquiryStatus;
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
use App\Models\BuyerProfile;
use App\Models\Commission;
use App\Models\Company;
use App\Models\Dispute;
use App\Models\HomeownerEnquiry;
use App\Models\Invoice;
use App\Models\Lead;
use App\Models\LeadAudit;
use App\Models\LeadEvidenceFile;
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
use App\Services\CommissionService;
use App\Services\LeadPricingService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DomainDemoSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()->where('email', 'admin@rml.test')->firstOrFail();
        $auditor = User::query()->where('email', 'auditor@rml.test')->firstOrFail();
        $sellerAdmin = User::query()->where('email', 'seller.admin@rml.test')->firstOrFail();
        $sellerStaff = User::query()->where('email', 'seller.staff@rml.test')->firstOrFail();
        $agent = User::query()->where('email', 'agent@rml.test')->firstOrFail();
        $buyer = User::query()->where('email', 'buyer@rml.test')->firstOrFail();

        $greenEnergy = $this->company('Green Energy Spain', CompanyType::Seller, 'Madrid', '28001', $admin);
        $solarFirst = $this->company('SolarFirst Iberia', CompanyType::Seller, 'Valencia', '46001', $admin);
        $ecoHomes = $this->company('EcoHomes España', CompanyType::Seller, 'Sevilla', '41001', $admin);
        $smithSurveys = $this->company('J. Smith Surveys', CompanyType::Seller, 'Barcelona', '08001', $admin, individual: true);

        $warmHomes = $this->company('Warm Homes Spain', CompanyType::Buyer, 'Madrid', '28002', $admin);
        $this->company('InstallPro Iberia', CompanyType::Buyer, 'Bilbao', '48001', $admin);
        $this->company('GreenFit Installers', CompanyType::Buyer, 'Málaga', '29001', $admin);

        SellerProfile::query()->updateOrCreate(
            ['user_id' => $sellerAdmin->id],
            [
                'company_id' => $greenEnergy->id,
                'seller_type' => SellerType::CompanyAdmin,
                'commission_rate' => 5.00,
                'approval_status' => ApprovalStatus::Approved,
            ],
        );

        SellerProfile::query()->updateOrCreate(
            ['user_id' => $sellerStaff->id],
            [
                'company_id' => $greenEnergy->id,
                'seller_type' => SellerType::SellerStaff,
                'commission_rate' => 10.00,
                'invited_by' => $sellerAdmin->id,
                'approval_status' => ApprovalStatus::Approved,
            ],
        );

        SellerProfile::query()->updateOrCreate(
            ['user_id' => $agent->id],
            [
                'company_id' => $smithSurveys->id,
                'seller_type' => SellerType::IndividualAgent,
                'commission_rate' => 15.00,
                'approval_status' => ApprovalStatus::Approved,
            ],
        );

        BuyerProfile::query()->updateOrCreate(
            ['user_id' => $buyer->id],
            [
                'company_id' => $warmHomes->id,
                'services_offered' => ['insulation', 'double_glazing'],
                'preferred_zones' => ['D1', 'D2', 'E1'],
                'max_distance_km' => 75,
                'billing_status' => 'active',
                'approval_status' => ApprovalStatus::Approved,
            ],
        );

        $pendingSeller = User::query()->where('email', 'pending.seller@rml.test')->firstOrFail();
        $pendingRetrofit = $this->company('Pending Retrofit Co', CompanyType::Seller, 'Granada', '18002', $admin);
        $pendingRetrofit->update([
            'approval_status' => ApprovalStatus::Pending,
            'approved_at' => null,
            'approved_by' => null,
            'contact_name' => 'Rafael Cano',
        ]);
        SellerProfile::query()->updateOrCreate(
            ['user_id' => $pendingSeller->id],
            [
                'company_id' => $pendingRetrofit->id,
                'seller_type' => SellerType::CompanyAdmin,
                'commission_rate' => 5.00,
                'approval_status' => ApprovalStatus::Pending,
            ],
        );

        $insulation = Scheme::query()->where('slug', 'insulation')->firstOrFail();
        $zones = Zone::query()->where('scheme_id', $insulation->id)->get()->keyBy('code');
        $pricing = new LeadPricingService;
        $commissionService = new CommissionService;
        $auditLog = new AuditLogService;

        $leadDefinitions = [
            [
                'reference' => 'LD-1041',
                'submitter' => $sellerAdmin,
                'company' => $greenEnergy,
                'zone' => 'D1',
                'status' => LeadStatus::Listed,
                'size' => 95,
                'city' => 'Madrid',
                'first' => 'Carlos',
                'last' => 'Garcia',
                'phone' => '+34600104101',
            ],
            [
                'reference' => 'LD-1042',
                'submitter' => $sellerStaff,
                'company' => $greenEnergy,
                'zone' => 'D2',
                'status' => LeadStatus::Listed,
                'size' => 110,
                'city' => 'Alcala de Henares',
                'first' => 'Maria',
                'last' => 'Lopez',
                'phone' => '+34600104102',
            ],
            [
                'reference' => 'LD-1043',
                'submitter' => $sellerStaff,
                'company' => $greenEnergy,
                'zone' => 'E1',
                'status' => LeadStatus::Sold,
                'size' => 84,
                'city' => 'Getafe',
                'first' => 'Javier',
                'last' => 'Martin',
                'phone' => '+34600104103',
            ],
            [
                'reference' => 'LD-1044',
                'submitter' => $agent,
                'company' => $smithSurveys,
                'zone' => 'E2',
                'status' => LeadStatus::PendingValidation,
                'size' => 72,
                'city' => 'Barcelona',
                'first' => 'Elena',
                'last' => 'Ruiz',
                'phone' => '+34600104104',
            ],
            [
                'reference' => 'LD-1045',
                'submitter' => $sellerAdmin,
                'company' => $greenEnergy,
                'zone' => 'D1',
                'status' => LeadStatus::NeedsMoreInformation,
                'size' => 60,
                'city' => 'Leganes',
                'first' => 'Pedro',
                'last' => 'Sanchez',
                'phone' => '+34600104105',
            ],
            [
                'reference' => 'LD-1046',
                'submitter' => $sellerAdmin,
                'company' => $solarFirst,
                'zone' => 'D2',
                'status' => LeadStatus::Rejected,
                'size' => 55,
                'city' => 'Valencia',
                'first' => 'Ana',
                'last' => 'Torres',
                'phone' => '+34600104106',
            ],
            [
                'reference' => 'LD-1047',
                'submitter' => $sellerAdmin,
                'company' => $ecoHomes,
                'zone' => 'E1',
                'status' => LeadStatus::Submitted,
                'size' => 100,
                'city' => 'Sevilla',
                'first' => 'Luis',
                'last' => 'Navarro',
                'phone' => '+34600104107',
            ],
        ];

        $leads = [];
        foreach ($leadDefinitions as $def) {
            $zone = $zones[$def['zone']];
            $lead = Lead::query()->updateOrCreate(
                ['lead_reference' => $def['reference']],
                [
                    'submitted_by_user_id' => $def['submitter']->id,
                    'seller_company_id' => $def['company']->id,
                    'scheme_id' => $insulation->id,
                    'zone_id' => $zone->id,
                    'status' => $def['status'],
                    'customer_first_name' => $def['first'],
                    'customer_last_name' => $def['last'],
                    'customer_phone' => $def['phone'],
                    'customer_whatsapp' => $def['phone'],
                    'customer_email' => Str::lower($def['first']).'.'.Str::lower($def['last']).'@example.es',
                    'address_line_1' => $def['city'].' Demo Street 1',
                    'city' => $def['city'],
                    'postcode' => '2800'.substr($def['reference'], -1),
                    'country' => 'ES',
                    'property_type' => 'detached',
                    'epc_rating' => 'E',
                    'size_m2' => $def['size'],
                    'distance_km' => 12.5 + ((int) substr($def['reference'], -1)),
                    'rejection_reason' => $def['status'] === LeadStatus::Rejected ? 'Incomplete evidence pack' : null,
                    'accepted_at' => in_array($def['status'], [LeadStatus::Listed, LeadStatus::Sold, LeadStatus::Priced], true) ? now()->subDays(5) : null,
                    'rejected_at' => $def['status'] === LeadStatus::Rejected ? now()->subDay() : null,
                    'listed_at' => in_array($def['status'], [LeadStatus::Listed, LeadStatus::Sold], true) ? now()->subDays(2) : null,
                    'sold_at' => $def['status'] === LeadStatus::Sold ? now()->subDay() : null,
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
            }

            LeadEvidenceFile::query()->updateOrCreate(
                [
                    'lead_id' => $lead->id,
                    'file_type' => EvidenceFileType::Photo,
                    'original_name' => $lead->lead_reference.'-photo.jpg',
                ],
                [
                    'uploaded_by_user_id' => $def['submitter']->id,
                    'path' => 'evidence/'.$lead->lead_reference.'/photo.jpg',
                    'disk' => 'local',
                    'mime_type' => 'image/jpeg',
                    'size' => 245760,
                    'visibility' => EvidenceVisibility::Private,
                    'status' => EvidenceStatus::Uploaded,
                ],
            );

            if (in_array($def['status'], [
                LeadStatus::Listed,
                LeadStatus::Sold,
                LeadStatus::PendingValidation,
                LeadStatus::NeedsMoreInformation,
                LeadStatus::Submitted,
            ], true) && $def['status'] !== LeadStatus::Rejected) {
                LeadAudit::query()->updateOrCreate(
                    ['lead_id' => $lead->id],
                    [
                        'auditor_user_id' => $auditor->id,
                        'final_decision_by_user_id' => in_array($def['status'], [LeadStatus::Listed, LeadStatus::Sold], true) ? $admin->id : null,
                        'status' => match ($def['status']) {
                            LeadStatus::NeedsMoreInformation => AuditDecisionStatus::NeedsMoreInformation,
                            LeadStatus::PendingValidation => AuditDecisionStatus::InReview,
                            LeadStatus::Submitted => AuditDecisionStatus::Pending,
                            default => AuditDecisionStatus::Accepted,
                        },
                        'audit_notes' => 'Demo audit for '.$lead->lead_reference,
                        'buying_price' => $lead->buying_price,
                        'selling_price' => $lead->selling_price,
                        'suggested_price' => $lead->selling_price,
                        'expected_margin' => $lead->expected_margin,
                        'completed_at' => in_array($def['status'], [LeadStatus::Listed, LeadStatus::Sold], true) ? now()->subDays(2) : null,
                    ],
                );
            }

            $leads[$def['reference']] = $lead->fresh();
        }

        $package = LeadPackage::query()->updateOrCreate(
            ['package_reference' => 'PKG-003'],
            [
                'name' => 'Madrid Insulation Mix',
                'package_type' => PackageType::MixedZone,
                'scheme_id' => $insulation->id,
                'requested_leads_count' => 2,
                'size_range_min' => 60,
                'size_range_max' => 120,
                'distance_range_min' => 0,
                'distance_range_max' => 60,
                'zone_mix' => ['D1' => 1, 'D2' => 1],
                'avg_price_per_m2' => 3.25,
                'estimated_total' => ((float) $leads['LD-1041']->selling_price) + ((float) $leads['LD-1042']->selling_price),
                'status' => PackageStatus::Available,
                'created_by_user_id' => $admin->id,
            ],
        );
        $package->leads()->sync([$leads['LD-1041']->id, $leads['LD-1042']->id]);

        $soldLead = $leads['LD-1043'];
        $payment = Payment::query()->updateOrCreate(
            ['payment_reference' => 'PAY-00091'],
            [
                'payer_user_id' => $buyer->id,
                'payer_company_id' => $warmHomes->id,
                'type' => PaymentType::BuyerPayment,
                'method' => PaymentMethod::Card,
                'provider' => 'demo',
                'provider_payment_id' => 'demo_txn_1043',
                'status' => PaymentStatus::Paid,
                'amount' => $soldLead->selling_price,
                'currency' => 'EUR',
                'paid_at' => now()->subDay(),
                'metadata' => ['note' => 'Demo paid purchase releasing customer details'],
            ],
        );

        $purchase = Purchase::query()->updateOrCreate(
            ['purchase_reference' => 'PUR-00041'],
            [
                'buyer_company_id' => $warmHomes->id,
                'buyer_user_id' => $buyer->id,
                'status' => PurchaseStatus::Paid,
                'total_amount' => $soldLead->selling_price,
                'total_size_m2' => $soldLead->size_m2,
                'payment_id' => $payment->id,
                'purchased_at' => now()->subDay(),
            ],
        );

        PurchaseItem::query()->updateOrCreate(
            ['purchase_id' => $purchase->id, 'lead_id' => $soldLead->id],
            [
                'lead_package_id' => null,
                'item_type' => PurchaseItemType::Lead,
                'quantity' => 1,
                'unit_price' => $soldLead->selling_price,
                'total_price' => $soldLead->selling_price,
            ],
        );

        Invoice::query()->updateOrCreate(
            ['invoice_reference' => 'INV-00084'],
            [
                'payment_id' => $payment->id,
                'purchase_id' => $purchase->id,
                'company_id' => $warmHomes->id,
                'user_id' => $buyer->id,
                'type' => InvoiceType::BuyerInvoice,
                'status' => InvoiceStatus::Paid,
                'subtotal' => $soldLead->selling_price,
                'tax_amount' => 0,
                'total' => $soldLead->selling_price,
                'currency' => 'EUR',
                'issued_at' => now()->subDay(),
            ],
        );

        Payout::query()->updateOrCreate(
            ['payout_reference' => 'PO-00021'],
            [
                'seller_company_id' => $greenEnergy->id,
                'seller_user_id' => $sellerAdmin->id,
                'payment_id' => null,
                'status' => PayoutStatus::Pending,
                'amount' => $soldLead->buying_price,
                'currency' => 'EUR',
                'due_date' => now()->addDays(7)->toDateString(),
                'notes' => 'Seller payout due for '.$soldLead->lead_reference,
            ],
        );

        $calc = $commissionService->calculate((float) $soldLead->selling_price, 10.00);
        $staffCommission = Commission::query()->updateOrCreate(
            ['commission_reference' => 'COM-00011'],
            [
                'lead_id' => $soldLead->id,
                'seller_user_id' => $sellerStaff->id,
                'seller_company_id' => $greenEnergy->id,
                'percentage' => $calc['percentage'],
                'base_amount' => $calc['base_amount'],
                'commission_amount' => $calc['commission_amount'],
                'status' => CommissionStatus::Pending,
            ],
        );
        $commissionService->markDueIfEligible($staffCommission, $soldLead, $purchase);

        $pendingPayment = Payment::query()->updateOrCreate(
            ['payment_reference' => 'PAY-00092'],
            [
                'payer_user_id' => $buyer->id,
                'payer_company_id' => $warmHomes->id,
                'type' => PaymentType::BuyerPayment,
                'method' => PaymentMethod::ManualBankTransfer,
                'status' => PaymentStatus::Pending,
                'amount' => $leads['LD-1041']->selling_price,
                'currency' => 'EUR',
                'due_date' => now()->addDays(3)->toDateString(),
            ],
        );

        $pendingPurchase = Purchase::query()->updateOrCreate(
            ['purchase_reference' => 'PUR-00042'],
            [
                'buyer_company_id' => $warmHomes->id,
                'buyer_user_id' => $buyer->id,
                'status' => PurchaseStatus::Pending,
                'total_amount' => $leads['LD-1041']->selling_price,
                'total_size_m2' => $leads['LD-1041']->size_m2,
                'payment_id' => $pendingPayment->id,
                'purchased_at' => null,
            ],
        );

        PurchaseItem::query()->updateOrCreate(
            ['purchase_id' => $pendingPurchase->id, 'lead_id' => $leads['LD-1041']->id],
            [
                'lead_package_id' => null,
                'item_type' => PurchaseItemType::Lead,
                'quantity' => 1,
                'unit_price' => $leads['LD-1041']->selling_price,
                'total_price' => $leads['LD-1041']->selling_price,
            ],
        );

        $thread = MessageThread::query()->updateOrCreate(
            ['thread_reference' => 'THR-00015'],
            [
                'subject' => 'Evidence clarification for LD-1045',
                'category' => MessageThreadCategory::SellerIssue,
                'status' => MessageThreadStatus::Open,
                'created_by_user_id' => $sellerAdmin->id,
                'assigned_to_user_id' => $admin->id,
                'related_lead_id' => $leads['LD-1045']->id,
            ],
        );

        Message::query()->updateOrCreate(
            [
                'message_thread_id' => $thread->id,
                'sender_user_id' => $sellerAdmin->id,
                'body' => 'Please confirm which photos are still required for LD-1045.',
            ],
            ['read_at' => null],
        );

        Message::query()->updateOrCreate(
            [
                'message_thread_id' => $thread->id,
                'sender_user_id' => $admin->id,
                'body' => 'Please upload a clearer exterior photo and the signed homeowner agreement.',
            ],
            ['read_at' => null],
        );

        $buyerThread = MessageThread::query()->updateOrCreate(
            ['thread_reference' => 'THR-00016'],
            [
                'subject' => 'Payment query for PUR-00041',
                'category' => MessageThreadCategory::PaymentQuery,
                'status' => MessageThreadStatus::Pending,
                'created_by_user_id' => $buyer->id,
                'assigned_to_user_id' => $admin->id,
                'related_purchase_id' => $purchase->id,
            ],
        );

        Message::query()->updateOrCreate(
            [
                'message_thread_id' => $buyerThread->id,
                'sender_user_id' => $buyer->id,
                'body' => 'Can you confirm the invoice PDF will be available today?',
            ],
            ['read_at' => null],
        );

        $auditorThread = MessageThread::query()->updateOrCreate(
            ['thread_reference' => 'THR-00017'],
            [
                'subject' => 'Audit question — LD-1044 evidence quality',
                'category' => MessageThreadCategory::AuditQuestion,
                'status' => MessageThreadStatus::Open,
                'created_by_user_id' => $auditor->id,
                'assigned_to_user_id' => $admin->id,
                'related_lead_id' => $leads['LD-1044']->id,
            ],
        );

        Message::query()->updateOrCreate(
            [
                'message_thread_id' => $auditorThread->id,
                'sender_user_id' => $auditor->id,
                'body' => 'The exterior photo looks cropped — should I request a retake before recommending accept?',
            ],
            ['read_at' => null],
        );

        Message::query()->updateOrCreate(
            [
                'message_thread_id' => $auditorThread->id,
                'sender_user_id' => $admin->id,
                'body' => 'Yes — request more information if the property boundary is unclear.',
            ],
            ['read_at' => null],
        );

        HomeownerEnquiry::query()->updateOrCreate(
            ['reference' => 'HE-00008'],
            [
                'name' => 'Isabel Fernandez',
                'phone' => '+34600999888',
                'email' => 'isabel.fernandez@example.es',
                'address' => 'Calle Mayor 12, Toledo',
                'postcode' => '45001',
                'service_interested_in' => 'Insulation',
                'message' => 'Interested in free installation assessment.',
                'consent' => true,
                'status' => HomeownerEnquiryStatus::New,
            ],
        );

        Dispute::query()->updateOrCreate(
            ['dispute_reference' => 'DSP-00005'],
            [
                'purchase_id' => $purchase->id,
                'lead_id' => $soldLead->id,
                'buyer_user_id' => $buyer->id,
                'buyer_company_id' => $warmHomes->id,
                'reason' => 'Customer unreachable after purchase (demo dispute).',
                'status' => DisputeStatus::Open,
            ],
        );

        $auditLog->log(
            'demo.seed_completed',
            $soldLead,
            null,
            ['leads' => count($leads), 'companies' => 7],
            $admin,
        );

        $auditLog->log(
            'lead.accepted',
            $leads['LD-1041'],
            ['status' => LeadStatus::PendingValidation->value],
            ['status' => LeadStatus::Listed->value],
            $admin,
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
        return Company::query()->updateOrCreate(
            ['name' => $name, 'type' => $type->value],
            [
                'approval_status' => ApprovalStatus::Approved,
                'contact_name' => $individual ? 'Pablo Herrera' : $name.' Contact',
                'email' => Str::slug($name, '.').'@demo.rml.test',
                'phone' => '+34911000000',
                'whatsapp' => '+34610000000',
                'address' => $city.' Business Park 1',
                'city' => $city,
                'postcode' => $postcode,
                'country' => 'ES',
                'latitude' => 40.4168,
                'longitude' => -3.7038,
                'notes' => 'Demo '.$type->value.' company',
                'approved_at' => now(),
                'approved_by' => $approver->id,
            ],
        );
    }
}
