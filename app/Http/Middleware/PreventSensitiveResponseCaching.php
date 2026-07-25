<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Prevent CDNs / shared caches from storing auth redirects or portal HTML
 * under public URLs such as /login (which can look like "auto login").
 */
class PreventSensitiveResponseCaching
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($this->shouldDisableCaching($request)) {
            $response->headers->set(
                'Cache-Control',
                'private, no-store, no-cache, must-revalidate, max-age=0',
            );
            $response->headers->set('Pragma', 'no-cache');
            $response->headers->set('Expires', '0');
        }

        return $response;
    }

    private function shouldDisableCaching(Request $request): bool
    {
        if ($request->user()) {
            return true;
        }

        return $request->is([
            'login',
            'register',
            'register/*',
            'forgot-password',
            'reset-password',
            'reset-password/*',
            'pending-approval',
            'verify-email',
            'verify-email/*',
            'confirm-password',
            'dashboard',
            'admin',
            'admin/*',
            'seller',
            'seller/*',
            'buyer',
            'buyer/*',
            'auditor',
            'auditor/*',
            'profile',
        ]);
    }
}
