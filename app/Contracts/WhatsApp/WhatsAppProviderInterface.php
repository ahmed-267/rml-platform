<?php

namespace App\Contracts\WhatsApp;

interface WhatsAppProviderInterface
{
    public function providerName(): string;

    public function isConfigured(): bool;

    /**
     * @return array{success: bool, provider_message_id: string|null, error: string|null}
     */
    public function sendText(string $toPhone, string $body): array;
}
