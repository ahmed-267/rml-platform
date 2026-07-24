<?php

namespace App\Http\Middleware;

use App\Enums\ApprovalStatus;
use App\Support\PortalRouter;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAccountApproved
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        if (PortalRouter::isInternalRole($user)) {
            return $next($request);
        }

        if ($user->approval_status === ApprovalStatus::Approved) {
            return $next($request);
        }

        if ($request->routeIs('pending-approval', 'logout', 'locale.update', 'profile.*')) {
            return $next($request);
        }

        return redirect()->route('pending-approval');
    }
}
