<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use App\Support\Permissions;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAuditorAccess
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(403);
        }

        if ($user->hasRole(UserRole::InternalAuditor->value)) {
            return $next($request);
        }

        if ($user->hasRole(UserRole::SuperAdmin->value)) {
            return $next($request);
        }

        if (
            $user->hasRole(UserRole::AdminStaff->value)
            && $user->can(Permissions::AUDIT_LEADS)
        ) {
            return $next($request);
        }

        abort(403);
    }
}
