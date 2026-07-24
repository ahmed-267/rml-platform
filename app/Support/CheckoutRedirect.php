<?php

namespace App\Support;

use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

final class CheckoutRedirect
{
    /**
     * External Stripe Checkout redirect that works for Inertia XHR posts.
     * Inertia requests get 409 + X-Inertia-Location; normal requests get redirect()->away().
     */
    public static function to(string $checkoutUrl): Response
    {
        return Inertia::location($checkoutUrl);
    }

    public static function awayOrInertia(string $checkoutUrl): Response|RedirectResponse
    {
        return self::to($checkoutUrl);
    }
}
