<?php

namespace App\Http\Middleware;

use App\Support\ApiClients\ApiClientRequestContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Lets through only a request made with an API client key: not an app token,
 * not a user token issued for a client.
 */
class EnsureApiClientKey
{
    public function handle(Request $request, Closure $next): Response
    {
        if (ApiClientRequestContext::resolve($request) === null || ! $request->user()->isServiceAccount()) {
            return response()->json([
                'success' => false,
                'message' => __('This endpoint requires an API client key.'),
            ], 403);
        }

        return $next($request);
    }
}
