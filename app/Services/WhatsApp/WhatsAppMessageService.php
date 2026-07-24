<?php

namespace App\Services\WhatsApp;

use App\Enums\UserRole;
use App\Models\Lead;
use App\Models\Purchase;
use App\Models\User;
use App\Models\WhatsAppSendLog;
use App\Services\AuditLogService;
use App\Services\Buyer\LeadReleaseService;
use App\Services\WhatsApp\Providers\MetaWhatsAppCloudProvider;
use App\Support\Permissions;
use Illuminate\Validation\ValidationException;

class WhatsAppMessageService
{
    public function __construct(
        private readonly MetaWhatsAppCloudProvider $provider = new MetaWhatsAppCloudProvider,
        private readonly LeadReleaseService $releaseService = new LeadReleaseService,
        private readonly AuditLogService $auditLogService = new AuditLogService,
    ) {}

    public function isConfigured(): bool
    {
        return $this->provider->isConfigured();
    }

    public function sendPurchasedLeadDetails(User $actor, Purchase $purchase, ?Lead $lead = null, ?string $toPhone = null): WhatsAppSendLog
    {
        if (! $this->releaseService->purchaseIsReleased($purchase)) {
            throw ValidationException::withMessages([
                'whatsapp' => __('rml.whatsapp.requires_paid_purchase'),
            ]);
        }

        $companyId = $actor->buyerProfile?->company_id;
        $isAdmin = $actor->can(Permissions::MANAGE_PAYMENTS)
            || $actor->hasRole(UserRole::SuperAdmin->value);

        if (! $isAdmin && ($companyId === null || $purchase->buyer_company_id !== $companyId)) {
            abort(403);
        }

        $purchase->loadMissing(['items.lead.scheme', 'items.lead.zone', 'payment']);
        $leads = $lead
            ? collect([$lead])
            : $this->releaseService->releasedLeadsForPurchase($purchase);

        if ($leads->isEmpty()) {
            throw ValidationException::withMessages([
                'whatsapp' => __('rml.whatsapp.no_released_leads'),
            ]);
        }

        $targetLead = $leads->first();
        $phone = $this->normalisePhone(
            $toPhone
            ?: $actor->buyerProfile?->company?->whatsapp
            ?: $actor->phone
        );

        if (! $phone) {
            throw ValidationException::withMessages([
                'whatsapp' => __('rml.whatsapp.phone_required'),
            ]);
        }

        $body = $this->buildLeadDetailsBody($targetLead, $purchase);

        if (! $this->provider->isConfigured()) {
            $log = WhatsAppSendLog::query()->create([
                'user_id' => $actor->id,
                'purchase_id' => $purchase->id,
                'lead_id' => $targetLead->id,
                'to_phone' => $phone,
                'status' => 'failed',
                'provider' => $this->provider->providerName(),
                'body' => $body,
                'error_message' => __('rml.whatsapp.not_configured'),
            ]);

            $this->auditLogService->log('whatsapp.send_failed', $log, null, [
                'reason' => 'not_configured',
                'purchase_reference' => $purchase->purchase_reference,
            ], $actor);

            throw ValidationException::withMessages([
                'whatsapp' => __('rml.whatsapp.not_configured'),
            ]);
        }

        $result = $this->provider->sendText($phone, $body);

        $log = WhatsAppSendLog::query()->create([
            'user_id' => $actor->id,
            'purchase_id' => $purchase->id,
            'lead_id' => $targetLead->id,
            'to_phone' => $phone,
            'status' => $result['success'] ? 'sent' : 'failed',
            'provider' => $this->provider->providerName(),
            'provider_message_id' => $result['provider_message_id'],
            'body' => $body,
            'error_message' => $result['error'],
        ]);

        $this->auditLogService->log(
            $result['success'] ? 'whatsapp.sent' : 'whatsapp.send_failed',
            $log,
            null,
            [
                'purchase_reference' => $purchase->purchase_reference,
                'lead_reference' => $targetLead->lead_reference,
                'to_phone' => $phone,
            ],
            $actor,
        );

        if (! $result['success']) {
            throw ValidationException::withMessages([
                'whatsapp' => $result['error'] ?? __('rml.whatsapp.send_failed'),
            ]);
        }

        return $log;
    }

    private function buildLeadDetailsBody(Lead $lead, Purchase $purchase): string
    {
        $lines = [
            'RML Energy Saving — purchased lead details',
            'Purchase: '.$purchase->purchase_reference,
            'Lead: '.$lead->lead_reference,
            'Customer: '.trim(($lead->customer_first_name ?? '').' '.($lead->customer_last_name ?? '')),
            'Phone: '.($lead->customer_phone ?? '—'),
            'WhatsApp: '.($lead->customer_whatsapp ?? '—'),
            'Email: '.($lead->customer_email ?? '—'),
            'Address: '.trim(implode(', ', array_filter([
                $lead->address_line_1,
                $lead->address_line_2,
                $lead->city,
                $lead->postcode,
                $lead->country,
            ]))),
            'Scheme: '.($lead->scheme?->name ?? '—'),
            'Zone: '.($lead->zone?->code ?? '—'),
            'Size m²: '.($lead->size_m2 ?? '—'),
            'Notes: '.($lead->notes ?? '—'),
        ];

        return implode("\n", $lines);
    }

    private function normalisePhone(?string $phone): ?string
    {
        if (! $phone) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $phone);
        if (! $digits) {
            return null;
        }

        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        }

        if (strlen($digits) <= 9) {
            $digits = config('whatsapp.default_country_code', '34').$digits;
        }

        return $digits;
    }
}
