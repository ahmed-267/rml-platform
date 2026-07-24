<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Services\Payments\PaymentWebhookService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Stripe\Exception\SignatureVerificationException;

class StripeWebhookController extends Controller
{
    public function __construct(
        private readonly PaymentWebhookService $webhookService,
    ) {}

    public function __invoke(Request $request): Response
    {
        $payload = $request->getContent();
        $signature = (string) $request->header('Stripe-Signature', '');

        try {
            $this->webhookService->handleStripe([
                'payload' => $payload,
                'signature' => $signature,
            ]);
        } catch (SignatureVerificationException|\UnexpectedValueException) {
            return response('Invalid signature', 400);
        } catch (\RuntimeException $e) {
            // Amount/currency mismatch — acknowledge to avoid infinite retries after logging,
            // but return 400 so operators notice in Stripe dashboard.
            report($e);

            return response($e->getMessage(), 400);
        }

        return response('OK', 200);
    }
}
