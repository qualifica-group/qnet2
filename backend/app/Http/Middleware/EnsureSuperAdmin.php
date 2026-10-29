<?php

namespace App\Http\Middleware;

use App\Services\UserService;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Fail-closed hard gate for the "Migrazioni" section (spec 0013): every
 * endpoint requires an authenticated user holding the privileged
 * `super-admin` role (UserService::PRIVILEGED_ROLE — no permission is
 * granular here, no hardcoded role string). Anonymous -> AuthenticationException
 * (401, same as auth:sanctum); authenticated without the role ->
 * AuthorizationException (403).
 */
class EnsureSuperAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            throw new AuthenticationException;
        }

        if (! $user->hasRole(UserService::PRIVILEGED_ROLE)) {
            throw new AuthorizationException(__('This section is reserved to the super-admin.'));
        }

        return $next($request);
    }
}
