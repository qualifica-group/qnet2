<?php

namespace App\Http\Middleware;

use App\Enums\ApiClientMethod;
use App\Enums\HttpStatusEnum;
use App\Support\ApiClients\ApiClientRequestContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Refuses (403) a request made with a token of an API client when the client
 * is not allowed the HTTP method (spec 0392). Requests of the app, made without
 * a client token, are never touched.
 */
class EnsureApiClientMethodAllowed
{
    public function handle(Request $request, Closure $next): Response
    {
        // Step 1: only requests made through an API client are gated
        $client = ApiClientRequestContext::resolve($request);
        $uri = $this->relativeUri($request);

        if ($client === null || in_array($uri, config('external-api.method_gate.always_allowed_routes'), true)) {
            return $next($request);
        }

        // Step 2: the permission that governs this method on this route
        $readOnly = in_array($uri, config('external-api.method_gate.read_only_post_routes'), true);
        $method = ApiClientMethod::forRequest($request->method(), $readOnly);

        if ($client->allowsMethod($method)) {
            return $next($request);
        }

        return response()->json([
            'success' => false,
            'message' => __('This API client is not allowed to use the :method method.', ['method' => $request->method()]),
        ], HttpStatusEnum::FORBIDDEN->value);
    }

    private function relativeUri(Request $request): string
    {
        $uri = (string) $request->route()?->uri();
        $prefix = trim((string) config('external-api.prefix'), '/').'/';

        return str_starts_with($uri, $prefix) ? substr($uri, strlen($prefix)) : $uri;
    }
}
