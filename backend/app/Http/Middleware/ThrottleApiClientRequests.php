<?php

namespace App\Http\Middleware;

use App\Support\ApiClients\ApiClientRequestContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Symfony\Component\HttpFoundation\Response;

/**
 * Per-client rate limit (spec 0210, AC-008), applied ONLY to requests made
 * with a token bound to an API client (the key or a client-login token): they
 * share one counter per client. Requests of the app, or without a token, pass
 * untouched.
 *
 * It runs in the `api` group, before the route's `auth:sanctum`, so the token
 * is read through `user('sanctum')` (ApiClientRequestContext). An invalid
 * token resolves to no client here and is rejected with 401 by the route.
 */
class ThrottleApiClientRequests
{
    public const string LIMITER = 'api-client';

    public function __construct(private readonly ThrottleRequests $throttle) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (ApiClientRequestContext::resolve($request) === null) {
            return $next($request);
        }

        return $this->throttle->handle($request, $next, self::LIMITER);
    }
}
