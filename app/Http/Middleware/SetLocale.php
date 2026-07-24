<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $supported = ['en', 'es', 'fr'];

        // Session wins so EN/ES/FR switching applies immediately across pages.
        // LocaleController also persists the choice onto the authenticated user.
        $locale = $request->session()->get('locale')
            ?? $request->user()?->locale
            ?? config('app.locale', 'en');

        if (! in_array($locale, $supported, true)) {
            $locale = 'en';
        }

        app()->setLocale($locale);

        return $next($request);
    }
}
