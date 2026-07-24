<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\BuyerProfile;
use App\Models\Commission;
use App\Models\Company;
use App\Models\Dispute;
use App\Models\HomeownerEnquiry;
use App\Models\Lead;
use App\Models\LeadAudit;
use App\Models\LeadPackage;
use App\Models\Message;
use App\Models\MessageThread;
use App\Models\Payment;
use App\Models\Purchase;
use App\Models\Scheme;
use App\Models\SellerProfile;
use App\Models\Zone;
use Inertia\Inertia;
use Inertia\Response;

class DomainFoundationController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('Admin/DomainFoundation', [
            'counts' => [
                'schemes' => Scheme::query()->count(),
                'zones' => Zone::query()->count(),
                'leads' => Lead::query()->count(),
                'seller_companies' => Company::query()->where('type', 'seller')->count(),
                'buyer_companies' => Company::query()->where('type', 'buyer')->count(),
                'seller_profiles' => SellerProfile::query()->count(),
                'buyer_profiles' => BuyerProfile::query()->count(),
                'payments' => Payment::query()->count(),
                'purchases' => Purchase::query()->count(),
                'packages' => LeadPackage::query()->count(),
                'audits' => LeadAudit::query()->count(),
                'message_threads' => MessageThread::query()->count(),
                'messages' => Message::query()->count(),
                'commissions' => Commission::query()->count(),
                'homeowner_enquiries' => HomeownerEnquiry::query()->count(),
                'disputes' => Dispute::query()->count(),
                'audit_logs' => AuditLog::query()->count(),
            ],
            'samples' => [
                'leads' => Lead::query()
                    ->orderBy('lead_reference')
                    ->limit(8)
                    ->get(['lead_reference', 'status', 'size_m2'])
                    ->map(fn (Lead $lead) => [
                        'reference' => $lead->lead_reference,
                        'status' => $lead->status->value,
                        'size_m2' => $lead->size_m2,
                    ])
                    ->all(),
                'zone_prices' => Zone::query()
                    ->with(['pricingRules' => fn ($q) => $q->where('active', true)])
                    ->where('active', true)
                    ->whereHas('scheme', fn ($q) => $q->where('slug', 'insulation'))
                    ->orderBy('sort_order')
                    ->get()
                    ->map(fn (Zone $zone) => [
                        'code' => $zone->code,
                        'price_per_m2' => $zone->pricingRules->first()?->price_per_m2 !== null
                            ? (float) $zone->pricingRules->first()->price_per_m2
                            : null,
                    ])
                    ->all(),
            ],
        ]);
    }
}
