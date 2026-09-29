<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Server-side enforcement of the first-access password change (spec 0177): a
 * user still flagged `must_set_password` is blocked from the protected API
 * until they choose a password. Impersonation sessions are exempt (the admin
 * acts on behalf of the user and cannot know their password).
 */
class EnsurePasswordIsSet
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user?->must_set_password && $user->currentAccessToken()?->impersonated_by === null) {
            return new JsonResponse([
                'success' => false,
                'message' => __('auth.must_set_password'),
            ], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
