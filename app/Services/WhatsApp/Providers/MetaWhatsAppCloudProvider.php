<?php

namespace App\Services\WhatsApp\Providers;

use App\Contracts\WhatsApp\WhatsAppProviderInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MetaWhatsAppCloudProvider implements WhatsAppProviderInterface
{
    public function providerName(): string
    {
        return 'meta_cloud';
    }

    public function isConfigured(): bool
    {
        return filled(config('whatsapp.access_token'))
            && filled(config('whatsapp.phone_number_id'));
    }

    public function sendText(string $toPhone, string $body): array
    {
        if (! $this->isConfigured()) {
            return [
                'success' => false,
                'provider_message_id' => null,
                'error' => __('rml.whatsapp.not_configured'),
            ];
        }

        $phoneNumberId = config('whatsapp.phone_number_id');
        $token = config('whatsapp.access_token');

        try {
            $response = Http::withToken($token)
                ->acceptJson()
                ->post("https://graph.facebook.com/v19.0/{$phoneNumberId}/messages", [
                    'messaging_product' => 'whatsapp',
                    'to' => $toPhone,
                    'type' => 'text',
                    'text' => [
                        'preview_url' => false,
                        'body' => $body,
                    ],
                ]);

            if (! $response->successful()) {
                Log::warning('WhatsApp send failed', [
                    'status' => $response->status(),
                    'body' => $response->json(),
                ]);

                return [
                    'success' => false,
                    'provider_message_id' => null,
                    'error' => $response->json('error.message') ?? __('rml.whatsapp.send_failed'),
                ];
            }

            return [
                'success' => true,
                'provider_message_id' => $response->json('messages.0.id'),
                'error' => null,
            ];
        } catch (\Throwable $e) {
            Log::warning('WhatsApp send exception', ['error' => $e->getMessage()]);

            return [
                'success' => false,
                'provider_message_id' => null,
                'error' => __('rml.whatsapp.send_failed'),
            ];
        }
    }
}
