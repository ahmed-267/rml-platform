<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Services\Payments\PaymentWebhookService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class MollieWebhookController extends Controller
{
    public function __construct(
        private readonly PaymentWebhookService $webhookService,
    ) {}

    public function __invoke(Request $request): Response
    {
        $payload = $request->all();

        if ($request->filled('id') && ! isset($payload['id'])) {
            $payload['id'] = $request->string('id')->toString();
        }

        $this->webhookService->handleMollie($payload);

        return response('OK', 200);
    }
}
