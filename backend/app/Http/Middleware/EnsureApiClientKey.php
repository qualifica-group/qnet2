<?php

namespace App\Http\Middleware;

use App\Enums\HttpStatusEnum;
use App\Support\ApiClients\ApiClientRequestContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Lets through only a request made with an API client key: not an app token,
 * not a user token issued for a client. Same check as ClientLoginController.
 */
class EnsureApiClientKey
{
    public function handle(Request $request, Closure $next): Response
    {
        if (ApiClientRequestContext::resolve($request) === null || ! $request->user()->isServiceAccount()) {
            return response()->json([
                'success' => false,
                'message' => __('This endpoint requires an API client key.'),
            ], HttpStatusEnum::FORBIDDEN->value);
        }

        return $next($request);
    }
}
