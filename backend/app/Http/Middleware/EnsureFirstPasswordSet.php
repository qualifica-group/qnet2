<?php

namespace App\Http\Middleware;

use App\Authorization\Denials\ForbiddenResponse;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * First-access gate (spec 0355): a user flagged `must_set_password` can only
 * reach the endpoints needed to choose a new password, read the session and
 * log out; everything else answers 403 `password_change_required`.
 *
 * It runs in the `api` group, before the route's `auth:sanctum`, so the user is
 * read through `user('sanctum')` (same scheme as ThrottleApiClientRequests). A
 * missing or invalid token resolves to no user and proceeds to the route's 401.
 * Impersonation tokens are never blocked: the admin must keep working.
 */
class EnsureFirstPasswordSet
{
    /** "METHOD path" pairs reachable while the first password is pending (routes are unnamed). */
    private const array ALLOWED = [
        'POST api/auth/login',
        'POST api/auth/logout',
        'POST api/auth/refresh',
        'GET api/auth/me',
        'GET api/auth/me/abilities',
        'PUT api/auth/me/first-password',
        'PUT api/auth/me/password',
        'POST api/auth/forgot-password',
        'POST api/auth/reset-password',
        'POST api/auth/client-login',
        'GET api/auth/impersonation',
        'POST api/auth/stop-impersonation',
    ];

    public function __construct(private readonly ForbiddenResponse $forbidden) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->mustChangePassword($request) && ! $this->isAllowed($request)) {
            return $this->forbidden->passwordChangeRequired();
        }

        return $next($request);
    }

    private function mustChangePassword(Request $request): bool
    {
        $user = $request->user('sanctum');

        return $user instanceof User
            && $user->must_set_password
            && $user->currentAccessToken()?->impersonated_by === null;
    }

    private function isAllowed(Request $request): bool
    {
        return in_array($request->method().' '.$request->path(), self::ALLOWED, true);
    }
}
